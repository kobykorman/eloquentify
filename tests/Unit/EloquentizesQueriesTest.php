<?php

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use KobyKorman\Eloquentize\ModelMeta;
use Tests\Models\User;
use Tests\Models\Post;
use Tests\Models\Comment;

test('EloquentizesQueries trait provides eloquentize static method', function () {
    // Create a test query result
    $result = createQueryResult([
        ['id' => 1, 'name' => 'John', 'post_id' => 10, 'post_title' => 'First Post'],
        ['id' => 1, 'name' => 'John', 'post_id' => 11, 'post_title' => 'Second Post'],
    ]);

    // Use the trait's eloquentize method
    $users = User::eloquentize($result, [Post::class]);

    expect($users)->toBeInstanceOf(EloquentCollection::class);
    expect($users)->toHaveCount(1);
    expect($users[0]->posts)->toHaveCount(2);
});

test('EloquentizesQueries trait handles string class names', function () {
    // Create a test query result
    $result = createQueryResult([
        ['id' => 1, 'name' => 'John', 'post_id' => 10, 'post_title' => 'First Post'],
    ]);

    // Use the trait's eloquentize method with string class name
    $users = User::eloquentize($result, [Post::class]);

    expect($users)->toHaveCount(1);
    expect($users[0]->posts)->toHaveCount(1);
});

test('EloquentizesQueries trait handles ModelMeta instances', function () {
    // Create a test query result
    $result = createQueryResult([
        ['id' => 1, 'name' => 'John', 'post_id' => 10, 'post_title' => 'First Post'],
    ]);

    // Create a ModelMeta instance manually
    $postMeta = new ModelMeta(Post::class);

    // Use the trait's eloquentize method with ModelMeta instance
    $users = User::eloquentize($result, [$postMeta]);

    expect($users)->toHaveCount(1);
    expect($users[0]->posts)->toHaveCount(1);
});

test('EloquentizesQueries trait handles deeply nested relationships', function () {
    // Create a test query result with users, posts, and comments
    $result = createQueryResult([
        [
            'id' => 1, 
            'name' => 'John', 
            'post_id' => 10, 
            'post_title' => 'First Post', 
            'post_comment_id' => 100, 
            'post_comment_body' => 'Comment body'
        ],
    ]);

    // Create nested ModelMeta instances
    $postMeta = new ModelMeta(Post::class);
    $commentMeta = new ModelMeta(Comment::class);
    $postMeta->nest($commentMeta);

    // Use the trait's eloquentize method with nested ModelMeta
    $users = User::eloquentize($result, [$postMeta]);

    expect($users)->toHaveCount(1);
    expect($users[0]->posts)->toHaveCount(1);
    expect($users[0]->posts[0]->comments)->toHaveCount(1);
    expect($users[0]->posts[0]->comments[0]->body)->toBe('Comment body');
});
