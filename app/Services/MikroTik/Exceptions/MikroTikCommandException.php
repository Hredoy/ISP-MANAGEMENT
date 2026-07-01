<?php

namespace App\Services\MikroTik\Exceptions;

/**
 * Router reachable and authenticated, but the command itself was rejected
 * (duplicate secret name, protected system profile, unknown target, ...).
 */
class MikroTikCommandException extends MikroTikException
{
    //
}
