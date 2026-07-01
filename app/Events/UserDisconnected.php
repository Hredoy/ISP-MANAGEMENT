<?php

namespace App\Events;

use App\Models\Mikrotik;

class UserDisconnected
{
    public function __construct(public Mikrotik $mikrotik, public string $username)
    {
        //
    }
}
