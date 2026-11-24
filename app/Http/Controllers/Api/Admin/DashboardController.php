<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly CheckinService $service) {}

    /**
     * GET /api/admin/dashboard?date=2026-11-21&status=missing&search=an
     * Ai đã check-in / chưa check-in trong ngày được chọn.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->validateFilters($request);
        $date = $this->service->parseDate($data['date'] ?? null);
        $status = $data['status'] ?? 'all';

        $rows = $this->service->dailyReport($date, $data['search'] ?? null);

        $total = $rows->count();
        $checked = $rows->where('checked_in', true)->count();

        $filtered = match ($status) {
            'checked' => $rows->where('checked_in', true),
            'missing' => $rows->where('checked_in', false),
            default => $rows,
        };

        return response()->json([
            'date' => $date->toDateString(),
            'learned_date' => $date->subDay()->toDateString(),
            'is_today' => $date->equalTo($this->service->today()),
            'summary' => [
                'total_learners' => $total,
                'checked_in' => $checked,
                'missing' => $total - $checked,
                'rate' => $total ? round($checked * 100 / $total, 1) : 0,
                'total_sentences' => $rows->sum('sentence_count'),
            ],
            'trend' => $this->service->trend($date, 7),
            'rows' => $filtered->values(),
        ]);
    }

    /** GET /api/admin/dashboard/export?date=... – xuất CSV (mở được bằng Excel) */
    public function export(Request $request): StreamedResponse
    {
        $data = $this->validateFilters($request);
        $date = $this->service->parseDate($data['date'] ?? null);
        $rows = $this->service->dailyReport($date, $data['search'] ?? null);

        $filename = "checkin-{$date->toDateString()}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM cho Excel
            fputcsv($out, ['Họ tên', 'Email', 'Trạng thái', 'Giờ check-in', 'Số câu', 'Streak', 'Các câu đã học']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['user']['name'],
                    $r['user']['email'],
                    $r['checked_in'] ? 'Đã check-in' : 'Chưa check-in',
                    $r['checked_in_at'] ?? '',
                    $r['sentence_count'],
                    $r['current_streak'],
                    collect($r['sentences'])->pluck('content')->implode(' | '),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status' => ['nullable', 'in:all,checked,missing'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
