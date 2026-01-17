<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommentsTable extends Migration
{
    public function up(): void
    {
        Schema::create(config('comment.comments_table', 'comments'), function (Blueprint $table) {
            if (config('comment.uuids')) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }

            $userForeignKey = config('comment.user_foreign_key', 'user_id');

            if (config('comment.uuids')) {
                $table->uuid($userForeignKey)->index();
            } else {
                $table->unsignedBigInteger($userForeignKey)->index();
            }

            if (config('comment.uuids')) {
                $table->uuidMorphs('commentable');
            } else {
                $table->morphs('commentable');
            }

            if (config('comment.uuids')) {
                $table->uuid('parent_id')->nullable()->index();
            } else {
                $table->unsignedBigInteger('parent_id')->nullable()->index();
            }

            $table->text('content');
            $table->unsignedInteger('likes_count')->default(0)->index();
            $table->unsignedInteger('replies_count')->default(0)->index();

            $table->timestamps();

            // Composite indexes for better query performance
            $table->index(['commentable_type', 'commentable_id', 'parent_id', 'created_at'], 'idx_commentable_parent_created');
            $table->index(['parent_id', 'created_at'], 'idx_parent_created');
            $table->index([$userForeignKey, 'created_at'], 'idx_user_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('comment.comments_table', 'comments'));
    }
}
