<?php

namespace Dizzi\Services;

require '../../vendor/autoload.php';

use Dizzi\Models\Vote;
use Dizzi\Repositories\PollRepository;

class VoteService {

    public static function vote(Vote $vote)
    {
        $pollRep = new PollRepository();
        if(!$pollRep->validVote($vote)){
            return false;
        }
        $pollRep->persistVote($vote);
        return true;
    }

}