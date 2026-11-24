<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mỗi người dùng chỉ có 1 lượt check-in / ngày.
        // checkin_date = ngày check-in (hôm nay), learned_date = ngày đã học (hôm qua).
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checkin_date');
            $table->date('learned_date');
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('sentence_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'checkin_date']);
            $table->index('checkin_date');
        });

        Schema::create('checkin_sentences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkin_id')->constrained()->cascadeOnDelete();
            $table->string('content', 500);          // câu tiếng Anh đã học
            $table->string('meaning', 500)->nullable(); // nghĩa tiếng Việt
            $table->string('note', 500)->nullable();    // ghi chú: ngữ cảnh, từ mới...
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_sentences');
        Schema::dropIfExists('checkins');
    }
};
