<?php

namespace Dizzi\Services;

require '../../vendor/autoload.php';

use Dizzi\Models\Vote;
use Dizzi\Repositories\PollRepository;
use Dizzi\Repositories\UserRepository;

class VoteService
{

    public static function vote(Vote $vote)
    {
        if (!self::validVote($vote, new UserRepository(), new PollRepository())) {
            return false;
        }
        return (new PollRepository())->persistVote($vote);
    }

    public static function validVote(Vote $vote, UserRepository $userRep, PollRepository $pollRep): bool
    {
        //Adicionar regra opcional de voto por CRIADOR da votação.
        if (!$userRep->existsById($vote->user->getUserName())) {
            return false;
        }

        $poll = $pollRep->getPollByCode($vote->code);
        if (!$poll) {
            return false;
        }

        if (!array_key_exists($vote->option_id, $poll["options"])) {
            return false;
        }

        if ($pollRep->searchVote($vote)) {
            return false;
        }

        return true;
    }
}
