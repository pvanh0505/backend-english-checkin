<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCheckinRequest;
use App\Http\Resources\CheckinResource;
use App\Services\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckinController extends Controller
{
    public function __construct(private readonly CheckinService $service) {}

    /** GET /api/checkins/today – trạng thái check-in hôm nay */
    public function today(Request $request): JsonResponse
    {
        $today = $this->service->today();
        $checkin = $this->service->findToday($request->user());

        return response()->json([
            'checkin_date' => $today->toDateString(),
            'learned_date' => $today->subDay()->toDateString(),
            'min_sentences' => config('checkin.min_sentences'),
            'max_sentences' => config('checkin.max_sentences'),
            'checkin' => $checkin ? new CheckinResource($checkin) : null,
        ]);
    }

    /** PUT /api/checkins/today – tạo/cập nhật check-in hôm nay */
    public function saveToday(SaveCheckinRequest $request): JsonResponse
    {
        [$checkin, $created] = $this->service->saveToday($request->user(), $request->validated());

        return (new CheckinResource($checkin))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    /** GET /api/checkins?month=2026-11 – lịch sử check-in của tôi */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $checkins = $request->user()->checkins()
            ->when($request->month, fn ($q, $m) => $q->where('checkin_date', 'like', "{$m}-%"))
            ->with('sentences')
            ->orderByDesc('checkin_date')
            ->paginate(31);

        return CheckinResource::collection($checkins);
    }

    /** GET /api/checkins/stats – streak, tổng số câu... */
    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->service->statsFor($request->user()));
    }
}
