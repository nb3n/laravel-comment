<?php

namespace Nben\LaravelComment\Events;

use Illuminate\Queue\SerializesModels;
use Nben\LaravelComment\Comment;

class CommentCreated
{
    use SerializesModels;

    public Comment $comment;

    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
    }
}
