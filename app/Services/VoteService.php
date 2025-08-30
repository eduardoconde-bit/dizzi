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
        return new PollRepository()->persistVote($vote);
    }

    public static function validVote(Vote $vote, UserRepository $userRep, PollRepository $pollRep): bool
    {
        //Adicionar regra opcional de voto por CRIADOR da votação.
        $isValid = $userRep->existsById($vote->user->getUserName());
        $isValid = $pollRep->getPoll($vote->code);
        $isValid = $isValid ? in_array($vote->option_id, $isValid["options"]) : false;
        $isValid = $pollRep->searchVote($vote);
        return !((bool) $isValid);
    }
}
