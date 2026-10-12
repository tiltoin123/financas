<?php

namespace Models;

use Core\Db;
use InvalidArgumentException;

class SaldoMensal
{
    public function __construct(
        public readonly int $id,
        public readonly int $usuarioId,
        public readonly string $anoMes,
        public readonly float $saldoInicial,
        public readonly float $saldoFinal,
        public readonly bool $fechado,
        public readonly ?string $fechadoEm
    ) {}

    // ---------- Consultas ----------

    public static function buscarPorMes(int $usuarioId, string $anoMes): ?self
    {
        self::validarAnoMes($anoMes);
        $db = Db::con();

        $sql = "SELECT id, usuario_id, ano_mes, saldo_inicial, saldo_final, fechado, fechado_em
                FROM saldos_mensais
                WHERE usuario_id = :usuario_id AND ano_mes = :ano_mes
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':ano_mes' => $anoMes
        ]);
        $data = $stmt->fetch();

        return $data ? self::deLinha($data) : null;
    }

    /**
     * Último mês FECHADO do usuário. Se $antesDe for informado ('YYYY-MM'),
     * considera só meses anteriores a ele.
     */
    public static function ultimoFechado(int $usuarioId, ?string $antesDe = null): ?self
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, ano_mes, saldo_inicial, saldo_final, fechado, fechado_em
                FROM saldos_mensais
                WHERE usuario_id = :usuario_id AND fechado = 1";
        $params = [':usuario_id' => $usuarioId];

        if ($antesDe !== null) {
            self::validarAnoMes($antesDe);
            $sql .= " AND ano_mes < :antes_de";
            $params[':antes_de'] = $antesDe;
        }

        $sql .= " ORDER BY ano_mes DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch();

        return $data ? self::deLinha($data) : null;
    }

    /** @return self[] */
    public static function listarPorUsuario(int $usuarioId): array
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, ano_mes, saldo_inicial, saldo_final, fechado, fechado_em
                FROM saldos_mensais
                WHERE usuario_id = :usuario_id
                ORDER BY ano_mes ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario_id' => $usuarioId]);

        $lista = [];
        foreach ($stmt->fetchAll() as $linha) {
            $lista[] = self::deLinha($linha);
        }

        return $lista;
    }

    // ---------- Persistência ----------

    /**
     * Grava o snapshot do mês. Se já existe (mesmo usuário e mês), atualiza.
     * Usado tanto no fechamento quanto no recálculo em cascata.
     */
    public static function gravar(
        int $usuarioId,
        string $anoMes,
        float $saldoInicial,
        float $saldoFinal,
        bool $fechado
    ): self {
        self::validarAnoMes($anoMes);
        $db = Db::con();

        $sql = "INSERT INTO saldos_mensais
                    (usuario_id, ano_mes, saldo_inicial, saldo_final, fechado, fechado_em)
                VALUES
                    (:usuario_id, :ano_mes, :saldo_inicial, :saldo_final, :fechado, :fechado_em)
                AS novo
                ON DUPLICATE KEY UPDATE
                    saldo_inicial = novo.saldo_inicial,
                    saldo_final   = novo.saldo_final,
                    fechado       = novo.fechado,
                    fechado_em    = novo.fechado_em";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':ano_mes' => $anoMes,
            ':saldo_inicial' => $saldoInicial,
            ':saldo_final' => $saldoFinal,
            ':fechado' => $fechado ? 1 : 0,
            ':fechado_em' => $fechado ? date('Y-m-d H:i:s') : null
        ]);

        return self::buscarPorMes($usuarioId, $anoMes);
    }

    // ---------- Internos ----------

    private static function deLinha(array $d): self
    {
        return new self(
            (int) $d['id'],
            (int) $d['usuario_id'],
            $d['ano_mes'],
            (float) $d['saldo_inicial'],
            (float) $d['saldo_final'],
            (bool) $d['fechado'],
            $d['fechado_em']
        );
    }

    private static function validarAnoMes(string $anoMes): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $anoMes)) {
            throw new InvalidArgumentException('Mês inválido. Use o formato YYYY-MM.');
        }
    }
}
