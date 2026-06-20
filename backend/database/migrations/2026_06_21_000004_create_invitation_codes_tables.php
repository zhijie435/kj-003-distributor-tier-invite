<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_codes', static function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('customer_group_id');
            $table->string('description')->nullable();
            $table->integer('max_uses')->default(0);
            $table->integer('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->cascadeOnDelete();

            $table->index('code');
            $table->index('customer_group_id');
            $table->index('expires_at');
        });

        Schema::create('invitation_code_usages', static function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invitation_code_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('invitation_code_id')
                ->references('id')
                ->on('invitation_codes')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->unique(['invitation_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_code_usages');
        Schema::dropIfExists('invitation_codes');
    }
};
