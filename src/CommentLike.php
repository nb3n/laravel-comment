<?php

namespace Nben\LaravelComment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nben\LaravelComment\Events\CommentLiked;
use Nben\LaravelComment\Events\CommentUnliked;

class CommentLike extends Model
{
    protected $fillable = [
        'comment_id',
    ];

    protected $dispatchesEvents = [
        'created' => CommentLiked::class,
        'deleted' => CommentUnliked::class,
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = config('comment.comment_likes_table', 'comment_likes');
        parent::__construct($attributes);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($like) {
            Comment::where('id', $like->comment_id)->increment('likes_count');
        });

        static::deleted(function ($like) {
            Comment::where('id', $like->comment_id)->decrement('likes_count');
        });
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(
            config('comment.comment_model', Comment::class),
            'comment_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('comment.user_model', \App\Models\User::class),
            config('comment.user_foreign_key', 'user_id')
        );
    }
}