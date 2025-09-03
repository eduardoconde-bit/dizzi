<?php

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
    public function __construct() {}

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

    public function finishPoll(string $pollCode): void
    {
        try {
            TokenService::protect();
            $finishPollService = new FinishPollService();
            echo json_encode(["success" => $finishPollService->finishPoll($pollCode, new PollRepository())]);
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
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
    }


    public function vote(?array $data): void
    {
        try {
            TokenService::protect();

            if (!isset($data)) {
                echo json_encode(["success" => false]);
                exit;
            }

            $vote = new Vote(
                new User(
                    $data["user_id"] ?? null
                ),
                $data["code"] ?? null,
                $data["poll_id"] ?? null,
                $data["option_id"] ?? null
            );

            if (!VoteService::vote($vote)) {
                echo json_encode(["error" => false]);
                exit;
            }
            echo json_encode(["success" => true]);
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
}
