<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل دورة حياة النظام: كل إقلاع/إطفاء سطر واحد.
     * يُستخدم في صفحة الإعدادات لعرض مدة التشغيل وآخر إيقاف
     * وتتبع الأعطال (إطفاء غير متوقع = فجوة بين سطري boot).
     */
    public function up(): void
    {
        Schema::create('system_lifecycles', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 20)->index(); // boot | shutdown
            $table->string('hostname', 150)->nullable();
            $table->unsignedInteger('pid')->nullable();
            $table->string('php_version', 30)->nullable();
            $table->string('laravel_version', 30)->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at'], 'syslc_event_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_lifecycles');
    }
};
