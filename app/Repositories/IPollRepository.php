<?php

namespace Dizzi\Repositories;

use Dizzi\Models\Poll;

interface IPollRepository
{

    public function create_poll(Poll $poll);
}
