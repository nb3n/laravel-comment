<?php

namespace Nben\LaravelComment\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Nben\LaravelComment\Comment;

trait Commentable
{
    public function comments(): MorphMany
    {
        return $this->morphMany(
            config('comment.comment_model', Comment::class),
            'commentable'
        )->whereNull('parent_id')
        ->orderBy('created_at', 'desc');
    }

    public function allComments(): MorphMany
    {
        return $this->morphMany(
            config('comment.comment_model', Comment::class),
            'commentable'
        )->orderBy('created_at', 'desc');
    }

    public function comment($user, string $content): Comment
    {
        $userForeignKey = config('comment.user_foreign_key', 'user_id');
        
        $comment = $this->comments()->create([
            'content' => $content,
            $userForeignKey => $user->{$user->getKeyName()},
        ]);

        return $comment;
    }

    public function commentsCount(): int
    {
        return $this->comments()->count();
    }

    public function allCommentsCount(): int
    {
        return $this->allComments()->count();
    }

    public function hasComments(): bool
    {
        return $this->comments()->exists();
    }

    public function getCommentsWithReplies($depth = null)
    {
        $depth = $depth ?? config('comment.max_nesting_depth', 3);
        
        return $this->comments()
            ->withDepth($depth)
            ->get();
    }
}