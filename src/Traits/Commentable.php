<?php

namespace Nben\LaravelComment\Traits;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
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

    public function scopeOrderByCommentsCount($query, string $direction = 'desc')
    {
        return $query->withCount('allComments')->orderBy('all_comments_count', $direction);
    }

    public function scopeOrderByCommentsCountDesc($query)
    {
        return $this->scopeOrderByCommentsCount($query, 'desc');
    }

    public function scopeOrderByCommentsCountAsc($query)
    {
        return $this->scopeOrderByCommentsCount($query, 'asc');
    }

    public function attachCommentStatus($comments, ?callable $resolver = null)
    {
        $returnFirst = false;

        switch (true) {
            case $comments instanceof Model:
                $returnFirst = true;
                $comments = collect([$comments]);
                break;
            case $comments instanceof LengthAwarePaginator:
                $comments = $comments->getCollection();
                break;
            case $comments instanceof Paginator:
            case $comments instanceof CursorPaginator:
                $comments = collect($comments->items());
                break;
            case $comments instanceof LazyCollection:
                $comments = collect(iterator_to_array($comments->getIterator()));
                break;
            case is_array($comments):
                $comments = collect($comments);
                break;
        }

        if (! ($comments instanceof Enumerable)) {
            throw new \InvalidArgumentException('Invalid $comments type.');
        }

        $commented = $this->allComments()->get();

        $comments->map(function ($comment) use ($commented, $resolver) {
            $resolver = $resolver ?? fn ($m) => $m;
            $comment = $resolver($comment);

            if ($comment && $comment instanceof Comment) {
                $item = $commented->where('id', $comment->getKey())->first();
                $comment->setAttribute('has_commented', (bool) $item);
                $comment->setAttribute('commented_at', $item ? $item->created_at : null);
            }
        });

        return $returnFirst ? $comments->first() : $comments;
    }
}
