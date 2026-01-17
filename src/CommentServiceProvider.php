<?php

namespace Nben\LaravelComment;

use Illuminate\Support\ServiceProvider;

class CommentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            \dirname(__DIR__).'/config/comment.php',
            'comment'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                \dirname(__DIR__).'/config/comment.php' => config_path('comment.php'),
            ], 'comment-config');

            $this->publishes([
                \dirname(__DIR__).'/migrations/2026_01_01_000000_create_comments_table.php' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_comments_table.php'),
                \dirname(__DIR__).'/migrations/2026_01_01_000001_create_comment_likes_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 1).'_create_comment_likes_table.php'),
            ], 'comment-migrations');

            $this->publishes([
                \dirname(__DIR__).'/config/comment.php' => config_path('comment.php'),
                \dirname(__DIR__).'/migrations/2026_01_01_000000_create_comments_table.php' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_comments_table.php'),
                \dirname(__DIR__).'/migrations/2026_01_01_000001_create_comment_likes_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 1).'_create_comment_likes_table.php'),
            ], 'comment');
        }
    }
}
