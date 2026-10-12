<?php

namespace Models;

use DateTimeImmutable;
use InvalidArgumentException;

class Hardcap
{
    /** Primeiro mês editável: janeiro do ano anterior. Formato 'YYYY-MM'. */
    public static function inicioJanela(?DateTimeImmutable $hoje = null): string
    {
        return (self::ano($hoje) - 1) . '-01';
    }

    /** Último mês visível/editável: dezembro do ano seguinte. */
    public static function fimJanela(?DateTimeImmutable $hoje = null): string
    {
        return (self::ano($hoje) + 1) . '-12';
    }

    /** Dentro da janela de 36 meses: pode editar e fechar. */
    public static function podeEditar(string $anoMes, ?DateTimeImmutable $hoje = null): bool
    {
        self::validar($anoMes);

        return $anoMes >= self::inicioJanela($hoje)
            && $anoMes <= self::fimJanela($hoje);
    }

    /** Passado: sempre visível (só leitura fora da janela). Futuro: só até o fim da janela. */
    public static function podeVer(string $anoMes, ?DateTimeImmutable $hoje = null): bool
    {
        self::validar($anoMes);

        return $anoMes <= self::fimJanela($hoje);
    }

    // ---------- Internos ----------

    private static function ano(?DateTimeImmutable $hoje): int
    {
        return (int) ($hoje ?? new DateTimeImmutable('today'))->format('Y');
    }

    private static function validar(string $anoMes): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $anoMes)) {
            throw new InvalidArgumentException('Mês inválido. Use o formato YYYY-MM.');
        }
    }
}
