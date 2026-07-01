<?php

namespace App\Events;

use App\Models\Mikrotik;

class RouterDisconnected
{
    public function __construct(public Mikrotik $mikrotik, public string $reason)
    {
        //
    }
}
