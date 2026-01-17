# Laravel Comment

[![Tests](https://github.com/nben/laravel-comment/workflows/Tests/badge.svg)](https://github.com/nben/laravel-comment/actions)
[![Latest Stable Version](https://poser.pugx.org/nben/laravel-comment/v)](https://packagist.org/packages/nben/laravel-comment)
[![License](https://poser.pugx.org/nben/laravel-comment/license)](https://packagist.org/packages/nben/laravel-comment)

A flexible, framework-agnostic comment system for Laravel with nested replies and like functionality.

## Features

- **Polymorphic Comments** - Comment on any model
- **Nested Replies** - Threaded conversations with configurable depth
- **Like System** - Simple like/unlike with counter and liker tracking
- **Events** - Extensible with custom event listeners
- **Configurable** - Fully customizable via config file
- **UUID Support** - Optional UUID primary keys
- **Framework Agnostic** - No dependency on Laravel's auth system
- **Clean API** - Intuitive, Laravel-like API

## Requirements

- PHP 8.0+
- Laravel 9.x, 10.x, 11.x, or 12.x

## Installation

Install via Composer:

```bash
composer require nben/laravel-comment
```

Publish the config and migrations:

```bash
php artisan vendor:publish --provider="Nben\LaravelComment\CommentServiceProvider"
```

Or publish individually:

```bash
# Publish config only
php artisan vendor:publish --tag=comment-config

# Publish migrations only
php artisan vendor:publish --tag=comment-migrations
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

The config file `config/comment.php` allows you to customize:

```php
return [
    'uuids' => false, // Use UUIDs instead of auto-incrementing IDs
    'user_foreign_key' => 'user_id', // Foreign key column name for users
    'comments_table' => 'comments', // Table name for comments
    'comment_likes_table' => 'comment_likes', // Table name for likes
    'comment_model' => \Nben\LaravelComment\Comment::class,
    'comment_like_model' => \Nben\LaravelComment\CommentLike::class,
    'max_nesting_depth' => 3, // Maximum reply depth (null for unlimited)
    'user_model' => \App\Models\User::class,
];
```

## Usage

### Setup Models

Add the `Commentable` trait to models that can be commented on:

```php
use Nben\LaravelComment\Traits\Commentable;

class Post extends Model
{
    use Commentable;
}
```

Add the `CanComment` trait to your User model:

```php
use Nben\LaravelComment\Traits\CanComment;

class User extends Model
{
    use CanComment;
}
```

### Creating Comments

**All methods require you to pass the User model explicitly - no dependency on `auth()`.**

```php
$user = User::find(1);
$post = Post::find(1);

// Comment on a post
$comment = $post->comment($user, 'Great post!');

// Or using the user model
$comment = $user->comment($post, 'Great post!');
```

### Creating Replies

Replies also require the user to be passed:

```php
$user = User::find(1);
$comment = Comment::find(1);

// Reply to a comment
$reply = $comment->reply($user, 'Thanks for your feedback!');
```

### Retrieving Comments

```php
// Get all top-level comments for a model
$comments = $post->comments; // Returns only parent comments

// Get ALL comments including replies
$allComments = $post->allComments;

// Get comments with nested replies
$comments = $post->getCommentsWithReplies();

// Get comments with custom depth
$comments = $post->getCommentsWithReplies(2);

// Get comment count
$count = $post->commentsCount(); // Only parent comments
$totalCount = $post->allCommentsCount(); // All comments including replies

// Check if has comments
if ($post->hasComments()) {
    // ...
}

// Get user's comments
$userComments = $user->comments;
```

### Comment Relationships

```php
// Get comment author
$user = $comment->commenter;

// Get commentable model
$post = $comment->commentable;

// Get parent comment
$parent = $comment->parent;

// Get replies
$replies = $comment->replies;

// Get all nested replies
$allReplies = $comment->getAllReplies();

// Check comment type
$comment->isParent(); // true if top-level comment
$comment->isReply(); // true if reply to another comment

// Get depth
$depth = $comment->depth(); // 0 for parent, 1+ for replies
```

### Liking Comments

```php
$user = User::find(1);
$comment = Comment::find(1);

// Like a comment (pass the Comment model)
$user->likeComment($comment);

// Unlike a comment
$user->unlikeComment($comment);

// Toggle like
$user->toggleCommentLike($comment);

// Check if user liked comment
if ($user->hasLikedComment($comment)) {
    // ...
}

// Or check from comment
if ($comment->isLikedBy($user)) {
    // ...
}

// Get all users who liked a comment
$likers = $comment->likers;

// Get all comments liked by user
$likedComments = $user->commentedLikes;

// Get like count
$count = $comment->likes_count;
```

### Deleting Comments

```php
// Delete comment
$comment->delete();

// Note: Deleting a comment will automatically delete all its replies and likes
```

## API Examples

### Example 1: Simple Commenting

```php
$user = User::find(1);
$post = Post::find(1);

// User posts a comment
$comment = $post->comment($user, 'This is awesome!');

// Another user replies
$otherUser = User::find(2);
$reply = $comment->reply($otherUser, 'I agree!');

// Like the original comment
$user->likeComment($comment);
```

### Example 2: Building a Comment System

```php
// Get the current user (however you manage users)
$currentUser = getCurrentUser(); // Your custom method

// Post a comment
$post = Post::find(1);
$comment = $post->comment($currentUser, 'Great article!');

// Reply to a comment
$parentComment = Comment::find(5);
$reply = $parentComment->reply($currentUser, 'Thanks!');

// Like/unlike
$currentUser->likeComment($comment);
$currentUser->unlikeComment($comment);
```

### Example 3: Nested Conversations

```php
$user = User::find(1);
$post = Post::find(1);

// Create a comment thread
$comment = $post->comment($user, 'Main comment');
$reply1 = $comment->reply($user, 'First reply');
$reply2 = $reply1->reply($user, 'Nested reply');

// Get all comments with replies
$comments = $post->getCommentsWithReplies();
```

## Events

The package dispatches the following events:

- `Nben\LaravelComment\Events\CommentCreated`
- `Nben\LaravelComment\Events\CommentDeleted`
- `Nben\LaravelComment\Events\CommentLiked`
- `Nben\LaravelComment\Events\CommentUnliked`

Listen to these events in your `EventServiceProvider`:

```php
protected $listen = [
    \Nben\LaravelComment\Events\CommentCreated::class => [
        \App\Listeners\SendCommentNotification::class,
    ],
    \Nben\LaravelComment\Events\CommentLiked::class => [
        \App\Listeners\SendLikeNotification::class,
    ],
];
```

## Blade Example

```blade
{{-- Display comments with replies --}}
@foreach($post->getCommentsWithReplies() as $comment)
    <div class="comment">
        <div class="comment-header">
            <strong>{{ $comment->commenter->name }}</strong>
            <small>{{ $comment->created_at->diffForHumans() }}</small>
        </div>
        
        <div class="comment-body">
            {{ $comment->content }}
        </div>
        
        <div class="comment-actions">
            <span>{{ $comment->likes_count }} likes</span>
            <span>{{ $comment->replies_count }} replies</span>
            
            @if($currentUser->hasLikedComment($comment))
                <button wire:click="unlike({{ $comment->id }})">Unlike</button>
            @else
                <button wire:click="like({{ $comment->id }})">Like</button>
            @endif
            
            <button wire:click="showReplyForm({{ $comment->id }})">Reply</button>
        </div>
        
        {{-- Display replies --}}
        @if($comment->replies->isNotEmpty())
            <div class="replies ml-8">
                @foreach($comment->replies as $reply)
                    <div class="reply">
                        <strong>{{ $reply->commenter->name }}</strong>
                        <p>{{ $reply->content }}</p>
                        <small>{{ $reply->likes_count }} likes</small>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endforeach

{{-- Comment form --}}
<form wire:submit.prevent="submitComment">
    <textarea wire:model="commentContent" placeholder="Write a comment..."></textarea>
    <button type="submit">Comment</button>
</form>
```

## Livewire Example

```php
use Livewire\Component;
use Nben\LaravelComment\Comment;

class PostComments extends Component
{
    public $post;
    public $commentContent;
    public $replyContent;
    public $replyingTo;

    public function submitComment()
    {
        $this->validate(['commentContent' => 'required|min:1']);
        
        // Get current user from your system
        $user = $this->getCurrentUser();
        
        $this->post->comment($user, $this->commentContent);
        $this->commentContent = '';
    }

    public function submitReply($commentId)
    {
        $this->validate(['replyContent' => 'required|min:1']);
        
        $comment = Comment::findOrFail($commentId);
        $user = $this->getCurrentUser();
        
        $comment->reply($user, $this->replyContent);
        
        $this->replyContent = '';
        $this->replyingTo = null;
    }

    public function like($commentId)
    {
        $comment = Comment::findOrFail($commentId);
        $user = $this->getCurrentUser();
        
        $user->likeComment($comment);
    }

    public function unlike($commentId)
    {
        $comment = Comment::findOrFail($commentId);
        $user = $this->getCurrentUser();
        
        $user->unlikeComment($comment);
    }

    protected function getCurrentUser()
    {
        // Your logic to get current user
        return auth()->user(); // Or however you manage users
    }

    public function render()
    {
        return view('livewire.post-comments', [
            'comments' => $this->post->getCommentsWithReplies()
        ]);
    }
}
```

## Advanced Usage

### Custom Comment Model

Create your own comment model extending the base:

```php
namespace App\Models;

use Nben\LaravelComment\Comment as BaseComment;

class Comment extends BaseComment
{
    protected $appends = ['is_edited'];
    
    public function getIsEditedAttribute()
    {
        return $this->created_at != $this->updated_at;
    }
}
```

Update config:

```php
'comment_model' => \App\Models\Comment::class,
```

### Eager Loading

```php
$comments = $post->comments()
    ->with(['commenter', 'replies.commenter', 'likers'])
    ->get();
```

### Querying

```php
use Nben\LaravelComment\Comment;

// Get recent comments
$recent = Comment::latest()->take(10)->get();

// Get most liked comments
$popular = Comment::orderBy('likes_count', 'desc')->take(10)->get();

// Get only parent comments
$parents = Comment::parents()->get();

// Get only replies
$replies = Comment::replies()->get();

// Get comments on specific model type
$postComments = Comment::where('commentable_type', Post::class)->get();
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.