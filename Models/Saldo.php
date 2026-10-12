<?php

namespace Models;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

class Saldo
{
    /** Saldo de hoje: tudo que já aconteceu até o dia atual. */
    public static function atual(int $usuarioId, ?DateTimeImmutable $hoje = null): float
    {
        $hoje ??= new DateTimeImmutable('today');

        return self::ate($usuarioId, $hoje->format('Y-m-d'));
    }

    /** Saldo previsto ao fim do mês 'YYYY-MM', se nada mudar. */
    public static function previstoNoFimDe(int $usuarioId, string $anoMes): float
    {
        [, $fim] = Evento::limitesDoMes($anoMes);

        return self::ate($usuarioId, $fim);
    }

    /**
     * Saldo acumulado até $dataLimite ('YYYY-MM-DD'), inclusive.
     *
     * Três partes:
     *  1. base: saldo_final do último mês fechado ANTERIOR ao mês da data;
     *  2. meses abertos entre a base e o mês da data: calculados pelas recorrências;
     *  3. mês da data: se já fechado, soma os eventos reais até a data;
     *     se aberto, soma as recorrências que caem até a data.
     */
    public static function ate(int $usuarioId, string $dataLimite): float
    {
        self::validarData($dataLimite);
        $mesLimite = substr($dataLimite, 0, 7);

        if (!Hardcap::podeVer($mesLimite)) {
            throw new DomainException('Data fora da janela de visualização.');
        }

        // 1. Base
        $base = SaldoMensal::ultimoFechado($usuarioId, $mesLimite);
        $total = $base?->saldoFinal ?? 0.0;

        $recorrencias = Recorrencia::listarPorUsuario($usuarioId);
        if ($recorrencias === []) {
            return round($total, 2);
        }

        // 2. Meses abertos entre a base e o mês da data (exclusive)
        $mes = $base !== null
            ? self::proximoMes($base->anoMes)
            : min(array_map(fn(Recorrencia $r) => substr($r->dataInicio, 0, 7), $recorrencias));

        for (; $mes < $mesLimite; $mes = self::proximoMes($mes)) {
            $total += self::somarRecorrencias($recorrencias, $mes);
        }

        // 3. Mês da data
        $doMes = SaldoMensal::buscarPorMes($usuarioId, $mesLimite);

        if ($doMes !== null && $doMes->fechado) {
            $total += Evento::somarPorPeriodo($usuarioId, $mesLimite . '-01', $dataLimite);
        } else {
            $total += self::somarRecorrencias($recorrencias, $mesLimite, $dataLimite);
        }

        return round($total, 2);
    }

    // ---------- Internos ----------

    /**
     * Soma o valor das recorrências ativas no mês, sem gerar evento.
     * Se $ateData for informado, ignora ocorrências posteriores a ela.
     *
     * @param Recorrencia[] $recorrencias
     */
    private static function somarRecorrencias(array $recorrencias, string $anoMes, ?string $ateData = null): float
    {
        $soma = 0.0;

        foreach ($recorrencias as $rec) {
            $data = $rec->dataNoMes($anoMes);

            if ($data === null) {
                continue;
            }
            if ($ateData !== null && $data > $ateData) {
                continue;
            }

            $soma += $rec->valor;
        }

        return $soma;
    }

    private static function proximoMes(string $anoMes): string
    {
        return (new DateTimeImmutable($anoMes . '-01'))
            ->modify('first day of next month')
            ->format('Y-m');
    }

    private static function validarData(string $data): void
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$d || $d->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException('Data inválida.');
        }
    }
}
