<?php
declare(strict_types=1);

namespace Dizzi\Controllers;

require '../../vendor/autoload.php';

use Dizzi\Models\Poll;
use Dizzi\Models\User;
use Dizzi\Repositories\PollRepository;
use Dizzi\Services\InitPollService;
use Dizzi\Models\Vote;
use Dizzi\Repositories\UserRepository;
use Dizzi\Services\TokenService;
use Dizzi\Services\VoteService;
use Dizzi\Services\FinishPollService;
use Exception;

require_once('../Models/Poll.php');
require_once('../Repositories/PollRepository.php');
require_once('../Services/InitPollService.php');


class PollController
{
    public function __construct() {
        return $this;
    }

    public function initPoll(): void
    {

        TokenService::protect();

        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $poll = new Poll(
                new User(
                    $data["user_id"] ?? null
                ),
                $data["title"] ?? null,
                $data["description"] ?? null,
                $data["duration"] ?? null,
                $data["options"] ?? null,
                $data["urls"] ?? null
            );
            echo json_encode(["success" => InitPollService::initPoll($poll, new PollRepository($poll))]);
        } catch (\InvalidArgumentException | \TypeError $e) {
            http_response_code(400);
            echo json_encode([
            "success" => false,
            "error" => [
                "code" => "MALFORMED_REQUEST",
                "message" => "Invalid input data"
            ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
            "success" => false,
            "error" => [
                "code" => "INTERNAL_SERVER_ERROR",
                "message" => "Internal Server Error"
            ]
            ]);
        }
    }

    public function getPoll(string $code_poll): void
    {
        TokenService::protect();

        $poll = (new PollRepository())->getPollByCode($code_poll);

        if (!$poll) { // se for false
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Poll not found'
            ]);
            return;
        }

        // Se for array, retorna diretamente
        echo json_encode([
            'success' => true,
            'poll' => $poll
        ]);
    }

    public function finishPoll(string $pollId): void
    {
        try {
            TokenService::protect();
            $finishPollService = new FinishPollService();
            echo json_encode(["success" => $finishPollService->finishPoll($pollId, new PollRepository())]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        } catch (\TypeError $e) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid input data"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
    }

    public function userPolls(string $user_id): void
    {
        try {
            TokenService::protect();

            $user = new User($user_id);
            $userRep = new UserRepository();

            if (!$userRep->existsById($user->getUserName())) {
                echo json_encode(["error" => "User don't exists"]);
                exit;
            }

            $userPolls = (new PollRepository())->getAllPollsByUser($user);

            echo json_encode([
                'success' => true,
                'polls' => $userPolls
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        } catch (\TypeError $e) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid input data"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
    }

    public function userVotes(string $user_id): void
    {
        try {
            TokenService::protect();

            $user = new User($user_id);
            $userRep = new UserRepository();

            if (!$userRep->existsById($user->getUserName())) {
                echo json_encode(["error" => "User don't exists"]);
                exit;
            }

            $userVotes = (new PollRepository())->getPollsVotedByUser($user);

            echo json_encode([
                'success' => true,
                'votes' => $userVotes
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        } catch (\TypeError $e) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid input data"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
    }

    public function vote(?array $data): void
    {
        try {
            TokenService::protect();

            $vote = new Vote(
                new User(
                    $data["user_id"] ?? null
                ),
                $data["code"] ?? null,
                $data["poll_id"] ?? null,
                $data["option_id"] ?? null
            );

            echo json_encode(VoteService::vote($vote));
        } catch (\InvalidArgumentException | \TypeError $e) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => [
                    "code" => "MALFORMED_REQUEST",
                    "message" => "Invalid input data"
                ]
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => [
                    "code" => "INTERNAL_SERVER_ERROR",
                    "message" => "Internal Server Error"
                ]
            ]);
        }
    }
}
