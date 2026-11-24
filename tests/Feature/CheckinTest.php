<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Ho_Chi_Minh', 'checkin.min_sentences' => 3]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-21 09:00', 'Asia/Ho_Chi_Minh'));
    }

    private function learner(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role' => User::ROLE_LEARNER]);
    }

    private function sentences(int $n): array
    {
        return collect(range(1, $n))->map(fn ($i) => ['content' => "Sentence number {$i}.", 'meaning' => "Câu {$i}"])->all();
    }

    public function test_learner_can_check_in_with_yesterday_sentences(): void
    {
        Sanctum::actingAs($user = $this->learner());

        $this->putJson('/api/checkins/today', ['sentences' => $this->sentences(3)])
            ->assertCreated()
            ->assertJsonPath('data.checkin_date', '2026-11-21')
            ->assertJsonPath('data.learned_date', '2026-11-20')
            ->assertJsonPath('data.sentence_count', 3);

        $this->assertDatabaseCount('checkin_sentences', 3);
    }

    public function test_check_in_requires_minimum_sentences_and_ignores_blank_rows(): void
    {
        Sanctum::actingAs($this->learner());

        $payload = ['sentences' => [...$this->sentences(2), ['content' => '   ']]];

        $this->putJson('/api/checkins/today', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sentences');
    }

    public function test_second_check_in_same_day_updates_instead_of_duplicating(): void
    {
        Sanctum::actingAs($this->learner());

        $this->putJson('/api/checkins/today', ['sentences' => $this->sentences(3)])->assertCreated();
        $this->putJson('/api/checkins/today', ['sentences' => $this->sentences(5)])->assertOk()
            ->assertJsonPath('data.sentence_count', 5);

        $this->assertDatabaseCount('checkins', 1);
        $this->assertDatabaseCount('checkin_sentences', 5);
    }

    public function test_duplicate_sentences_are_rejected(): void
    {
        Sanctum::actingAs($this->learner());

        $s = [['content' => 'Hello there.'], ['content' => 'hello there.'], ['content' => 'Bye.']];

        $this->putJson('/api/checkins/today', ['sentences' => $s])
            ->assertUnprocessable();
    }

    public function test_streak_is_counted_from_consecutive_days(): void
    {
        Sanctum::actingAs($user = $this->learner());

        foreach (['2026-11-18', '2026-11-19', '2026-11-20'] as $d) {
            Checkin::create(['user_id' => $user->id, 'checkin_date' => $d, 'learned_date' => $d, 'sentence_count' => 3]);
        }

        // chưa check-in hôm nay vẫn giữ streak 3
        $this->getJson('/api/checkins/stats')->assertJsonPath('current_streak', 3)
            ->assertJsonPath('checked_in_today', false);

        $this->putJson('/api/checkins/today', ['sentences' => $this->sentences(3)]);
        $this->getJson('/api/checkins/stats')->assertJsonPath('current_streak', 4);
    }

    public function test_learner_cannot_access_admin(): void
    {
        Sanctum::actingAs($this->learner());

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_admin_dashboard_lists_checked_and_missing_learners(): void
    {
        $done = $this->learner(['name' => 'A Done']);
        $this->learner(['name' => 'B Missing']);
        $this->learner(['name' => 'C Locked', 'is_active' => false]);

        Checkin::create(['user_id' => $done->id, 'checkin_date' => '2026-11-21', 'learned_date' => '2026-11-20', 'sentence_count' => 3]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.total_learners', 2)
            ->assertJsonPath('summary.checked_in', 1)
            ->assertJsonPath('summary.missing', 1);

        $this->getJson('/api/admin/dashboard?status=missing')
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.user.name', 'B Missing');
    }
}
