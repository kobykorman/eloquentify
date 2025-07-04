<?php

use Illuminate\Support\Facades\DB;
use Tests\Models\User;
use Tests\Models\Post;
use Tests\Models\Comment;
use Tests\Models\Profile;

beforeEach(function () {
    // Create test data
    $john = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);

    $jane = User::create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com'
    ]);

    // Create profiles
    Profile::create([
        'user_id' => $john->id,
        'bio' => 'John\'s biography',
        'avatar' => 'john.jpg'
    ]);

    Profile::create([
        'user_id' => $jane->id,
        'bio' => 'Jane\'s biography',
        'avatar' => 'jane.jpg'
    ]);

    // Create posts
    $post1 = Post::create([
        'user_id' => $john->id,
        'title' => 'John\'s First Post',
        'content' => 'Content of John\'s first post'
    ]);

    $post2 = Post::create([
        'user_id' => $john->id,
        'title' => 'John\'s Second Post',
        'content' => 'Content of John\'s second post'
    ]);

    $post3 = Post::create([
        'user_id' => $jane->id,
        'title' => 'Jane\'s Post',
        'content' => 'Content of Jane\'s post'
    ]);

    // Create comments
    Comment::create([
        'post_id' => $post1->id,
        'body' => 'First comment on John\'s first post'
    ]);

    Comment::create([
        'post_id' => $post1->id,
        'body' => 'Second comment on John\'s first post'
    ]);

    Comment::create([
        'post_id' => $post3->id,
        'body' => 'Comment on Jane\'s post'
    ]);
});

test('can transform a simple query with one-to-many relationships', function () {
    // Execute a query with join
    $results = DB::table('users')
        ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
        ->select(
            'users.id',
            'users.name',
            'users.email',
            'posts.id as post_id',
            'posts.title as post_title',
            'posts.content as post_content'
        )
        ->get();

    // Transform using eloquentify
    $users = User::eloquentify($results, [Post::class]);

    // Assertions
    expect($users)->toHaveCount(2);

    $john = $users->firstWhere('name', 'John Doe');
    $jane = $users->firstWhere('name', 'Jane Smith');

    expect($john->posts)->toHaveCount(2);
    expect($jane->posts)->toHaveCount(1);

    expect($john->posts[0]->title)->toContain('John\'s');
    expect($jane->posts[0]->title)->toBe('Jane\'s Post');
});

test('can transform a complex query with multiple nested relationships', function () {
    // Execute a complex query with multiple joins
    $results = DB::table('users')
        ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
        ->leftJoin('comments', 'comments.post_id', '=', 'posts.id')
        ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
        ->select(
            'users.id',
            'users.name',
            'users.email',
            'posts.id as post_id',
            'posts.title as post_title',
            'posts.content as post_content',
            'comments.id as post_comment_id',
            'comments.body as post_comment_body',
            'profiles.id as profile_id',
            'profiles.bio as profile_bio',
            'profiles.avatar as profile_avatar'
        )
        ->get();

    // Set up model relationships
    $commentMeta = new KobyKorman\Eloquentify\ModelMeta(Comment::class);
    $postMeta = new KobyKorman\Eloquentify\ModelMeta(Post::class);
    $profileMeta = new KobyKorman\Eloquentify\ModelMeta(Profile::class);

    $postMeta->nest($commentMeta);

    // Transform using eloquentify with multiple relations
    $users = User::eloquentify($results, [$postMeta, $profileMeta]);

    // Assertions
    expect($users)->toHaveCount(2);

    $john = $users->firstWhere('name', 'John Doe');

    // Check posts and comments
    expect($john->posts)->toHaveCount(2);

    $firstPost = $john->posts->firstWhere('title', 'John\'s First Post');
    expect($firstPost->comments)->toHaveCount(2);

    // Check profile
    expect($john->profile)->not->toBeNull();
    expect($john->profile->bio)->toBe('John\'s biography');

    // Check Jane's data
    $jane = $users->firstWhere('name', 'Jane Smith');
    expect($jane->posts)->toHaveCount(1);
    expect($jane->posts[0]->comments)->toHaveCount(1);
    expect($jane->profile->bio)->toBe('Jane\'s biography');
});

test('handles empty result sets gracefully', function () {
    // Execute a query that returns no results
    $results = DB::table('users')
        ->where('name', 'Non-existent User')
        ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
        ->select(
            'users.id',
            'users.name',
            'posts.id as post_id',
            'posts.title as post_title'
        )
        ->get();

    // Transform using eloquentify
    $users = User::eloquentify($results, [Post::class]);

    // Assertions
    expect($users)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
    expect($users)->toHaveCount(0);
});

test('can handle real-world complex query with many-to-many relationships', function () {
    // Create some tags
    $tag1 = Tests\Models\Tag::create(['name' => 'Laravel']);
    $tag2 = Tests\Models\Tag::create(['name' => 'PHP']);
    $tag3 = Tests\Models\Tag::create(['name' => 'Tutorial']);

    // Attach tags to posts
    $post1 = Post::first();
    $post1->tags()->attach([$tag1->id, $tag2->id]);

    $post2 = Post::skip(1)->first();
    $post2->tags()->attach([$tag2->id, $tag3->id]);

    // Execute a complex query with many-to-many relationship
    $results = DB::table('posts')
        ->join('users', 'users.id', '=', 'posts.user_id')
        ->leftJoin('post_tag', 'post_tag.post_id', '=', 'posts.id')
        ->leftJoin('tags', 'tags.id', '=', 'post_tag.tag_id')
        ->leftJoin('comments', 'comments.post_id', '=', 'posts.id')
        ->select(
            'posts.id',
            'posts.title',
            'posts.content',
            'users.id as user_id',
            'users.name as user_name',
            'users.email as user_email',
            'tags.id as tag_id',
            'tags.name as tag_name',
            'comments.id as comment_id',
            'comments.body as comment_body'
        )
        ->get();

    // Set up model relationships for transformation
    $userMeta = new KobyKorman\Eloquentify\ModelMeta(User::class);
    $tagMeta = new KobyKorman\Eloquentify\ModelMeta(Tests\Models\Tag::class);
    $commentMeta = new KobyKorman\Eloquentify\ModelMeta(Comment::class);

    // Transform using Post as the root model
    $posts = Post::eloquentify($results, [$userMeta, $tagMeta, $commentMeta]);

    // Assertions
    expect($posts)->toHaveCount(3); // 3 posts in our test data

    // Check that we have the correct relationships
    $firstPost = $posts->firstWhere('title', 'John\'s First Post');
    expect($firstPost->user->name)->toBe('John Doe');
    expect($firstPost->tags)->toHaveCount(2);
    expect($firstPost->comments)->toHaveCount(2);

    // Check tag names
    $tagNames = $firstPost->tags->pluck('name')->toArray();
    expect($tagNames)->toContain('Laravel');
    expect($tagNames)->toContain('PHP');
});
