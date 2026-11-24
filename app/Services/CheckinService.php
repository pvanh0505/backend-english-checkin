<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckinService
{
    /** Ngày hôm nay theo múi giờ ứng dụng (APP_TIMEZONE) */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    public function parseDate(?string $date): CarbonImmutable
    {
        return $date
            ? CarbonImmutable::createFromFormat('!Y-m-d', $date, config('app.timezone'))
            : $this->today();
    }

    public function findToday(User $user): ?Checkin
    {
        return $user->checkins()
            ->where('checkin_date', $this->today()->toDateString())
            ->with('sentences')
            ->first();
    }

    /**
     * Tạo mới hoặc cập nhật check-in của HÔM NAY.
     * Không cho phép check-in bù cho ngày đã qua.
     *
     * @return array{0: Checkin, 1: bool} [checkin, wasCreated]
     */
    public function saveToday(User $user, array $data): array
    {
        $today = $this->today();

        return DB::transaction(function () use ($user, $data, $today) {
            $sentences = array_values($data['sentences']);

            $checkin = Checkin::query()
                ->where('user_id', $user->id)
                ->where('checkin_date', $today->toDateString())
                ->lockForUpdate()
                ->first();

            $created = $checkin === null;
            $checkin ??= new Checkin([
                'user_id' => $user->id,
                'checkin_date' => $today->toDateString(),
                'learned_date' => $today->subDay()->toDateString(),
            ]);

            $checkin->note = $data['note'] ?? null;
            $checkin->sentence_count = count($sentences);
            $checkin->save();

            // Thay toàn bộ danh sách câu bằng danh sách mới
            $checkin->sentences()->delete();
            $checkin->sentences()->createMany(
                collect($sentences)->map(fn (array $s, int $i) => [
                    'content' => $s['content'],
                    'meaning' => $s['meaning'] ?? null,
                    'note' => $s['note'] ?? null,
                    'position' => $i,
                ])->all()
            );

            return [$checkin->load('sentences'), $created];
        });
    }

    /**
     * Chuỗi ngày check-in liên tiếp tính đến $asOf.
     * Nếu $asOf chưa check-in thì vẫn giữ chuỗi tính tới hôm trước
     * (người dùng còn thời gian trong ngày để check-in).
     *
     * @param  array<int, string>  $dates  danh sách ngày 'Y-m-d'
     */
    public function currentStreak(array $dates, CarbonImmutable $asOf): int
    {
        $set = array_flip($dates);
        $day = isset($set[$asOf->toDateString()]) ? $asOf : $asOf->subDay();

        $streak = 0;
        while (isset($set[$day->toDateString()])) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /** @param  array<int, string>  $dates */
    public function longestStreak(array $dates): int
    {
        $dates = array_values(array_unique($dates));
        sort($dates);

        $longest = 0;
        $run = 0;
        $prev = null;
        foreach ($dates as $date) {
            $d = CarbonImmutable::createFromFormat('!Y-m-d', $date);
            $run = ($prev && $prev->addDay()->equalTo($d)) ? $run + 1 : 1;
            $longest = max($longest, $run);
            $prev = $d;
        }

        return $longest;
    }

    /** Thống kê tổng hợp của 1 người dùng */
    public function statsFor(User $user, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= $this->today();
        $dates = $user->checkins()
            ->where('checkin_date', '<=', $asOf->toDateString())
            ->pluck('checkin_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->all();

        return [
            'checked_in_today' => in_array($this->today()->toDateString(), $dates, true),
            'current_streak' => $this->currentStreak($dates, $asOf),
            'longest_streak' => $this->longestStreak($dates),
            'total_checkins' => count($dates),
            'total_sentences' => (int) $user->checkins()->sum('sentence_count'),
        ];
    }

    /**
     * Báo cáo check-in của tất cả người học đang hoạt động cho 1 ngày.
     *
     * @return Collection<int, array>
     */
    public function dailyReport(CarbonImmutable $date, ?string $search = null): Collection
    {
        $learners = User::query()
            ->activeLearners()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $ids = $learners->pluck('id');

        $checkins = Checkin::query()
            ->whereIn('user_id', $ids)
            ->where('checkin_date', $date->toDateString())
            ->with('sentences')
            ->get()
            ->keyBy('user_id');

        // Lấy ngày check-in trong khoảng lookback để tính streak cho từng người (1 query)
        $lookback = $date->subDays(config('checkin.streak_lookback_days'))->toDateString();
        $datesByUser = Checkin::query()
            ->whereIn('user_id', $ids)
            ->whereBetween('checkin_date', [$lookback, $date->toDateString()])
            ->get(['user_id', 'checkin_date'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => substr((string) $r->checkin_date, 0, 10))->all());

        return $learners->map(function (User $u) use ($checkins, $datesByUser, $date) {
            $c = $checkins->get($u->id);

            return [
                'user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email],
                'checked_in' => $c !== null,
                'checked_in_at' => $c?->created_at?->toIso8601String(),
                'updated_at' => $c?->updated_at?->toIso8601String(),
                'sentence_count' => $c?->sentence_count ?? 0,
                'sentences' => $c ? $c->sentences->map->only(['content', 'meaning', 'note'])->values() : [],
                'note' => $c?->note,
                'current_streak' => $this->currentStreak($datesByUser->get($u->id, []), $date),
            ];
        });
    }

    /** Số lượt check-in mỗi ngày trong $days ngày kết thúc tại $end */
    public function trend(CarbonImmutable $end, int $days = 7): array
    {
        $start = $end->subDays($days - 1);
        $totalLearners = User::activeLearners()->count();

        $counts = Checkin::query()
            ->whereBetween('checkin_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('user', fn ($q) => $q->activeLearners())
            ->selectRaw('checkin_date, COUNT(*) as total')
            ->groupBy('checkin_date')
            ->pluck('total', 'checkin_date')
            ->mapWithKeys(fn ($v, $k) => [substr((string) $k, 0, 10) => (int) $v]);

        $result = [];
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            $key = $d->toDateString();
            $checked = $counts->get($key, 0);
            $result[] = [
                'date' => $key,
                'checked_in' => $checked,
                'rate' => $totalLearners ? round($checked * 100 / $totalLearners, 1) : 0,
            ];
        }

        return $result;
    }
}
