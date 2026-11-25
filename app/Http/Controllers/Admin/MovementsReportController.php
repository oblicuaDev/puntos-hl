<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\PointMovementsReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovementsReportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'desde' => 'required|date_format:Y-m-d',
            'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde',
        ], [
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        ]);

        $report = new PointMovementsReport(
            Carbon::createFromFormat('Y-m-d', $data['desde'])->startOfDay(),
            Carbon::createFromFormat('Y-m-d', $data['hasta'])->endOfDay(),
        );

        return response()->streamDownload(
            fn () => $report->writeTo('php://output'),
            $report->filename(),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
