<?php

namespace DantePiazza\LaravelAuth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly mixed $model,
        public readonly array $extraFields = [],
    ) {}
}
