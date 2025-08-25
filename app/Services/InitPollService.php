<?php

namespace Dizzi\Services;

require '../../vendor/autoload.php';

use Dizzi\Models\Poll;
use Dizzi\Repositories\PollRepository;

class InitPollService
{

    public static function initPoll(Poll $poll, PollRepository $pollRep): bool
    {
        return self::createGenesisPoll($pollRep->create_poll($poll), $pollRep);
    }

    public static function createGenesisPoll(string $poll_id, PollRepository $pollRep)
    {
        return $pollRep->createGenesisBlock($poll_id);
    }

}
