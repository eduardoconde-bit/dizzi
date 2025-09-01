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

require_once('../Models/Poll.php');
require_once('../Repositories/PollRepository.php');
require_once('../Services/InitPollService.php');


class PollController
{
    public function __construct() {}

    public function initPoll(): bool
    {

        TokenService::protect();

        $data = json_decode(file_get_contents('php://input'), true);

        $poll = new Poll(
            new User(
                $data["user_id"]
            ),
            $data["title"],
            $data["description"],
            $data["duration"],
            $data["options"],
            $data["urls"]
        );

        return InitPollService::initPoll($poll, new PollRepository($poll));
    }

    public function getPoll(string $code_poll): void
    {
        TokenService::protect();

        $poll = (new PollRepository())->getPoll($code_poll);

        if (!$poll) { // se for false
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Poll não encontrada'
            ]);
            return;
        }

        // Se for array, retorna diretamente
        //header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'poll' => $poll
        ]);
    }

    public function finishPoll(string $pollCode): void
    {
        TokenService::protect();

        $finishPollService = new FinishPollService();
        echo json_encode(["success" => $finishPollService->finishPoll($pollCode, new PollRepository())]);
    }

    public function userPolls(string $user_id): void
    {
        TokenService::protect();

        $user = new User($user_id);
        $userRep = new UserRepository();

        if (!$userRep->existsById($user->getUserName())) {
            echo json_encode(["error" => "User don't exists"]);
            exit;
        }

        $userPolls = (new PollRepository())->getAllPollsByUser($user);

        //http_response_code(404);

        // Se for array, retorna diretamente
        //header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'polls' => $userPolls
        ]);
    }


    public function vote(?array $data): void
    {
        TokenService::protect();

        if (!isset($data)) {
            echo json_encode(["success" => false]);
        }

        $vote = new Vote(
            new User(
                $data["user_id"]
            ),
            $data["code"],
            $data["election_id"],
            $data["option_id"]
        );

        if (!VoteService::vote($vote)) {
            echo json_encode(["error" => false]);
            exit;
        }
        echo json_encode(["success" => true]);
    }
}
