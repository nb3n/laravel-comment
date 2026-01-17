<?php

namespace Nben\LaravelComment\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Nben\LaravelComment\Traits\CanComment;

class User extends Model
{
    use CanComment;

    protected $fillable = ['name'];
}
