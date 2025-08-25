<?php

namespace Dizzi\Controllers;

require '../../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

use Dizzi\Models\Poll;
use Dizzi\Repositories\PollRepository;
use Dizzi\Services\InitPollService;
use Dizzi\Models\Vote;
use Dizzi\Services\VoteService;

require_once('../Models/Poll.php');
require_once('../Repositories/PollRepository.php');
require_once('../Services/InitPollService.php');


class PollController
{
    public function __construct()
    {
        
    }

    public function initPoll()
    {

        $data = json_decode(file_get_contents('php://input'), true);

        $poll = new Poll($data["title"], $data["description"], $data["duration"], $data["options"], $data["urls"]);
      
        $pollRep = new PollRepository($poll);

        return InitPollService::initPoll($poll, $pollRep);

    }

    public function getOptions(string $code_poll)
    {
        $pollRep = new PollRepository();
        echo "<pre>";
        $poll = $pollRep->getVotingOptionsByCode($code_poll);
        if($poll) {
            $poll[] = Uuid::uuid4()->toString();
            var_dump($poll);
        } else {
            var_dump($poll);
        }
    }

    public function vote(?array $data)
    {
        if (!isset($data)) {
            return false;
        }

        $vote = new Vote($data["uuid"], $data["code"], $data["election_id"], $data["option_id"]);
        if(!VoteService::vote($vote)) {
            echo "Não foi possível Salvar no Banco de Dados!";
            return false;
        }
        echo "Salvo com Sucesso!";
    }

}