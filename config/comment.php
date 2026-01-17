<?php

return [
    /*
     * Use uuid as primary key.
     */
    'uuids' => false,

    /*
     * User tables foreign key name.
     */
    'user_foreign_key' => 'user_id',

    /*
     * Table name for comments table.
     */
    'comments_table' => 'comments',

    /*
     * Table name for comment likes table.
     */
    'comment_likes_table' => 'comment_likes',

    /*
     * Model class name for comments table.
     */
    'comment_model' => \Nben\LaravelComment\Comment::class,

    /*
     * Model class name for comment likes table.
     */
    'comment_like_model' => \Nben\LaravelComment\CommentLike::class,

    /*
     * Maximum nesting depth for replies (null for unlimited, recommended: 2-3).
     */
    'max_nesting_depth' => 3,

    /*
     * User model class name.
     */
    'user_model' => \App\Models\User::class,
];