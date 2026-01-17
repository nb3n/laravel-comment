<?php

namespace Nben\LaravelComment\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Nben\LaravelComment\Traits\Commentable;

class Post extends Model
{
    use Commentable;

    protected $fillable = ['title', 'content'];
}
