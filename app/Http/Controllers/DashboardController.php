<?php

namespace App\Http\Controllers;

use App\Support\RecordEkgService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $records = RecordEkgService::filteredQuery($request)
                ->paginate(RecordEkgService::perPage($request))
                ->withQueryString();
            $dashboard = RecordEkgService::dashboard();
            $dbError = null;
        } catch (QueryException $exception) {
            $records = collect();
            $dashboard = ['total_patients' => 0, 'completed_records' => 0, 'avg_bpm' => 0, 'latest_record' => '-', 'chart_labels' => [], 'chart_bpm' => []];
            $dbError = $exception->getMessage();
        }

        return view('dashboard.index', compact('records', 'dashboard', 'dbError'));
    }
}
