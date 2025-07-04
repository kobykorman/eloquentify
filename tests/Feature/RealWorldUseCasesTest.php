<?php

use Illuminate\Support\Facades\DB;
use Tests\Models\User;
use Tests\Models\Post;
use Tests\Models\Comment;

beforeEach(function () {
    // Create a dataset with some users, posts and comments
    $user1 = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $user2 = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
    $user3 = User::create(['name' => 'Charlie', 'email' => 'charlie@example.com']);

    // Create posts for users
    foreach (range(1, 5) as $i) {
        Post::create([
            'user_id' => $user1->id,
            'title' => "Alice's Post $i",
            'content' => "Content for Alice's post $i"
        ]);
    }

    foreach (range(1, 3) as $i) {
        Post::create([
            'user_id' => $user2->id,
            'title' => "Bob's Post $i",
            'content' => "Content for Bob's post $i"
        ]);
    }

    // Charlie has no posts

    // Add comments to posts
    $posts = Post::all();

    foreach ($posts as $index => $post) {
        $commentCount = ($index % 3) + 1; // 1, 2, or 3 comments per post

        foreach (range(1, $commentCount) as $i) {
            Comment::create([
                'post_id' => $post->id,
                'body' => "Comment $i on {$post->title}"
            ]);
        }
    }
});

test('use case: dashboard with user activity stats', function () {
    // Simulate a complex dashboard query that gets users with their post counts and latest post
    $results = DB::table('users')
        ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
        ->select(
            'users.id',
            'users.name',
            'users.email',
            'posts.id as post_id',
            'posts.title as post_title',
            'posts.content as post_content',
            'posts.created_at as post_created_at'
        )
        ->orderBy('posts.created_at', 'desc')
        ->get();

    // Transform using eloquentify
    $users = User::eloquentify($results, [Post::class]);

    // Assertions
    expect($users)->toHaveCount(3); // All 3 users should be returned

    $alice = $users->firstWhere('name', 'Alice');
    $bob = $users->firstWhere('name', 'Bob');
    $charlie = $users->firstWhere('name', 'Charlie');

    // Alice has 5 posts
    expect($alice->posts)->toHaveCount(5);
    expect($alice->posts[0]->title)->toContain('Alice\'s Post');

    // Bob has 3 posts
    expect($bob->posts)->toHaveCount(3);
    expect($bob->posts[0]->title)->toContain('Bob\'s Post');

    // Charlie has no posts
    expect($charlie->posts)->toHaveCount(0);
});

test('use case: blog posts with comments and author details', function () {
    // Simulate a blog listing query that includes posts, their authors, and comments
    $results = DB::table('posts')
        ->join('users', 'users.id', '=', 'posts.user_id')
        ->leftJoin('comments', 'comments.post_id', '=', 'posts.id')
        ->select(
            'posts.id',
            'posts.title',
            'posts.content',
            'posts.created_at',
            'users.id as user_id',
            'users.name as user_name',
            'users.email as user_email',
            'comments.id as comment_id',
            'comments.body as comment_body',
            'comments.created_at as comment_created_at'
        )
        ->orderBy('posts.created_at', 'desc')
        ->get();

    // Transform the results
    $userMeta = new KobyKorman\Eloquentify\ModelMeta(User::class);
    $commentMeta = new KobyKorman\Eloquentify\ModelMeta(Comment::class);

    $posts = Post::eloquentify($results, [$userMeta, $commentMeta]);

    // Assertions
    expect($posts)->toHaveCount(8); // 5 posts for Alice + 3 posts for Bob

    // Check first post's details
    $firstPost = $posts->first();
    expect($firstPost->user)->not->toBeNull();
    expect($firstPost->comments)->not->toBeNull();

    // Check a post with comments
    $postWithComments = $posts->first(function ($post) {
        return $post->comments->count() > 0;
    });

    expect($postWithComments)->not->toBeNull();
    expect($postWithComments->comments->first()->body)->toContain('Comment');
});

test('use case: performance comparison with standard Eloquent', function () {
    // Reset query count
    DB::enableQueryLog();

    // Using eloquentify with a single custom query
    $startTime = microtime(true);

    $results = DB::table('users')
        ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
        ->leftJoin('comments', 'comments.post_id', '=', 'posts.id')
        ->select(
            'users.id',
            'users.name',
            'users.email',
            'posts.id as post_id',
            'posts.title as post_title',
            'posts.content as post_content',
            'comments.id as post_comment_id',
            'comments.body as post_comment_body'
        )
        ->get();

    $commentMeta = new KobyKorman\Eloquentify\ModelMeta(Comment::class);
    $postMeta = new KobyKorman\Eloquentify\ModelMeta(Post::class);
    $postMeta->nest($commentMeta);

    $usersEloquentify = User::eloquentify($results, [$postMeta]);
    $eloquentifyTime = microtime(true) - $startTime;
    $eloquentifyQueries = count(DB::getQueryLog());

    // Reset for standard Eloquent test
    DB::flushQueryLog();

    // Using standard Eloquent with eager loading
    $startTime = microtime(true);
    $usersEloquent = User::with(['posts.comments'])->get();
    $eloquentTime = microtime(true) - $startTime;
    $eloquentQueries = count(DB::getQueryLog());

    // Assertions - check that both approaches return the same structure
    expect($usersEloquentify)->toHaveCount($usersEloquent->count());

    // Key advantage: Eloquentify should use fewer queries
    expect($eloquentifyQueries)->toBeLessThanOrEqual($eloquentQueries);

    // Both should have the same data structure
    if ($usersEloquentify->count() > 0) {
        $user1 = $usersEloquentify->first();
        expect($user1->posts)->not->toBeNull();
    }

    // Output for informational purposes
    echo "\nEloquentify queries: $eloquentifyQueries, time: {$eloquentifyTime}s";
    echo "\nStandard Eloquent queries: $eloquentQueries, time: {$eloquentTime}s\n";
});

test('use case: complex filtering that would be difficult with standard Eloquent', function () {
    // Create a complex query that would be difficult to express with standard Eloquent
    // For example, finding posts with a specific comment AND belonging to a specific user

    $results = DB::table('posts')
        ->join('users', 'users.id', '=', 'posts.user_id')
        ->join('comments', 'comments.post_id', '=', 'posts.id')
        ->where('comments.body', 'like', '%Comment 1%')
        ->where('users.name', '=', 'Alice')
        ->select(
            'posts.id',
            'posts.title',
            'posts.content',
            'users.id as user_id',
            'users.name as user_name',
            'users.email as user_email',
            'comments.id as comment_id',
            'comments.body as comment_body'
        )
        ->get();

    // Transform the results
    $userMeta = new KobyKorman\Eloquentify\ModelMeta(User::class);
    $commentMeta = new KobyKorman\Eloquentify\ModelMeta(Comment::class);

    $posts = Post::eloquentify($results, [$userMeta, $commentMeta]);

    // Assertions
    expect($posts->count())->toBeGreaterThan(0);

    // All posts should belong to Alice
    foreach ($posts as $post) {
        expect($post->user->name)->toBe('Alice');
    }

    // All posts should have at least one comment containing 'Comment 1'
    foreach ($posts as $post) {
        $hasMatchingComment = $post->comments->contains(function ($comment) {
            return str_contains($comment->body, 'Comment 1');
        });

        expect($hasMatchingComment)->toBeTrue();
    }
});
