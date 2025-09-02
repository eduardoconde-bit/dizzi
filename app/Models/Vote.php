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
            throw new InvalidArgumentException("Código inválido: deve ter ao menos 3 caracteres.");
        }

        // validação de option_id (aqui supondo que seja um identificador numérico)
        if (!ctype_digit($poll_id)) {
            throw new InvalidArgumentException("Poll ID inválido: deve ser numérico.");
        }

        // validação de option_id (aqui supondo que seja um identificador numérico)
        if (!ctype_digit($option_id)) {
            throw new InvalidArgumentException("Option ID inválido: deve ser numérico.");
        }

        $this->user          = $user;
        $this->code          = $code;
        $this->poll_id       = $poll_id;
        $this->option_id     = $option_id;
    }
}
