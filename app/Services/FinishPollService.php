<?php

namespace Dizzi\Services;

require '../../vendor/autoload.php';

use Dizzi\Models\Poll;
use Dizzi\Repositories\PollRepository;
use Dizzi\Repositories\PollFinishStatus;

class FinishPollService
{
    /**
     * Finish a poll by setting its status to finished and performing any necessary cleanup.
     *
     * @param string $pollId
     * @return boolean
     */
    public static function finishPoll(string $pollId, PollRepository $pollRep):bool
    {
        $poll = $pollRep->getPoll($pollId);
        if($poll && $poll["user"]["user_id"] === $GLOBALS["auth_user"]) {
            return $pollRep->finishPoll($pollId) === PollFinishStatus::FINISHED;
        }
        return false;
    }  
}