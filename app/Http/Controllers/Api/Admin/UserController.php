<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckinResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CheckinService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private readonly CheckinService $service) {}

    /** GET /api/admin/users?search=&role=&page= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in([User::ROLE_ADMIN, User::ROLE_LEARNER])],
        ]);

        $users = User::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->withCount('checkins')
            ->withMax('checkins', 'checkin_date')
            ->orderBy('name')
            ->paginate(20);

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_LEARNER])],
            'is_active' => ['boolean'],
        ]);

        $user = User::create($data + ['is_active' => true]);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user),
            'stats' => $this->service->statsFor($user),
        ]);
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in([User::ROLE_ADMIN, User::ROLE_LEARNER])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($user->is($request->user()) && (($data['is_active'] ?? true) === false || ($data['role'] ?? User::ROLE_ADMIN) !== User::ROLE_ADMIN)) {
            throw ValidationException::withMessages(['is_active' => 'Bạn không thể tự khoá hoặc bỏ quyền admin của chính mình.']);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        // Khoá tài khoản => thu hồi mọi token đang đăng nhập
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $user->tokens()->delete();
        }

        return new UserResource($user);
    }

    /** GET /api/admin/users/{user}/checkins?month=2026-11 – lịch check-in theo tháng */
    public function checkins(Request $request, User $user): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $month = $request->month
            ? CarbonImmutable::createFromFormat('!Y-m', $request->month, config('app.timezone'))
            : $this->service->today()->startOfMonth();

        $checkins = $user->checkins()
            ->whereBetween('checkin_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->with('sentences')
            ->orderBy('checkin_date')
            ->get();

        return response()->json([
            'user' => new UserResource($user),
            'month' => $month->format('Y-m'),
            'stats' => $this->service->statsFor($user),
            'checkins' => CheckinResource::collection($checkins),
        ]);
    }
}
