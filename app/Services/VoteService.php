<?php

namespace Dizzi\Services;

require '../../vendor/autoload.php';

use DateTimeZone;
use Dizzi\Models\Poll;
use Dizzi\Models\Vote;
use Dizzi\Repositories\PollRepository;
use Dizzi\Repositories\UserRepository;
use Dizzi\Repositories\PollFinishStatus;

class VoteService
{

    public static function vote(Vote $vote): array
    {
        $pollRep = new PollRepository();
        $poll = $pollRep->getPollByCode($vote->code);

        if (!$poll) {
            throw new \InvalidArgumentException("Poll not found");
        }

        if (isset($poll["user"])) {
            if ($poll["user"]["user_id"] === $vote->user->getUserName()) {
                http_response_code(400);
                return [
                    "success"     => false,
                    "error"       => [
                        "code"    => "SELF_VOTE",
                        "message" => "User cannot vote in their own poll"
                    ]
                ];
            }
        }

        if (!self::validVote($vote, new UserRepository(), $poll)) {
            http_response_code(400);
            return [
                "success"     => false,
                "error"       => [
                    "code"    => "INVALID_VOTE",
                    "message" => "Poll options diverge"
                ]
            ];
        }

        $pollFinished = false;

        // Verifica se a votação já terminou com base no tempo
        if ($poll["end_time"]) {
            $endTime = new \DateTime($poll["end_time"], new \DateTimeZone('UTC'));
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            if ($endTime < $now) {
                if ($pollRep->finishPoll($poll["id"], \DateTimeImmutable::createFromMutable($endTime)) === PollFinishStatus::ERROR) {
                    throw new \PDOException("Failed to finish poll");
                }
                $pollFinished = true;
            }
        }

        if (!empty($poll["is_finished"]) && $poll["is_finished"]) {
            $pollFinished = true;
        }

        if ($pollFinished) {
            http_response_code(400);
            return [
                "success"     => false,
                "error"       => [
                    "code"    => "POLL_FINISHED",
                    "message" => "Poll has already finished"
                ]
            ];
        }

        if ($pollRep->searchVote($vote)) {
            return [
                "success"     => false,
                "error"       => [
                    "code"    => "ALREADY_VOTED",
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
            "code"    => "VOTE_RECORDED",
            "message" => "Vote recorded successfully"
        ];
    }

    public static function validVote(Vote $vote, UserRepository $userRep, ?array $poll): bool
    {
        //Adicionar regra opcional de voto por CRIADOR da votação.
        if (!$userRep->existsById($vote->user->getUserName())) {
            return false;
        }

        if (empty($poll)) {
            return false;
        }

        if (!array_key_exists($vote->option_id, $poll["options"])) {
            return false;
        }

        return true;
    }
}
