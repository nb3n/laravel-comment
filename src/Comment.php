<?php

namespace Nben\LaravelComment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nben\LaravelComment\Events\CommentCreated;
use Nben\LaravelComment\Events\CommentDeleted;

class Comment extends Model
{
    protected $fillable = [
        'content',
        'commentable_id',
        'commentable_type',
        'parent_id',
    ];

    protected $casts = [
        'likes_count' => 'integer',
        'replies_count' => 'integer',
    ];

    protected $dispatchesEvents = [
        'created' => CommentCreated::class,
        'deleted' => CommentDeleted::class,
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = config('comment.comments_table', 'comments');
        parent::__construct($attributes);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($comment) {
            if ($comment->parent_id) {
                $parent = static::find($comment->parent_id);
                if ($parent) {
                    $depth = $parent->depth();
                    $maxDepth = config('comment.max_nesting_depth');
                    
                    if ($maxDepth !== null && $depth >= $maxDepth) {
                        throw new \Exception("Maximum nesting depth of {$maxDepth} reached.");
                    }
                }
            }
        });

        static::created(function ($comment) {
            if ($comment->parent_id) {
                static::where('id', $comment->parent_id)->increment('replies_count');
            }
        });

        static::deleted(function ($comment) {
            if ($comment->parent_id) {
                static::where('id', $comment->parent_id)->decrement('replies_count');
            }
            
            // Delete all replies
            $comment->replies()->delete();
            
            // Delete all likes
            $comment->likes()->delete();
        });
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function commenter(): BelongsTo
    {
        return $this->belongsTo(
            config('comment.user_model', \App\Models\User::class),
            config('comment.user_foreign_key', 'user_id')
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')
            ->orderBy('created_at', 'asc');
    }

    public function likes()
    {
        return $this->hasMany(
            config('comment.comment_like_model', CommentLike::class),
            'comment_id'
        );
    }

    public function likers()
    {
        return $this->belongsToMany(
            config('comment.user_model', \App\Models\User::class),
            config('comment.comment_likes_table', 'comment_likes'),
            'comment_id',
            config('comment.user_foreign_key', 'user_id')
        )->withTimestamps();
    }

    public function reply($user, string $content): self
    {
        $userForeignKey = config('comment.user_foreign_key', 'user_id');
        
        $reply = static::create([
            'content' => $content,
            'commentable_id' => $this->commentable_id,
            'commentable_type' => $this->commentable_type,
            'parent_id' => $this->id,
            $userForeignKey => $user->{$user->getKeyName()},
        ]);

        return $reply;
    }

    public function isLikedBy($user): bool
    {
        if (is_numeric($user)) {
            $userId = $user;
        } else {
            $userId = $user->{$user->getKeyName()};
        }

        return $this->likers()
            ->where(config('comment.user_foreign_key', 'user_id'), $userId)
            ->exists();
    }

    public function depth(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    public function isParent(): bool
    {
        return $this->parent_id === null;
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeWithDepth($query, int $depth = null)
    {
        $depth = $depth ?? config('comment.max_nesting_depth', 3);
        
        return $query->with(['replies' => function ($q) use ($depth) {
            if ($depth > 1) {
                $q->withDepth($depth - 1);
            }
        }]);
    }

    public function getAllReplies()
    {
        return $this->replies()->with('replies')->get();
    }
}