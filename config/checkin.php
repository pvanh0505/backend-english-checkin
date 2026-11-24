<?php

return [
    // Số câu tối thiểu phải ghi lại để check-in hợp lệ
    'min_sentences' => (int) env('CHECKIN_MIN_SENTENCES', 3),

    // Số câu tối đa cho 1 lượt check-in
    'max_sentences' => (int) env('CHECKIN_MAX_SENTENCES', 50),

    // Số ngày nhìn lại khi tính chuỗi ngày liên tiếp (streak)
    'streak_lookback_days' => (int) env('CHECKIN_STREAK_LOOKBACK', 400),
];
