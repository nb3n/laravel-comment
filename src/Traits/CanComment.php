<?php

namespace Nben\LaravelComment\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nben\LaravelComment\Comment;
use Nben\LaravelComment\CommentLike;

trait CanComment
{
    public function comments(): HasMany
    {
        return $this->hasMany(
            config('comment.comment_model', Comment::class),
            config('comment.user_foreign_key', 'user_id')
        )->orderBy('created_at', 'desc');
    }

    public function comment($commentable, string $content): Comment
    {
        $userForeignKey = config('comment.user_foreign_key', 'user_id');

        $comment = $commentable->comments()->create([
            'content' => $content,
            $userForeignKey => $this->{$this->getKeyName()},
        ]);

        return $comment;
    }

    public function commentLikes(): HasMany
    {
        return $this->hasMany(
            config('comment.comment_like_model', CommentLike::class),
            config('comment.user_foreign_key', 'user_id')
        );
    }

    public function commentedLikes(): BelongsToMany
    {
        return $this->belongsToMany(
            config('comment.comment_model', Comment::class),
            config('comment.comment_likes_table', 'comment_likes'),
            config('comment.user_foreign_key', 'user_id'),
            'comment_id'
        )->withTimestamps();
    }

    public function likeComment($comment): CommentLike
    {
        $comment = $this->getCommentModel($comment);

        if ($this->hasLikedComment($comment)) {
            return $this->commentLikes()
                ->where('comment_id', $comment->{$comment->getKeyName()})
                ->first();
        }

        $userForeignKey = config('comment.user_foreign_key', 'user_id');

        $like = (new (config('comment.comment_like_model', CommentLike::class)))->create([
            $userForeignKey => $this->{$this->getKeyName()},
            'comment_id' => $comment->{$comment->getKeyName()},
        ]);

        return $like;
    }

    public function unlikeComment($comment): bool
    {
        $comment = $this->getCommentModel($comment);

        $userForeignKey = config('comment.user_foreign_key', 'user_id');

        $deleted = (new (config('comment.comment_like_model', CommentLike::class)))
            ->where($userForeignKey, $this->{$this->getKeyName()})
            ->where('comment_id', $comment->{$comment->getKeyName()})
            ->delete();

        return (bool) $deleted;
    }

    public function toggleCommentLike($comment)
    {
        if ($this->hasLikedComment($comment)) {
            return $this->unlikeComment($comment);
        }

        return $this->likeComment($comment);
    }

    public function hasLikedComment($comment): bool
    {
        $comment = $this->getCommentModel($comment);

        if ($this->relationLoaded('commentedLikes')) {
            return $this->commentedLikes
                ->where($comment->getKeyName(), $comment->{$comment->getKeyName()})
                ->isNotEmpty();
        }

        return $this->commentedLikes()
            ->where('comment_id', $comment->{$comment->getKeyName()})
            ->exists();
    }

    protected function getCommentModel($comment)
    {
        if (is_numeric($comment)) {
            return (new (config('comment.comment_model', Comment::class)))->findOrFail($comment);
        }

        if ($comment instanceof Comment) {
            return $comment;
        }

        throw new \InvalidArgumentException('Invalid comment type.');
    }
}
