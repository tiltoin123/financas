<?php

namespace Models;

use DateTimeImmutable;
use DomainException;

class Extrato
{
    /**
     * Tudo que o painel mostra de um mês ('YYYY-MM').
     * Mês fechado: lê os eventos materializados.
     * Mês aberto: calcula as ocorrências a partir das recorrências (nada é gravado).
     */
    public static function doMes(int $usuarioId, string $anoMes): array
    {
        if (!Hardcap::podeVer($anoMes)) {
            throw new DomainException('Esse mês está fora da janela de visualização.');
        }

        $hoje = (new DateTimeImmutable('today'))->format('Y-m-d');
        $fechado = SaldoMensal::buscarPorMes($usuarioId, $anoMes)?->fechado === true;
        $recorrencias = Recorrencia::listarPorUsuario($usuarioId);

        $nomesCategoria = [];
        foreach (Categoria::listarPorUsuario($usuarioId) as $c) {
            $nomesCategoria[$c->getId()] = $c->nome;
        }

        // Linhas brutas: eventos (mês fechado) ou ocorrências calculadas (mês aberto)
        $brutos = [];

        if ($fechado) {
            foreach (Evento::listarPorMes($usuarioId, $anoMes) as $e) {
                $brutos[] = [
                    'origem' => 'evento',
                    'eventoId' => $e->id,
                    'recorrenciaId' => $e->recorrenciaId,
                    'nome' => $e->nome,
                    'descricao' => $e->descricao,
                    'valor' => $e->valor,
                    'data' => $e->data,
                    'categoriaId' => $e->categoriaId,
                ];
            }
        } else {
            foreach ($recorrencias as $r) {
                $data = $r->dataNoMes($anoMes);

                if ($data === null) {
                    continue;
                }

                $brutos[] = [
                    'origem' => 'recorrencia',
                    'eventoId' => null,
                    'recorrenciaId' => $r->id,
                    'nome' => $r->nome,
                    'descricao' => $r->descricao,
                    'valor' => $r->valor,
                    'data' => $data,
                    'categoriaId' => $r->categoriaId,
                ];
            }

            usort($brutos, fn($a, $b) => [$a['data'], $a['nome']] <=> [$b['data'], $b['nome']]);
        }

        // Campos derivados e totais
        $lancamentos = [];
        $receitas = 0.0;
        $despesas = 0.0;

        foreach ($brutos as $l) {
            $categoriaId = $l['categoriaId'];

            $l['categoria'] = $categoriaId !== null ? ($nomesCategoria[$categoriaId] ?? null) : null;
            $l['tipo'] = $l['valor'] < 0 ? 'despesa' : 'receita';
            $l['realizado'] = $l['data'] <= $hoje; // status vem da data, nunca é guardado

            if ($l['valor'] < 0) {
                $despesas += $l['valor'];
            } else {
                $receitas += $l['valor'];
            }

            $lancamentos[] = $l;
        }

        // Limites da navegação: do mais antigo entre a janela e a primeira recorrência até o fim da janela
        $minimo = Hardcap::inicioJanela();
        foreach ($recorrencias as $r) {
            $minimo = min($minimo, substr($r->dataInicio, 0, 7));
        }

        return [
            'anoMes' => $anoMes,
            'fechado' => $fechado,
            'pendentes' => $fechado ? [] : Fechamento::mesesParaFechar($usuarioId, $anoMes),
            'minimo' => $minimo,
            'maximo' => Hardcap::fimJanela(),
            'saldoPrevisto' => Saldo::previstoNoFimDe($usuarioId, $anoMes),
            'receitas' => round($receitas, 2),
            'despesas' => round($despesas, 2),
            'lancamentos' => $lancamentos,
        ];
    }
}
