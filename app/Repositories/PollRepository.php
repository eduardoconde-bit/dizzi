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
            $startTime = new \DateTimeImmutable();

            // Converte duration de ms -> segundos
            $durationSeconds = (int) ($poll->duration / 1000);

            // Calcula o end_time
            $endTime = $startTime->modify("+{$durationSeconds} seconds");

            // Insere na tabela polls
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
                    ':poll_id'    => $pollId,
                    ':option_name'=> $optionName,
                    ':image_url'  => $imageUrl,
                ]);
            }

            // Gerar código único da poll
            $code = bin2hex(random_bytes(8)); // exemplo: 16 caracteres hex

            $stmtCode = $pdo->prepare("
                INSERT INTO poll_codes (poll_id, code)
                VALUES (:poll_id, :code)
            ");
            $stmtCode->execute([
                ':poll_id' => $pollId,
                ':code'    => $code,
            ]);

            $pdo->commit();

            return $pollId;
        } catch (\Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro ao criar poll: " . $e->getMessage());
            return false;
        }
    }

     public function finishPoll(string $pollId): PollFinishStatus
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("UPDATE polls SET is_finished = 1 WHERE id = :pollId");
            $stmt->execute([':pollId' => $pollId]);

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

    public function createGenesisBlock(string $poll_id): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            // Busca o user_id da tabela polls usando o poll_id
            $stmt = $pdo->prepare("
                SELECT user_id
                FROM polls
                WHERE id = :poll_id
            ");
            $stmt->execute([':poll_id' => $poll_id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                throw new \RuntimeException("user_id for this poll not found");
            }
            $user_id = $row['user_id'];

            // Hash inicial do genesis block
            $previousHash = str_repeat('0', 64); // 64 zeros
            $hash = hash('sha256', $user_id . $poll_id . $previousHash . time());

            // Inserir genesis block na ledger (poll_id aqui é o poll_code)
            $stmt = $pdo->prepare("
                INSERT INTO ledger (user_id, poll_id, option_id, previous_hash, hash)
                VALUES (:user_id, :poll_id, :option_id, :previous_hash, :hash)
            ");

            $success = $stmt->execute([
                ':user_id' => $user_id,
                ':poll_id' => $poll_id, 
                ':option_id' => null,
                ':previous_hash' => $previousHash,
                ':hash' => $hash
            ]);

            return $success;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }



    public function getPoll(string $pollIdOrCode): array|false
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            // Se for código, busca o poll_id correspondente
            if (!ctype_digit($pollIdOrCode)) {
                $stmtCode = $pdo->prepare   ("SELECT poll_id FROM poll_codes WHERE code = :code LIMIT 1");
                $stmtCode->execute([':code' => $pollIdOrCode]);
                $rowCode = $stmtCode->fetch(\PDO::FETCH_ASSOC);

                if (!$rowCode) {
                    return false; // Código não encontrado
                }
                $pollId = $rowCode['poll_id'];
            } else {
                $pollId = $pollIdOrCode;
            }

            // Busca os dados da poll, user, opções e code
            $sql = "
                SELECT 
                    p.id AS poll_id,
                    p.user_id,
                    u.user_name,
                    p.title,
                    p.description,
                    TIMESTAMPDIFF(SECOND, p.start_time, COALESCE(p.end_time, NOW())) AS duration_seconds,
                    po.id AS option_id,
                    po.option_name,
                    po.image_url,
                    pc.code
                FROM polls p
                INNER JOIN users u ON u.user_id = p.user_id
                LEFT JOIN poll_options po ON po.poll_id = p.id
                LEFT JOIN poll_codes pc ON pc.poll_id = p.id
                WHERE p.id = :pollId
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':pollId' => $pollId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!$rows) {
                return false;
            }

            $poll = [
                'id' => (string) $rows[0]['poll_id'],
                'user' => [
                    'user_id' => $rows[0]['user_id'],
                    'user_name' => $rows[0]['user_name']
                ],
                'title' => $rows[0]['title'],
                'description' => $rows[0]['description'] ?? null,
                'duration' => (string) $rows[0]['duration_seconds'] . 's',
                'options' => [],
                'urls' => [],
                'code' => $rows[0]['code'] ?? null
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
        } catch (\PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function getAllPollsByUser(User $user): array
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("
            SELECT id, user_id, title, description, start_time, end_time, is_finished
            FROM polls
            WHERE user_id = :user_id
            ORDER BY start_time DESC
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

            // 1. Buscar último bloco da eleição
            $sql = "
            SELECT hash 
            FROM ledger 
            WHERE election_id = :election_id 
            ORDER BY id DESC 
            LIMIT 1
        ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':election_id' => $vote->election_id]);
            $lastBlock = $stmt->fetch(\PDO::FETCH_ASSOC);

            // 2. Definir previous_hash
            $previousHash = $lastBlock
                ? $lastBlock['hash']
                : hash('sha256', "GENESIS-" . $vote->election_id);

            // 3. Timestamp atual
            $timestamp = date("Y-m-d H:i:s");

            // 4. Montar dados que compõem o hash atual
            $blockData = implode("|", [
                $vote->user->getUserName(), // <- usar user_id, não nome
                $vote->election_id,
                $vote->option_id,
                $timestamp,
                $previousHash
            ]);

            $currentHash = hash('sha256', $blockData);

            // 5. Inserir no ledger seguindo a ordem física da tabela
            $sql = "
            INSERT INTO ledger (user_id, election_id, option_id, timestamp, previous_hash, hash)
            VALUES (:user_id, :election_id, :option_id, :timestamp, :previous_hash, :hash)
        ";
            $stmt = $pdo->prepare($sql);

            return $stmt->execute([
                ':user_id'       => $vote->user->getUserName(),
                ':election_id'   => $vote->election_id,
                ':option_id'     => $vote->option_id,
                ':timestamp'     => $timestamp,
                ':previous_hash' => $previousHash,
                ':hash'          => $currentHash,
            ]);
        } catch (\PDOException $e) {
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
                    AND election_id = :election_id 
                    AND previous_hash != :genesis
                    LIMIT 1";


            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":user_id"   => $vote->user->getUserName(),
                ":election_id"   => $vote->election_id,
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
