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
- **Eager Loading** - N+1 query prevention
- **Aggregations** - Built-in counting and ordering methods

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

### Scopes and Query Methods

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

// Get comments on specific model
$postComments = Comment::of($post)->get();

// Get comments by specific user
$userComments = Comment::commentedBy($user)->get();

// Order models by comment count
$popularPosts = Post::orderByCommentsCountDesc()->get();
$leastCommented = Post::orderByCommentsCountAsc()->get();
```

### Aggregations

```php
// Comments count
$post->comments()->count();
$post->allComments()->count();

// With conditions
$post->comments()->where('created_at', '>', now()->subDays(7))->count();

// Likes count
$comment->likes()->count();
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
// Get the current user
$currentUser = auth()->user();

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

### Example 4: Eager Loading (Preventing N+1)

```php
// Load comments with relationships
$comments = $post->comments()
    ->with(['commenter', 'replies.commenter', 'likers'])
    ->get();

// Now accessing relationships won't trigger additional queries
foreach ($comments as $comment) {
    echo $comment->commenter->name; // No additional query
    foreach ($comment->replies as $reply) {
        echo $reply->commenter->name; // No additional query
    }
}
```

### Example 5: Most Commented Posts

```php
// Get posts ordered by comment count
$popularPosts = Post::withCount('allComments')
    ->orderByDesc('all_comments_count')
    ->take(10)
    ->get();

// Or use the built-in scope
$popularPosts = Post::orderByCommentsCountDesc()->take(10)->get();
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
    
    // Add custom methods
    public function markAsSpam()
    {
        $this->update(['is_spam' => true]);
    }
}
```

Update config:

```php
'comment_model' => \App\Models\Comment::class,
```

### Attach Comment Status

Similar to `laravel-follow`, you can attach comment status to collections:

```php
$comments = Comment::all();
$post->attachCommentStatus($comments);

// Now each comment has 'has_commented' and 'commented_at' attributes
foreach ($comments as $comment) {
    if ($comment->has_commented) {
        echo "Commented at: " . $comment->commented_at;
    }
}
```

## Best Practices

### 1. Use Eager Loading

Always use eager loading to prevent N+1 queries:

```php
$comments = Comment::with(['commenter', 'replies.commenter', 'likers'])->get();
```

### 2. Limit Reply Depth

Set a reasonable `max_nesting_depth` in config (2-3 recommended):

```php
'max_nesting_depth' => 3,
```

### 3. Cache Heavy Queries

For high-traffic applications, cache comment counts:

```php
$commentCount = Cache::remember("post.{$post->id}.comments", 3600, function () use ($post) {
    return $post->allCommentsCount();
});
```

### 4. Use Database Transactions

When performing multiple operations:

```php
DB::transaction(function () use ($post, $user) {
    $comment = $post->comment($user, 'Great post!');
    $user->likeComment($comment);
});
```

## Performance Tips

1. **Index Usage**: The package creates optimized indexes for common queries
2. **Batch Operations**: Use chunk() for large datasets
3. **Select Specific Columns**: Only select what you need
4. **Pagination**: Always paginate large result sets

```php
// Good
$comments = Comment::select('id', 'content', 'created_at')
    ->paginate(20);

// Better with eager loading
$comments = Comment::with('commenter:id,name')
    ->paginate(20);
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## Credits

Inspired by [overtrue/laravel-follow](https://github.com/overtrue/laravel-follow).