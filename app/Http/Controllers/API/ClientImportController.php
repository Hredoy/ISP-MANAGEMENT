<?php

namespace App\Http\Controllers\API;

use App\Exports\ClientsImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\ClientsImport;
use App\Services\ClientBulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ClientImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Clients/Import');
    }

    public function template(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(new ClientsImportTemplateExport, 'clients_import_template.xlsx');
    }

    public function preview(Request $request, ClientBulkImportService $service): JsonResponse
    {
        $rows = $this->parse($request);

        return response()->json($service->process($rows, commit: false));
    }

    public function import(Request $request, ClientBulkImportService $service): JsonResponse
    {
        $rows = $this->parse($request);

        return response()->json($service->process($rows, commit: true));
    }

    private function parse(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        $sheets = Excel::toCollection(new ClientsImport, $request->file('file'));

        return $sheets->first() ?? collect();
    }
}
