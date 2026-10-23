<?php

namespace Models;

use Core\Db;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDOException;

class Recorrencia
{
    public const PERIODICIDADES = ['unica', 'mensal'];
    public const TIPOS = ['receita', 'despesa'];

    // ---------- Propriedades: cada atribuição é validada ----------

    /** null até a recorrência ser salva. Só a própria classe escreve. */
    public private(set) ?int $id = null;

    public readonly int $usuarioId;

    /** Existência e dono da categoria são conferidos em salvar() (dependem do banco). */
    public ?int $categoriaId = null;

    public string $nome = '' {
        set {
            $limpo = trim($value);

            if ($limpo === '' || mb_strlen($limpo) > 150) {
                throw new InvalidArgumentException('Informe um nome de até 150 caracteres.');
            }

            $this->nome = $limpo;
        }
    }

    public ?string $descricao = null {
        set {
            $limpa = $value === null ? null : trim($value);
            $limpa = $limpa === '' ? null : $limpa;

            if ($limpa !== null && mb_strlen($limpa) > 255) {
                throw new InvalidArgumentException('A descrição pode ter no máximo 255 caracteres.');
            }

            $this->descricao = $limpa;
        }
    }

    /** Com sinal (despesa negativa). Só definirValor() escreve aqui. */
    public private(set) float $valor = 0.0;

    public string $dataInicio = '' {
        set {
            self::validarData($value, 'Data de início inválida.');
            $this->dataInicio = $value;
        }
    }

    public ?string $dataFim = null {
        set {
            if ($value !== null) {
                self::validarData($value, 'Data final inválida.');
            }

            $this->dataFim = $value;
        }
    }

    public string $periodicidade = 'unica' {
        set {
            if (!in_array($value, self::PERIODICIDADES, true)) {
                throw new InvalidArgumentException('Periodicidade inválida.');
            }

            $this->periodicidade = $value;
        }
    }

    /** Dia do mês em que a recorrência cai: sempre o dia da data de início. */
    public int $diaDoMes {
        get => (int) substr($this->dataInicio, 8, 2);
    }

    // ---------- Construção ----------

    /**
     * $id = 0: recorrência nova (sem id até ser salva).
     * $id > 0: carrega do banco; precisa ser do usuário, senão lança InvalidArgumentException.
     */
    public function __construct(int $id, int $usuarioId)
    {
        $this->usuarioId = $usuarioId;

        if ($id === 0) {
            return;
        }

        $linha = self::buscarLinha($id, $usuarioId);

        if ($linha === null) {
            throw new InvalidArgumentException('Recorrência não encontrada.');
        }

        $this->id = $id;
        $this->preencher($linha);
    }

    /**
     * Valor e tipo entram juntos porque o sinal depende do tipo.
     * Receita fica positiva e despesa negativa: o saldo é uma soma de valores.
     */
    public function definirValor(string $tipo, string|int|float $valor): void
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException('Escolha receita ou despesa.');
        }
        if (!is_numeric($valor)) {
            throw new InvalidArgumentException('Informe um valor numérico.');
        }

        $valor = round((float) $valor, 2);

        if ($valor < 0.01 || $valor > 99999999.99) {
            throw new InvalidArgumentException('Informe um valor entre 0,01 e 99.999.999,99.');
        }

        $this->valor = $tipo === 'despesa' ? -$valor : $valor;
    }

    // ---------- Regra de negócio ----------

    /**
     * Data em que esta recorrência cai no mês informado ('YYYY-MM'),
     * ou null se ela não está ativa naquele mês.
     * Se o mês não tem o dia (ex: 31 em abril), cai no último dia do mês.
     */
    public function dataNoMes(string $anoMes): ?string
    {
        $primeiro = new DateTimeImmutable($anoMes . '-01');
        $ultimoDia = (int) $primeiro->format('t');
        $dia = min($this->diaDoMes, $ultimoDia);

        $data = $anoMes . '-' . sprintf('%02d', $dia);

        if ($data < $this->dataInicio) {
            return null;
        }
        if ($this->dataFim !== null && $data > $this->dataFim) {
            return null;
        }

        return $data;
    }

    // ---------- Consultas ----------

    /** Versão que devolve null em vez de lançar exceção quando não encontra. */
    public static function buscarPorId(int $id, int $usuarioId): ?self
    {
        $linha = self::buscarLinha($id, $usuarioId);

        return $linha ? self::deLinha($linha, $usuarioId) : null;
    }

    /** @return self[] */
    public static function listarPorUsuario(int $usuarioId): array
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, categoria_id, nome, descricao, valor,
                       data_inicio, data_fim, periodicidade
                FROM recorrencias
                WHERE usuario_id = :usuario_id
                ORDER BY data_inicio ASC, id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario_id' => $usuarioId]);

        $lista = [];
        foreach ($stmt->fetchAll() as $linha) {
            $lista[] = self::deLinha($linha, $usuarioId);
        }

        return $lista;
    }

    // ---------- Persistência ----------

    /**
     * Cria (sem id) ou edita (com id). Lança InvalidArgumentException (dado inválido)
     * ou DomainException (regra de mês fechado / hardcap).
     */
    public function salvar(): bool
    {
        $this->validar();

        return $this->id === null ? $this->criar() : $this->editar();
    }

    private function criar(): bool
    {
        self::validarContraFechamento($this->usuarioId, $this->dataInicio, $this->dataFim, null);

        $db = Db::con();

        $sql = "INSERT INTO recorrencias
                    (usuario_id, categoria_id, nome, descricao, valor,
                     data_inicio, data_fim, periodicidade, dia_do_mes)
                VALUES
                    (:usuario_id, :categoria_id, :nome, :descricao, :valor,
                     :data_inicio, :data_fim, :periodicidade, :dia_do_mes)";
        $stmt = $db->prepare($sql);

        $ok = $stmt->execute([
            ':usuario_id' => $this->usuarioId,
            ':categoria_id' => $this->categoriaId,
            ':nome' => $this->nome,
            ':descricao' => $this->descricao,
            ':valor' => $this->valor,
            ':data_inicio' => $this->dataInicio,
            ':data_fim' => $this->dataFim,
            ':periodicidade' => $this->periodicidade,
            ':dia_do_mes' => $this->diaDoMes
        ]);

        if ($ok) {
            $this->id = (int) $db->lastInsertId();
        }

        return $ok;
    }

    private function editar(): bool
    {
        // Estado salvo no banco: base de comparação das regras de mês fechado.
        // Se sumiu (apagada por outra aba, por exemplo), não há o que editar.
        $atual = self::buscarPorId($this->id, $this->usuarioId);

        if ($atual === null) {
            throw new InvalidArgumentException('Recorrência não encontrada.');
        }

        self::validarContraFechamento($this->usuarioId, $this->dataInicio, $this->dataFim, $atual);

        $db = Db::con();

        $sql = "UPDATE recorrencias
                SET categoria_id = :categoria_id, nome = :nome, descricao = :descricao,
                    valor = :valor, data_inicio = :data_inicio, data_fim = :data_fim,
                    periodicidade = :periodicidade, dia_do_mes = :dia_do_mes
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            ':categoria_id' => $this->categoriaId,
            ':nome' => $this->nome,
            ':descricao' => $this->descricao,
            ':valor' => $this->valor,
            ':data_inicio' => $this->dataInicio,
            ':data_fim' => $this->dataFim,
            ':periodicidade' => $this->periodicidade,
            ':dia_do_mes' => $this->diaDoMes,
            ':id' => $this->id,
            ':usuario_id' => $this->usuarioId
        ]);
    }

    /** Retorna false se já existem eventos materializados (FK RESTRICT) ou se não foi salva. */
    public function apagar(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $db = Db::con();

        $sql = "DELETE FROM recorrencias
                WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);

        try {
            return $stmt->execute([
                ':id' => $this->id,
                ':usuario_id' => $this->usuarioId
            ]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Tira a recorrência de $anoMes ('YYYY-MM', um mês aberto onde ela aparece) em diante,
     * sem mexer no passado. Se não sobra nenhum mês antes dele, apaga de verdade;
     * senão ela passa a terminar no último dia do mês anterior.
     * Retorna 'apagada' ou 'encerrada'.
     */
    public function removerAPartirDe(string $anoMes): string
    {
        if ($this->id === null) {
            throw new InvalidArgumentException('Recorrência inválida.');
        }

        $mes = DateTimeImmutable::createFromFormat('!Y-m-d', $anoMes . '-01');
        if (!$mes || $mes->format('Y-m') !== $anoMes) {
            throw new InvalidArgumentException('Mês inválido.');
        }

        // Em mês fechado a linha da tela é um evento, não a recorrência
        if (SaldoMensal::buscarPorMes($this->usuarioId, $anoMes)?->fechado === true) {
            throw new DomainException('Esse mês já está fechado: apague o lançamento do mês.');
        }
        if ($this->dataNoMes($anoMes) === null) {
            throw new InvalidArgumentException('Esta recorrência não aparece nesse mês.');
        }

        // Nenhum mês anterior sobraria: apagar de verdade
        if (substr($this->dataInicio, 0, 7) >= $anoMes) {
            if (!$this->apagar()) {
                throw new DomainException('Esta recorrência já gerou eventos e não pode ser apagada.');
            }

            return 'apagada';
        }

        // Termina no mês anterior: o passado fica como está
        $this->dataFim = $mes->modify('last day of previous month')->format('Y-m-d');
        $this->salvar();

        return 'encerrada';
    }

    // ---------- Internos ----------

    /** Linha do banco ou null. Sempre filtra por usuário. */
    private static function buscarLinha(int $id, int $usuarioId): ?array
    {
        $db = Db::con();

        $sql = "SELECT id, usuario_id, categoria_id, nome, descricao, valor,
                       data_inicio, data_fim, periodicidade
                FROM recorrencias
                WHERE id = :id AND usuario_id = :usuario_id
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':usuario_id' => $usuarioId
        ]);
        $linha = $stmt->fetch();

        return $linha ?: null;
    }

    /** Monta a instância a partir de uma linha já lida (sem nova consulta). */
    private static function deLinha(array $linha, int $usuarioId): self
    {
        $r = new self(0, $usuarioId);
        $r->id = (int) $linha['id'];
        $r->preencher($linha);

        return $r;
    }

    /** Atribui os campos do banco pelos mesmos setters de qualquer outra atribuição. */
    private function preencher(array $linha): void
    {
        $valor = (float) $linha['valor'];

        $this->categoriaId = $linha['categoria_id'] !== null ? (int) $linha['categoria_id'] : null;
        $this->nome = $linha['nome'];
        $this->descricao = $linha['descricao'];
        $this->definirValor($valor < 0 ? 'despesa' : 'receita', abs($valor));
        $this->dataInicio = $linha['data_inicio'];
        $this->dataFim = $linha['data_fim'];
        $this->periodicidade = $linha['periodicidade'];
    }

    private static function validarData(string $data, string $mensagem): void
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$d || $d->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException($mensagem);
        }
    }

    /**
     * O que um setter isolado não enxerga: campos obrigatórios que ficaram nos valores
     * iniciais, relação entre os campos e a categoria (depende do banco).
     */
    private function validar(): void
    {
        if ($this->nome === '') {
            throw new InvalidArgumentException('Informe um nome de até 150 caracteres.');
        }
        if ($this->valor === 0.0) {
            throw new InvalidArgumentException('Informe o valor e se é receita ou despesa.');
        }
        if ($this->dataInicio === '') {
            throw new InvalidArgumentException('Data de início inválida.');
        }

        if ($this->periodicidade === 'unica') {
            $this->dataFim = $this->dataInicio; // evento único: data_fim = data_inicio
        } elseif ($this->dataFim !== null && $this->dataFim < $this->dataInicio) {
            throw new InvalidArgumentException('A data final não pode ser anterior à inicial.');
        }

        if ($this->categoriaId !== null && Categoria::buscarPorId($this->categoriaId, $this->usuarioId) === null) {
            throw new InvalidArgumentException('Categoria inválida.');
        }
    }

    /**
     * Regra "mês fechado é fato": a recorrência não pode passar a cobrir
     * um mês que já foi fechado, porque ninguém materializaria o evento.
     * Também respeita o hardcap na data de início.
     * $atual é o estado salvo no banco (null na criação).
     */
    private static function validarContraFechamento(
        int $usuarioId,
        string $dataInicio,
        ?string $dataFim,
        ?self $atual
    ): void {
        $inicioMudou = $atual === null || $dataInicio !== $atual->dataInicio;
        $mesInicio = substr($dataInicio, 0, 7);

        if ($inicioMudou && !Hardcap::podeEditar($mesInicio)) {
            throw new DomainException('A data de início está fora da janela de edição.');
        }

        $ultimo = SaldoMensal::ultimoFechado($usuarioId)?->anoMes;
        if ($ultimo === null) {
            return;
        }

        // Início novo ou alterado caindo em mês já fechado
        if ($inicioMudou && $mesInicio <= $ultimo) {
            throw new DomainException('O mês de início já está fechado.');
        }

        // Fim estendido para frente, alcançando meses que já fecharam sem essa recorrência
        if ($atual !== null && $atual->dataFim !== null) {
            $estendeu = $dataFim === null || $dataFim > $atual->dataFim;

            if ($estendeu && substr($atual->dataFim, 0, 7) <= $ultimo) {
                throw new DomainException('Não é possível estender a recorrência para meses já fechados.');
            }
        }
    }
}
