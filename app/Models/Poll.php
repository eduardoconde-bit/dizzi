<?php

namespace Dizzi\Models;

require "../../vendor/autoload.php";

use Dizzi\Models\User;

class Poll
{
    public string  $id;
    public User    $user;
    public string  $title;
    public ?string $description;
    public string  $duration;
    public array   $options;
    public ?array  $urls;
    public ?string $code;

    public function __construct(User $user, $title, $description, $duration, $options, $urls)
    {
        $this->user = $user;

        if (empty(trim($title))) {
            throw new \InvalidArgumentException("O título é obrigatório.");
        }
        if (strlen($title) > 255) {
            throw new \InvalidArgumentException("O título não pode ter mais de 255 caracteres.");
        }
        $this->title = $title;

        // Validar description (opcional, mas limitada se presente)
        if ($description !== null) {
            if (strlen($description) > 1000) {
                throw new \InvalidArgumentException("A descrição não pode ter mais de 1000 caracteres.");
            }
            $this->description = $description;
        } else {
            $this->description = null;
        }

        // Validar duration
        // Validar duration em minutos (mínimo 1 minuto, máximo 24 horas)
        if ($duration < 1 || $duration > 1440) { // 1 a 1440 minutos
            throw new \InvalidArgumentException("A duração deve ser entre 1 e 1440 minutos (1 a 24 horas).");
        }
        
        $this->duration = $duration;
        
        $this->options = $options;
        
        $this->urls = $urls;

        return $this;
    }
}
