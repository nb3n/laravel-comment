<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommentLikesTable extends Migration
{
    public function up(): void
    {
        Schema::create(config('comment.comment_likes_table', 'comment_likes'), function (Blueprint $table) {
            if (config('comment.uuids')) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }

            $userForeignKey = config('comment.user_foreign_key', 'user_id');

            if (config('comment.uuids')) {
                $table->uuid($userForeignKey)->index();
                $table->uuid('comment_id')->index();
            } else {
                $table->unsignedBigInteger($userForeignKey)->index();
                $table->unsignedBigInteger('comment_id')->index();
            }

            $table->timestamps();

            // Unique constraint to prevent duplicate likes
            $table->unique([$userForeignKey, 'comment_id'], 'unique_user_comment_like');

            // Index for querying likes by comment
            $table->index(['comment_id', 'created_at'], 'idx_comment_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('comment.comment_likes_table', 'comment_likes'));
    }
}
