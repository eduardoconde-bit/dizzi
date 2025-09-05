<?php

namespace Dizzi\Services;

require '../../vendor/autoload.php';

use Dizzi\Models\Poll;
use Dizzi\Models\Vote;
use Dizzi\Repositories\PollRepository;
use Dizzi\Repositories\UserRepository;

class VoteService
{

    public static function vote(Vote $vote): array
    {
        $pollRep = new PollRepository();
        $poll = $pollRep->getPollByCode($vote->code);
        
        if (!self::validVote($vote, new UserRepository(), $poll)) {
            http_response_code(400);
            return [
                "success" => false,
                "error" => [
                    "code" => "INVALID_VOTE",
                    "message" => "Poll options diverge"
                ]
            ];
        }
        
        if($poll['is_finished']) {
            http_response_code(400);
            return [
                "success" => false,
                "error" => [
                    "code" => "POLL_FINISHED",
                    "message" => "Poll is finished"
                ]
            ];

        }

        if ($pollRep->searchVote($vote)) {
            return [
                "success" => false,
                "error" => [
                    "code" => "ALREADY_VOTED",
                    "message" => "User has already voted in this poll"
                ]
            ];
        }

        if (!(new PollRepository())->persistVote($vote)) {
            throw new \PDOException("Failed to persist vote");
        }
        http_response_code(201);
        return [
            "success" => true,
            "code" => "VOTE_RECORDED",
            "message" => "Vote recorded successfully"
        ];
    }

    public static function validVote(Vote $vote, UserRepository $userRep, array $poll): bool
    {
        //Adicionar regra opcional de voto por CRIADOR da votação.
        if (!$userRep->existsById($vote->user->getUserName())) {
            return false;
        }

        if (empty($poll)) {
            echo "Oi poll vazio!";
            return false;
        }

        if (!array_key_exists($vote->option_id, $poll["options"])) {
            return false;
        }

        return true;
    }
}
