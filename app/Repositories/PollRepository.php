<?php

namespace Dizzi\Repositories;

use Dizzi\Repositories\IPollRepository;
use Dizzi\Models\Poll;
use Dizzi\Database\Database;
use Dizzi\Models\User;
use Dizzi\Models\Vote;

require_once("IPollRepository.php");
require_once("../Database/Database.php");

enum PollFinishStatus: string
{
    case FINISHED  = "finished";   // seja porque atualizou agora ou já estava finalizada
    case NOT_FOUND = "not_found";  // id não existe
    case ERROR     = "error";      // erro de execução
}

class PollRepository implements IPollRepository
{
    const END_TIME_DEFAULT = 135931893000; // 01/01/2030 em ms

    public function __construct()
    {
        return $this;
    }

    public function create_poll(Poll $poll): string|false
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();
            $pdo->beginTransaction();

            // Pega o start_time atual
            $startTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            if ($poll->duration) {
                // duration está em minutos → adiciona ao startTime
                $endTime = $startTime->modify("+" . ((int)$poll->duration) . " minutes");
            } else {
                // duration não especificado → adiciona 5 anos
                $endTime = $startTime->add(new \DateInterval('P5Y'));
            }

            // Cria a poll
            $stmtPoll = $pdo->prepare("
                INSERT INTO polls (user_id, title, description, start_time, end_time)
                VALUES (:user_id, :title, :description, :start_time, :end_time)
            ");
            $stmtPoll->execute([
                ':user_id'     => $poll->user->getUserName(),
                ':title'       => $poll->title,
                ':description' => $poll->description,
                ':start_time'  => $startTime->format('Y-m-d H:i:s'),
                ':end_time'    => $endTime->format('Y-m-d H:i:s'),
            ]);

            $pollId = $pdo->lastInsertId();

            // Insere opções
            $stmtOption = $pdo->prepare("
                INSERT INTO poll_options (poll_id, option_name, image_url)
                VALUES (:poll_id, :option_name, :image_url)
            ");

            foreach ($poll->options as $i => $option) {
                $optionName = is_array($option) ? $option['name'] : (string)$option;
                $imageUrl   = $poll->urls[$i] ?? null;

                $stmtOption->execute([
                    ':poll_id'     => $pollId,
                    ':option_name' => $optionName,
                    ':image_url'   => $imageUrl,
                ]);
            }

            // Gera código único da poll
            $code = bin2hex(random_bytes(4));

            $stmtCode = $pdo->prepare("
                INSERT INTO poll_codes (poll_id, code)
                VALUES (:poll_id, :code)
            ");
            $stmtCode->execute([
                ':poll_id' => $pollId,
                ':code'    => $code,
            ]);

            // Cria o bloco gênesis na mesma transação
            $previousHash = str_repeat('0', 64);
            $hash = hash('sha256', $poll->user->getUserName() . $pollId . $previousHash . time());

            $stmtGenesis = $pdo->prepare("
                INSERT INTO ledger (user_id, poll_id, option_id, previous_hash, hash)
                VALUES (:user_id, :poll_id, :option_id, :previous_hash, :hash)
            ");
            $stmtGenesis->execute([
                ':user_id'       => $poll->user->getUserName(),
                ':poll_id'       => $pollId,
                ':option_id'     => null,
                ':previous_hash' => $previousHash,
                ':hash'          => $hash
            ]);

            // Commita tudo
            $pdo->commit();
            return $pollId;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(["error" => "Erro ao criar poll + genesis: " . $e->getMessage()]);
            exit;
            error_log("Erro ao criar poll + genesis: " . $e->getMessage());
            return false;
        }
    }

    public function finishPoll(
        string $pollId,
        \DateTimeImmutable $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
    ): PollFinishStatus {
        try {
            $db = new Database();
            $pdo = $db->getConnection();
            $endTime = $endTime->format('Y-m-d H:i:s');


            $stmt = $pdo->prepare("UPDATE polls SET is_finished = 1, end_time = :endTime WHERE id = :pollId");
            $stmt->execute([':pollId' => $pollId, ':endTime' => $endTime]);

            if ($stmt->rowCount() > 0) {
                return PollFinishStatus::FINISHED;
            }

            // Se não alterou, mas o id existe → também considera finalizada
            $check = $pdo->prepare("SELECT 1 FROM polls WHERE id = :pollId");
            $check->execute([':pollId' => $pollId]);

            if ($check->fetch()) {
                return PollFinishStatus::FINISHED;
            }

            return PollFinishStatus::NOT_FOUND;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return PollFinishStatus::ERROR;
        }
    }

    public function getPollById(string $pollId): ?array
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            return $this->fetchPoll($pdo, $pollId);
        } catch (\PDOException $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function getPollByCode(string $code): ?array
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            // Busca o poll_id a partir do código
            $stmtCode = $pdo->prepare("SELECT poll_id FROM poll_codes WHERE code = :code LIMIT 1");
            $stmtCode->execute([':code' => $code]);
            $rowCode = $stmtCode->fetch(\PDO::FETCH_ASSOC);

            if (!$rowCode) {
                return null; // Código não encontrado
            }

            $pollId = $rowCode['poll_id'];
            return $this->fetchPoll($pdo, $pollId);
        } catch (\PDOException $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    private function fetchPoll(\PDO $pdo, string $pollId): ?array
    {
        $sql = "
            SELECT 
            p.id AS poll_id,
            p.user_id,
            u.user_name,
            p.title,
            p.description,
            p.end_time,
            TIMESTAMPDIFF(SECOND, p.start_time, COALESCE(p.end_time, NOW())) AS duration_seconds,
            p.is_finished,
            p.number_votes,
            po.id AS option_id,
            po.option_name,
            po.image_url,
            pc.code
            FROM polls p
            INNER JOIN users u ON u.user_id = p.user_id
            LEFT JOIN poll_options po ON po.poll_id = p.id
            LEFT JOIN poll_codes pc ON pc.poll_id = p.id
            WHERE p.id = :pollId
            ORDER BY p.is_finished DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pollId' => $pollId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$rows) {
            return null;
        }

        $poll = [
            'id' => (string) $rows[0]['poll_id'],
            'user' => [
                'user_id'   => $rows[0]['user_id'],
                'user_name' => $rows[0]['user_name']
            ],
            'title'        => $rows[0]['title'],
            'description'  => $rows[0]['description'] ?? null,
            'duration'     => (string) $rows[0]['duration_seconds'] . 's',
            'options'      => [],
            'urls'         => [],
            'code'         => $rows[0]['code'] ?? null,
            'end_time'     => $rows[0]['end_time'] ?? null,
            'is_finished'  => (bool) $rows[0]['is_finished'],
            'number_votes' => (int) $rows[0]['number_votes']
        ];

        foreach ($rows as $row) {
            if ($row['option_id']) {
                $poll['options'][(string)$row['option_id']] = $row['option_name'];
                if ($row['image_url']) {
                    $poll['urls'][] = $row['image_url'];
                }
            }
        }

        return $poll;
    }

    public function getAllPollsByUser(User $user): array
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.user_id, 
                p.title, 
                p.description, 
                p.start_time, 
                p.end_time, 
                p.is_finished,
                pc.code,
                pc.is_expired
            FROM polls p
            LEFT JOIN poll_codes pc 
                ON p.id = pc.poll_id
            WHERE p.user_id = :user_id
            ORDER BY p.is_finished, p.start_time DESC;
            ");

            $stmt->bindValue(':user_id', $user->getUserName(), \PDO::PARAM_STR);
            $stmt->execute();

            $polls = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $polls ?: [];
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function getPollsVotedByUser(User $user): array
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("
                SELECT DISTINCT
                    p.id,
                    p.user_id,
                    p.title,
                    p.description,
                    p.start_time,
                    p.end_time,
                    p.is_finished,
                    pc.code,
                    pc.is_expired
                FROM ledger l
                INNER JOIN polls p ON l.poll_id = p.id
                LEFT JOIN poll_codes pc ON p.id = pc.poll_id
                WHERE l.user_id = :user_id
                AND l.previous_hash != REPEAT('0',64) -- Exclui bloco gênesis
                ORDER BY p.start_time DESC
            ");

            $stmt->bindValue(':user_id', $user->getUserName(), \PDO::PARAM_STR);
            $stmt->execute();

            $polls = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $polls ?: [];
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function persistVote(Vote $vote): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            // Inicia transação
            $pdo->beginTransaction();

            // 1. Buscar último bloco da eleição
            $sql = "
            SELECT hash 
            FROM ledger 
            WHERE poll_id = :poll_id
            ORDER BY id DESC 
            LIMIT 1
        ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':poll_id' => $vote->poll_id]);
            $lastBlock = $stmt->fetch(\PDO::FETCH_ASSOC);

            // 2. Definir previous_hash
            $previousHash = $lastBlock
                ? $lastBlock['hash']
                : hash('sha256', "GENESIS-" . $vote->poll_id);

            // 3. Timestamp atual
            $timestamp = date("Y-m-d H:i:s");

            // 4. Montar dados que compõem o hash atual (usar user_id)
            $blockData = implode("|", [
                $vote->user->getUserName(),
                $vote->poll_id,
                $vote->option_id,
                $timestamp,
                $previousHash
            ]);
            $currentHash = hash('sha256', $blockData);

            // 5. Inserir no ledger
            $sql = "
            INSERT INTO ledger (user_id, poll_id, option_id, timestamp, previous_hash, hash)
            VALUES (:user_id, :poll_id, :option_id, :timestamp, :previous_hash, :hash)
        ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'       => $vote->user->getUserName(),
                ':poll_id'       => $vote->poll_id,
                ':option_id'     => $vote->option_id,
                ':timestamp'     => $timestamp,
                ':previous_hash' => $previousHash,
                ':hash'          => $currentHash,
            ]);

            // 6. Incrementar number_votes na eleição
            $updateSql = "
            UPDATE polls
            SET number_votes = number_votes + 1
            WHERE id = :poll_id
        ";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([':poll_id' => $vote->poll_id]);

            // Commit da transação
            $pdo->commit();

            return true;
        } catch (\PDOException $e) {
            // Rollback em caso de erro
            $pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }


    public function searchVote(Vote $vote): array|false
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $sql = "SELECT * 
                    FROM ledger 
                    WHERE user_id = :user_id 
                    AND poll_id = :poll_id 
                    AND previous_hash != :genesis
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":user_id"   => $vote->user->getUserName(),
                ":poll_id"   => $vote->poll_id,
                ":genesis"   => str_repeat("0", 64) // 64 zeros
            ]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result ?: false;
        } catch (\PDOException $e) {
            error_log("Erro em searchVote: " . $e->getMessage());
            return false;
        }
    }
}
