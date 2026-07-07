<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Marker import used with Excel::toCollection() so uploaded rows come back
 * keyed by normalized headers (e.g. "PPPoE Username" -> "pppoe_username")
 * instead of numeric indexes.
 */
class ClientsImport implements WithHeadingRow
{
}
