<?php

namespace Models;

use Core\Db;
use Functions;
use DateTimeImmutable;
use InvalidArgumentException;
use PDOException;
use DomainException;
use Throwable;

class Fechamento
{
    // ---------- Etapa 1: materializar ----------

    /**
     * Gera as linhas de `eventos` do mês a partir das recorrências ativas.
     * Idempotente: não duplica evento que já existe para a recorrência no mês.
     * Retorna quantos eventos foram criados.
     */
    public static function materializar(int $usuarioId, string $anoMes): int
    {
        $criados = 0;

        foreach (Recorrencia::listarPorUsuario($usuarioId) as $rec) {
            $data = $rec->dataNoMes($anoMes);

            if ($data === null) {
                continue;
            }
            if (Evento::existeDaRecorrenciaNoMes($rec->id, $usuarioId, $anoMes)) {
                continue;
            }

            Evento::criar(
                $usuarioId,
                $rec->id,
                $rec->categoriaId,
                $rec->nome,
                $rec->descricao,
                $rec->valor,
                $data
            );
            $criados++;
        }

        return $criados;
    }

    // ---------- Etapa 2: calcular saldo ----------

    /**
     * saldo_inicial = saldo_final do último mês fechado anterior (ou 0).
     * saldo_final   = saldo_inicial + soma dos eventos do mês.
     * Grava o snapshot como fechado (upsert, serve também pro recálculo).
     */
    public static function calcularSaldo(int $usuarioId, string $anoMes): SaldoMensal
    {
        $anterior = SaldoMensal::ultimoFechado($usuarioId, $anoMes);

        $saldoInicial = $anterior?->saldoFinal ?? 0.0;
        $saldoFinal = round($saldoInicial + Evento::somarPorMes($usuarioId, $anoMes), 2);

        return SaldoMensal::gravar($usuarioId, $anoMes, $saldoInicial, $saldoFinal, true);
    }

    // ---------- Fechamento em cadeia ----------

    /**
     * Meses que precisam ser fechados para fechar $anoMes (inclui ele mesmo).
     * Serve para a tela mostrar a confirmação antes de executar.
     * @return string[] 'YYYY-MM' em ordem cronológica
     */
    public static function mesesParaFechar(int $usuarioId, string $anoMes): array
    {
        $pendentes = [];

        foreach (self::mesesEntre(self::primeiroMes($usuarioId, $anoMes), $anoMes) as $mes) {
            $saldo = SaldoMensal::buscarPorMes($usuarioId, $mes);

            if ($saldo === null || !$saldo->fechado) {
                $pendentes[] = $mes;
            }
        }

        return $pendentes;
    }

    /**
     * Fecha $anoMes e todos os anteriores ainda abertos, em ordem.
     * Retorna os meses que foram fechados.
     * @return string[]
     */
    public static function fecharMes(int $usuarioId, string $anoMes): array
    {
        if (!Hardcap::podeEditar($anoMes)) {
            throw new DomainException('Mês fora da janela de edição.');
        }

        return self::emTransacao(function () use ($usuarioId, $anoMes) {
            $pendentes = self::mesesParaFechar($usuarioId, $anoMes);

            foreach ($pendentes as $mes) {
                self::materializar($usuarioId, $mes);
                self::calcularSaldo($usuarioId, $mes);
            }

            // Se já havia meses fechados depois do alvo, o saldo_inicial deles ficou velho.
            self::recalcularDesde($usuarioId, self::proximoMes($anoMes));

            return $pendentes;
        });
    }

    // ---------- Cascata ----------

    /**
     * Recalcula, em ordem, todos os meses JÁ FECHADOS a partir de $anoMes (inclusive).
     * Não materializa nada: eventos de mês fechado são fato, só os saldos são refeitos.
     */
    public static function recalcularDesde(int $usuarioId, string $anoMes): void
    {
        self::emTransacao(function () use ($usuarioId, $anoMes) {
            foreach (SaldoMensal::listarPorUsuario($usuarioId) as $saldo) {
                if ($saldo->fechado && $saldo->anoMes >= $anoMes) {
                    self::calcularSaldo($usuarioId, $saldo->anoMes);
                }
            }
        });
    }

    // ---------- Internos ----------

    /** Começo da cadeia: mês da recorrência mais antiga (ou o próprio alvo, se for anterior). */
    private static function primeiroMes(int $usuarioId, string $alvo): string
    {
        $primeiro = $alvo;

        foreach (Recorrencia::listarPorUsuario($usuarioId) as $rec) {
            $mes = substr($rec->dataInicio, 0, 7);
            if ($mes < $primeiro) {
                $primeiro = $mes;
            }
        }

        return $primeiro;
    }

    /** @return string[] */
    private static function mesesEntre(string $de, string $ate): array
    {
        $mes = new DateTimeImmutable($de . '-01');
        $fim = new DateTimeImmutable($ate . '-01');
        $lista = [];

        while ($mes <= $fim) {
            $lista[] = $mes->format('Y-m');
            $mes = $mes->modify('first day of next month');
        }

        return $lista;
    }

    private static function proximoMes(string $anoMes): string
    {
        return (new DateTimeImmutable($anoMes . '-01'))
            ->modify('first day of next month')
            ->format('Y-m');
    }

    /** Transação que aceita ser chamada dentro de outra (não abre duas). */
    private static function emTransacao(callable $fn): mixed
    {
        $db = Db::con();

        if ($db->inTransaction()) {
            return $fn();
        }

        $db->beginTransaction();
        try {
            $resultado = $fn();
            $db->commit();
            return $resultado;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
