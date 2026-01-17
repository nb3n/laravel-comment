<?php

namespace Nben\LaravelComment\Events;

use Illuminate\Queue\SerializesModels;
use Nben\LaravelComment\CommentLike;

class CommentLiked
{
    use SerializesModels;

    public CommentLike $like;

    public function __construct(CommentLike $like)
    {
        $this->like = $like;
    }
}
