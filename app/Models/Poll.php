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
        if ($duration < 1000) {
            throw new \InvalidArgumentException("Duração Mínima da Votação (1000ms) não alcançada");
        }
        
        $this->duration = $duration;
        
        $this->options = $options;
        
        if (empty($urls)) {
            throw new \InvalidArgumentException("Duração Mínima da Votação (1000ms) não alcançada");
        }
        $this->urls = $urls;

        return $this;
    }
}
