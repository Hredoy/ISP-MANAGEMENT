<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientsImportTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['Name', 'Phone', 'Address', 'Package', 'PPPoE Username', 'PPPoE Password', 'ONU MAC', 'Zone'];
    }

    public function array(): array
    {
        return [
            ['John Doe', '01712345678', 'House 12, Road 4, Mirpur', '10Mbps Unlimited', 'john.doe', '', '', 'Mirpur'],
        ];
    }
}
