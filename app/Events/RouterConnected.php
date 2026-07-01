<?php

namespace App\Events;

use App\Models\Mikrotik;

class RouterConnected
{
    public function __construct(public Mikrotik $mikrotik)
    {
        //
    }
}
