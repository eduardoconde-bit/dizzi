<?php

namespace Dizzi\Repositories;

use Dizzi\Repositories\IPollRepository;
use Dizzi\Models\Poll;
use Dizzi\Database\Database;
use Dizzi\Models\User;
use Dizzi\Models\Vote;

require_once("IPollRepository.php");
require_once("../Database/Database.php");

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

            // Insere na tabela elections
            $stmtElection = $pdo->prepare("
            INSERT INTO elections (user_id, title, description, start_time, end_time)
            VALUES (:user_id, :title, :description, :start_time, :end_time)
        ");
            $stmtElection->execute([
                ':user_id'       => $poll->user->getUserName(),
                ':title'         => $poll->title,
                ':description'   => $poll->description,
                ':start_time'    => $startTime->format('Y-m-d H:i:s'),
                ':end_time'      => $endTime->format('Y-m-d H:i:s'),
            ]);

            $electionId = $pdo->lastInsertId();

            // Insere opções
            $stmtOption = $pdo->prepare("
            INSERT INTO voting_options (election_id, option_name, image_url)
            VALUES (:election_id, :option_name, :image_url)
        ");

            foreach ($poll->options as $i => $option) {
                $optionName = is_array($option) ? $option['name'] : (string)$option;
                $imageUrl   = $poll->urls[$i] ?? null;

                $stmtOption->execute([
                    ':election_id' => $electionId,
                    ':option_name' => $optionName,
                    ':image_url'   => $imageUrl,
                ]);
            }

            // Gerar código único da poll
            $code = bin2hex(random_bytes(8)); // exemplo: 16 caracteres hex

            $stmtCode = $pdo->prepare("
            INSERT INTO poll_codes (election_id, code)
            VALUES (:election_id, :code)
        ");
            $stmtCode->execute([
                ':election_id' => $electionId,
                ':code'        => $code,
            ]);

            $pdo->commit();

            //echo "Poll criada com sucesso, ID {$electionId}, CODE {$code}";
            return $electionId;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException("Erro ao criar poll: " . $e->getMessage());
        }
    }


    public function createGenesisBlock(int $poll_id): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            // Busca o user_id dono da eleição
            $stmt = $pdo->prepare("SELECT user_id FROM elections WHERE id = :poll_id");
            $stmt->execute([':poll_id' => $poll_id]);
            $election = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$election) {
                throw new \RuntimeException("Eleição não encontrada: $poll_id");
            }

            $userId = $election['user_id'];

            // Hash inicial do genesis block
            $previousHash = str_repeat('0', 64); // 64 zeros
            $hash = hash('sha256', $userId . $poll_id . $previousHash . time());

            // Inserir genesis block seguindo a ordem física da tabela
            $stmt = $pdo->prepare("
            INSERT INTO ledger (user_id, election_id, option_id, previous_hash, hash)
            VALUES (:user_id, :poll_id, :option_id, :previous_hash, :hash)
        ");

            $success = $stmt->execute([
                ':user_id' => $userId,
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



    public function getPoll(string $code): array|false
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            // Seleciona eleição, dono (somente user_id), opções e URLs associadas ao código
            $sql = "
            SELECT 
                e.id AS election_id,
                e.user_id,
                e.title,
                e.description,
                TIMESTAMPDIFF(SECOND, e.start_time, COALESCE(e.end_time, NOW())) AS duration_seconds,
                vo.id AS option_id,
                vo.option_name,
                vo.image_url
            FROM elections e
            INNER JOIN poll_codes pc ON pc.election_id = e.id
            LEFT JOIN voting_options vo ON vo.election_id = e.id
            WHERE pc.code = :code
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':code' => $code]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!$rows) {
                return false; // não encontrou eleição
            }

            // Monta o objeto agregado
            $poll = [
                'id' => (string) $rows[0]['election_id'],
                'user' => [
                    'user_id' => $rows[0]['user_id']
                ],
                'title' => $rows[0]['title'],
                'description' => $rows[0]['description'] ?? null,
                'duration' => (string) $rows[0]['duration_seconds'] . 's',
                'options' => [],
                'urls' => [],
                'code' => $code
            ];

            foreach ($rows as $row) {
                if ($row['option_id']) {
                    // options no formato id => option_name
                    $poll['options'][(string)$row['option_id']] = $row['option_name'];

                    // urls num array separado
                    if ($row['image_url']) {
                        $poll['urls'][] = $row['image_url'];
                    }
                }
            }

            // Remove duplicados das URLs
            //$poll['urls'] = array_values(array_unique($poll['urls']));

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
            SELECT id, user_id, title, description, start_time, end_time
            FROM elections
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
