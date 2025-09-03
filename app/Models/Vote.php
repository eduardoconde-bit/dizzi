<?php

namespace Dizzi\Models;

require "../../vendor/autoload.php";

use InvalidArgumentException;
use Dizzi\Models\User;

class Vote
{
    public User $user;
    public string $code;
    public string $poll_id;
    public string $option_id;

    public function __construct(User $user, string $code, string $poll_id, string $option_id)
    {

        // validação de código (exemplo: pelo menos 3 chars, ajusta conforme sua regra)
        if (empty(trim($code)) || strlen($code) < 3) {
            throw new InvalidArgumentException("Invalid code: must be at least 3 characters long.");
        }

        // validação de option_id (aqui supondo que seja um identificador numérico)
        if (!ctype_digit($poll_id)) {
            throw new InvalidArgumentException("Invalid Poll ID: must be numeric.");
        }

        // validação de option_id (aqui supondo que seja um identificador numérico)
        if (!ctype_digit($option_id)) {
            throw new InvalidArgumentException("Invalid Option ID: must be numeric.");
        }

        $this->user          = $user;
        $this->code          = $code;
        $this->poll_id       = $poll_id;
        $this->option_id     = $option_id;
    }
}
