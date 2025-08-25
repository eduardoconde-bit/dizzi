<?php

namespace Dizzi\Repositories;

use Dizzi\Repositories\IPollRepository;
use Dizzi\Models\Poll;
use Dizzi\Database\Database;
use Dizzi\Models\Vote;

require_once("IPollRepository.php");
require_once("../Database/Database.php");

class PollRepository implements IPollRepository
{

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
            INSERT INTO elections (title, description, start_time, end_time)
            VALUES (:title, :description, :start_time, :end_time)
        ");
            $stmtElection->execute([
                ':title'       => $poll->title,
                ':description' => $poll->description,
                ':start_time'  => $startTime->format('Y-m-d H:i:s'),
                ':end_time'    => $endTime->format('Y-m-d H:i:s'),
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

            echo "Poll criada com sucesso, ID {$electionId}, CODE {$code}";
            return $electionId;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException("Erro ao criar poll: " . $e->getMessage());
        }
    }


    public function createGenesisBlock($poll_id): bool
    {
        try {
            $db = new Database();
            $pdo = $db->getConnection();


            // Criar hash inicial (genesis block)
            $previousHash = str_repeat('0', 64); // 64 zeros
            $hash = hash('sha256', $poll_id . $previousHash . time());

            $stmt = $pdo->prepare("
            INSERT INTO ledger (election_id, option_id, voter_hash, previous_hash, hash) 
            VALUES (:poll_id, NULL, 'GENESIS', :previous_hash, :hash)
        ");


            $success = $stmt->execute([
                ':poll_id' => $poll_id,
                ':previous_hash' => $previousHash,
                ':hash' => $hash
            ]);

            if ($success) {
                echo "Sucesso ao criar Genesis!";
            } else {
                echo "Erro ao criar Genesis!";
            }

            return $success;
        } catch (\Exception $e) {
            // Apenas retorna false em caso de erro
            echo $e;
            return false;
        }
    }

    public function getVotingOptionsByCode(string $code): array|false
    {
        try {
            // Instancia a conexão
            $db = new Database();
            $pdo = $db->getConnection();

            // Seleciona as opções de voto da eleição correspondente ao código
            $sql = "
            SELECT 
                vo.id AS option_id,
                vo.option_name,
                vo.image_url,
                e.id AS election_id,
                e.title AS election_title,
                e.description AS election_description
            FROM voting_options vo
            INNER JOIN elections e ON e.id = vo.election_id
            INNER JOIN poll_codes pc ON pc.election_id = e.id
            WHERE pc.code = :code
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':code' => $code]);

            $options = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $options ?: false; // retorna false se não houver opções
        } catch (\PDOException $e) {
            // Aqui você pode logar o erro se quiser
            return false;
        }
    }

    public function validVote(Vote $vote): bool
    {
        try {
            // Instancia a conexão
            $db = new Database();
            $pdo = $db->getConnection();

            // Consulta para verificar se o option_id realmente pertence à eleição do code
            $sql = "
            SELECT 1
            FROM voting_options vo
            INNER JOIN elections e ON e.id = vo.election_id
            INNER JOIN poll_codes pc ON pc.election_id = e.id
            WHERE pc.code = :code
              AND vo.id = :option_id
            LIMIT 1
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':code'      => $vote->code,
                ':option_id' => $vote->option_id,
            ]);

            // Se encontrou, é válido
            return (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            // Aqui você pode logar o erro se quiser
            return false;
        }
    }

    public function persistVote(Vote $vote): bool
    {
        try {
            // Instancia a conexão
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
            $stmt->execute([
                ':election_id' => $vote->election_id,
            ]);
            $lastBlock = $stmt->fetch(\PDO::FETCH_ASSOC);

            // 2. Definir previous_hash
            $previousHash = $lastBlock
                ? $lastBlock['hash']
                : hash('sha256', "GENESIS-" . $vote->election_id);

            // 3. Timestamp atual
            $timestamp = date("Y-m-d H:i:s");

            // 4. Gerar voter_hash simulado (apenas para testes)
            $voterHash = hash('sha256', uniqid("test_voter_", true));

            // 5. Montar dados que compõem o hash atual
            $blockData = implode("|", [
                $vote->election_id,
                $vote->option_id,
                $voterHash,
                $timestamp,
                $previousHash
            ]);

            $currentHash = hash('sha256', $blockData);

            // 6. Inserir no ledger
            $sql = "
            INSERT INTO ledger (
                election_id, option_id, voter_hash, timestamp, previous_hash, hash
            ) VALUES (
                :election_id, :option_id, :voter_hash, :timestamp, :previous_hash, :hash
            )
        ";
            $stmt = $pdo->prepare($sql);

            return $stmt->execute([
                ':election_id'   => $vote->election_id,
                ':option_id'     => $vote->option_id,
                ':voter_hash'    => $voterHash,
                ':timestamp'     => $timestamp,
                ':previous_hash' => $previousHash,
                ':hash'          => $currentHash,
            ]);
        } catch (\PDOException $e) {
            // Aqui você pode logar o erro se quiser
            return false;
        }
    }
}
