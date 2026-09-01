<?php

namespace App\Http\Controllers;

use App\Exports\AuditLogExport;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AuditLogController extends Controller
{
    /**
     * Ambil filters yang sama dari request — dipakai oleh index() dan export()
     */
    private function buildQuery(Request $request)
    {
        $query = AuditLog::with('user')->latest();

        if ($request->action)     $query->where('action', $request->action);
        if ($request->module)     $query->where('module', $request->module);
        if ($request->user_id)    $query->where('user_id', $request->user_id);
        if ($request->start_date) $query->whereDate('created_at', '>=', $request->start_date);
        if ($request->end_date)   $query->whereDate('created_at', '<=', $request->end_date);
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', '%' . $request->search . '%')
                  ->orWhere('action', 'like', '%' . $request->search . '%');
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $logs    = $this->buildQuery($request)->paginate(50)->withQueryString();
        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $modules = AuditLog::select('module')->distinct()->orderBy('module')->pluck('module');

        return view('audit.index', compact('logs', 'actions', 'modules'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'format'     => 'nullable|in:xlsx,csv',
        ]);

        $format     = $request->format ?? 'xlsx';
        $schoolName = \DB::table('school_settings')->where('key', 'school_name')->value('value') ?? 'Sekolah';

        $filters = $request->only(['action', 'module', 'search', 'start_date', 'end_date']);

        $start    = $request->start_date ?? 'semua';
        $end      = $request->end_date   ?? 'semua';
        $fileName = 'audit-trail_' . str_replace('/', '-', $start) . '_sd_' . str_replace('/', '-', $end) . '.' . $format;

        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;

        return \Maatwebsite\Excel\Facades\Excel::download(
            new AuditLogExport($filters, $schoolName),
            $fileName,
            $writerType
        );
    }
}
