<?php

namespace Database\Seeders;

use App\Models\Checkin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Quản trị viên',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $learners = collect([
            ['Nguyễn Văn An', 'an@example.com'],
            ['Trần Thị Bình', 'binh@example.com'],
            ['Lê Minh Châu', 'chau@example.com'],
            ['Phạm Quốc Dũng', 'dung@example.com'],
            ['Hoàng Thu Hà', 'ha@example.com'],
        ])->map(fn ($u) => User::updateOrCreate(['email' => $u[1]], [
            'name' => $u[0],
            'password' => 'password',
            'role' => User::ROLE_LEARNER,
        ]));

        $samples = [
            ['Could you give me a hand with this?', 'Bạn giúp tôi việc này được không?'],
            ["I'm looking forward to seeing you.", 'Tôi rất mong được gặp bạn.'],
            ['It depends on the weather.', 'Còn tuỳ vào thời tiết.'],
            ["Let's call it a day.", 'Hôm nay làm đến đây thôi.'],
            ["I'll get back to you later.", 'Tôi sẽ phản hồi bạn sau.'],
            ['That makes sense.', 'Điều đó hợp lý.'],
            ['Never mind.', 'Không sao đâu.'],
            ['How have you been?', 'Dạo này bạn thế nào?'],
        ];

        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        // Dữ liệu mẫu 14 ngày gần nhất (không gồm hôm nay)
        foreach ($learners as $index => $learner) {
            for ($i = 14; $i >= 1; $i--) {
                if (random_int(0, 100) < 25 + $index * 10) {
                    continue; // bỏ ngày ngẫu nhiên để có dữ liệu "chưa check-in"
                }
                $date = $today->subDays($i);
                $picked = collect($samples)->shuffle()->take(random_int(3, 5))->values();

                $checkin = Checkin::updateOrCreate(
                    ['user_id' => $learner->id, 'checkin_date' => $date->toDateString()],
                    ['learned_date' => $date->subDay()->toDateString(), 'sentence_count' => $picked->count()]
                );
                $checkin->sentences()->delete();
                $checkin->sentences()->createMany($picked->map(fn ($s, $pos) => [
                    'content' => $s[0], 'meaning' => $s[1], 'position' => $pos,
                ])->all());
            }
        }
    }
}
