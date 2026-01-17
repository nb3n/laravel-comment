<?php

namespace Nben\LaravelComment\Tests;

use Illuminate\Support\Facades\Event;
use Nben\LaravelComment\Comment;
use Nben\LaravelComment\Events\CommentCreated;
use Nben\LaravelComment\Events\CommentDeleted;
use Nben\LaravelComment\Events\CommentLiked;
use Nben\LaravelComment\Events\CommentUnliked;
use Nben\LaravelComment\Tests\Models\Post;
use Nben\LaravelComment\Tests\Models\User;

class CommentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_user_can_comment_on_post()
    {
        $user = User::create(['name' => 'John']);
        $post = Post::create(['title' => 'Test Post']);

        $comment = $post->comment($user, 'Great post!');

        Event::assertDispatched(
            CommentCreated::class,
            function ($event) use ($comment) {
                return $event->comment->id === $comment->id;
            }
        );

        $this->assertTrue($post->hasComments());
        $this->assertSame(1, $post->commentsCount());
    }

    public function test_user_can_reply_to_comment()
    {
        $user = User::create(['name' => 'John']);
        $post = Post::create(['title' => 'Test Post']);
        $comment = $post->comment($user, 'Great post!');

        $reply = $comment->reply($user, 'Thanks!');

        $this->assertSame($comment->id, $reply->parent_id);
        $this->assertSame(1, $comment->fresh()->replies_count);
        $this->assertTrue($reply->isReply());
        $this->assertFalse($reply->isParent());
    }

    public function test_deleting_comment_deletes_replies_and_likes()
    {
        $user1 = User::create(['name' => 'John']);
        $user2 = User::create(['name' => 'Jane']);
        $post = Post::create(['title' => 'Test Post']);

        $comment = $post->comment($user1, 'Parent');
        $reply = $comment->reply($user2, 'Child');
        $user1->likeComment($comment);

        $this->assertSame(1, $comment->fresh()->replies_count);
        $this->assertSame(1, $comment->fresh()->likes_count);

        $comment->delete();

        $this->assertDatabaseMissing('comments', ['id' => $reply->id]);
        $this->assertDatabaseMissing('comment_likes', ['comment_id' => $comment->id]);

        Event::assertDispatched(CommentDeleted::class);
    }

    public function test_user_can_like_and_unlike_comment()
    {
        $user = User::create(['name' => 'John']);
        $post = Post::create(['title' => 'Test Post']);
        $comment = $post->comment($user, 'Great!');

        $user->likeComment($comment);

        Event::assertDispatched(
            CommentLiked::class,
            function ($event) use ($comment) {
                return $event->like->comment_id === $comment->id;
            }
        );

        $this->assertTrue($user->hasLikedComment($comment));
        $this->assertTrue($comment->isLikedBy($user));
        $this->assertSame(1, $comment->fresh()->likes_count);

        $user->unlikeComment($comment);

        Event::assertDispatched(CommentUnliked::class);

        $this->assertFalse($user->hasLikedComment($comment));
        $this->assertSame(0, $comment->fresh()->likes_count);
    }

    public function test_toggle_like_works()
    {
        $user = User::create(['name' => 'John']);
        $post = Post::create(['title' => 'Test Post']);
        $comment = $post->comment($user, 'Great!');

        $user->toggleCommentLike($comment);
        $this->assertTrue($user->hasLikedComment($comment));

        $user->toggleCommentLike($comment);
        $this->assertFalse($user->hasLikedComment($comment));
    }

    public function test_duplicate_likes_are_handled()
    {
        $user = User::create(['name' => 'John']);
        $post = Post::create(['title' => 'Test Post']);
        $comment = $post->comment($user, 'Great!');

        $user->likeComment($comment);
        $user->likeComment($comment); // Should not create duplicate

        $this->assertSame(1, $comment->fresh()->likes_count);
        $this->assertDatabaseCount('comment_likes', 1);
    }

    public function test_comment_depth_calculation()
    {
        $user = User::create(['name' => 'John']);
        $post = Post::create(['title' => 'Test Post']);

        $comment1 = $post->comment($user, 'Level 0');
        $comment2 = $comment1->reply($user, 'Level 1');
        $comment3 = $comment2->reply($user, 'Level 2');

        $this->assertSame(0, $comment1->depth());
        $this->assertSame(1, $comment2->depth());
        $this->assertSame(2, $comment3->depth());
    }

    public function test_parent_and_reply_checks()
    {
        $user = User::create(['name' => 'John']);
        $post = Post::create(['title' => 'Test Post']);

        $comment = $post->comment($user, 'Parent');
        $reply = $comment->reply($user, 'Child');

        $this->assertTrue($comment->isParent());
        $this->assertFalse($comment->isReply());

        $this->assertFalse($reply->isParent());
        $this->assertTrue($reply->isReply());
    }

    public function test_scopes_work_correctly()
    {
        $user = User::create(['name' => 'John']);
        $post1 = Post::create(['title' => 'Post 1']);
        $post2 = Post::create(['title' => 'Post 2']);

        $comment1 = $post1->comment($user, 'Comment 1');
        $reply1 = $comment1->reply($user, 'Reply 1');
        $comment2 = $post2->comment($user, 'Comment 2');

        $this->assertSame(2, Comment::parents()->count());
        $this->assertSame(1, Comment::replies()->count());
        $this->assertSame(2, Comment::of($post1)->count());
        $this->assertSame(1, Comment::of($post2)->count());
        $this->assertSame(3, Comment::commentedBy($user)->count());
    }

    public function test_eager_loading_reduces_queries()
    {
        $user = User::create(['name' => 'John']);
        $post = Post::create(['title' => 'Test Post']);

        $comment = $post->comment($user, 'Comment');
        $comment->reply($user, 'Reply 1');
        $comment->reply($user, 'Reply 2');

        // Without eager loading
        $sqls = $this->getQueryLog(function () use ($post) {
            $comments = $post->comments;
            foreach ($comments as $c) {
                $c->commenter->name;
                foreach ($c->replies as $r) {
                    $r->commenter->name;
                }
            }
        });

        $countWithoutEager = $sqls->count();

        // With eager loading
        $sqls = $this->getQueryLog(function () use ($post) {
            $comments = $post->comments()->with(['commenter', 'replies.commenter'])->get();
            foreach ($comments as $c) {
                $c->commenter->name;
                foreach ($c->replies as $r) {
                    $r->commenter->name;
                }
            }
        });

        $countWithEager = $sqls->count();

        $this->assertTrue($countWithEager < $countWithoutEager);
    }

    public function test_order_by_comments_count()
    {
        $user = User::create(['name' => 'John']);
        $post1 = Post::create(['title' => 'Post 1']);
        $post2 = Post::create(['title' => 'Post 2']);
        $post3 = Post::create(['title' => 'Post 3']);

        // Post 2: 3 comments
        $post2->comment($user, 'Comment 1');
        $post2->comment($user, 'Comment 2');
        $post2->comment($user, 'Comment 3');

        // Post 1: 2 comments
        $post1->comment($user, 'Comment 1');
        $post1->comment($user, 'Comment 2');

        // Post 3: 0 comments

        $posts = Post::orderByCommentsCountDesc()->get();

        $this->assertSame($post2->id, $posts[0]->id);
        $this->assertSame(3, $posts[0]->all_comments_count);
        $this->assertSame($post1->id, $posts[1]->id);
        $this->assertSame(2, $posts[1]->all_comments_count);
        $this->assertSame($post3->id, $posts[2]->id);
        $this->assertSame(0, $posts[2]->all_comments_count);
    }

    public function test_get_comments_with_replies()
    {
        $user = User::create(['name' => 'John']);
        $post = Post::create(['title' => 'Test Post']);

        $comment1 = $post->comment($user, 'Comment 1');
        $reply1 = $comment1->reply($user, 'Reply 1');
        $reply2 = $comment1->reply($user, 'Reply 2');

        $comments = $post->getCommentsWithReplies();

        $this->assertSame(1, $comments->count());
        $this->assertSame(2, $comments->first()->replies->count());
    }

    protected function getQueryLog(\Closure $callback): \Illuminate\Support\Collection
    {
        $sqls = \collect([]);
        \DB::listen(function ($query) use ($sqls) {
            $sqls->push(['sql' => $query->sql, 'bindings' => $query->bindings]);
        });

        $callback();

        return $sqls;
    }
}
