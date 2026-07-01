<?php

namespace App\Events;

use App\Models\Mikrotik;

class UserConnected
{
    public function __construct(public Mikrotik $mikrotik, public string $username, public ?string $address = null)
    {
        //
    }
}
