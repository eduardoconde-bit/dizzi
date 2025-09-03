<?php

namespace Dizzi\Services;

require '../../vendor/autoload.php';

use Dizzi\Models\Poll;
use Dizzi\Repositories\PollRepository;

class InitPollService
{

    public static function initPoll(Poll $poll, PollRepository $pollRep): bool
    {
        return $pollRep->create_poll($poll) !== false;
    }
}
