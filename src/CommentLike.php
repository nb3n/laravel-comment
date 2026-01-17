<?php

namespace Nben\LaravelComment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
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

        if (config('comment.uuids')) {
            $this->keyType = 'string';
            $this->incrementing = false;
        }

        parent::__construct($attributes);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($like) {
            if (config('comment.uuids')) {
                $like->{$like->getKeyName()} = $like->{$like->getKeyName()} ?: (string) Str::orderedUuid();
            }

            $userForeignKey = config('comment.user_foreign_key', 'user_id');
            $like->setAttribute($userForeignKey, $like->{$userForeignKey} ?: auth()->id());
        });

        static::created(function ($like) {
            $commentModel = config('comment.comment_model', Comment::class);
            $commentModel::where('id', $like->comment_id)->increment('likes_count');
        });

        static::deleted(function ($like) {
            $commentModel = config('comment.comment_model', Comment::class);
            $commentModel::where('id', $like->comment_id)->decrement('likes_count');
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

    public function scopeOf($query, Model $comment)
    {
        return $query->where('comment_id', $comment->getKey());
    }

    public function scopeLikedBy($query, Model $user)
    {
        return $query->where(config('comment.user_foreign_key', 'user_id'), $user->getKey());
    }
}
