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
            throw new \InvalidArgumentException("Title is required.");
        }
        if (strlen($title) > 255) {
            throw new \InvalidArgumentException("Title cannot be longer than 255 characters.");
        }
        $this->title = $title;

        // Validate description (optional, but limited if present)
        if ($description !== null) {
            if (strlen($description) > 1000) {
                throw new \InvalidArgumentException("Description cannot be longer than 1000 characters.");
            }
            $this->description = $description;
        } else {
            $this->description = null;
        }

        // Validate duration in minutes (minimum 1 minute, maximum 24 hours)
        if ($duration < 1 || $duration > 1440) { // 1 to 1440 minutes
            throw new \InvalidArgumentException("Duration must be between 1 and 1440 minutes (1 to 24 hours).");
        }

        if (!empty($options)) {
            if (count($options) < 2) {
            throw new \InvalidArgumentException("At least two options are required.");
            }
            if (count($options) > 10) {
            throw new \InvalidArgumentException("A maximum of ten options are allowed.");
            }
            foreach ($options as $option) {
            if (empty(trim($option))) {
                throw new \InvalidArgumentException("Options cannot be empty.");
            }
            if (strlen($option) > 100) {
                throw new \InvalidArgumentException("Each option cannot be longer than 100 characters.");
            }
            }
        } else {
            throw new \InvalidArgumentException("Options are required.");
        }
        
        $this->duration = $duration;
        
        $this->options = $options;
        
        $this->urls = $urls;

        return $this;
    }
}
