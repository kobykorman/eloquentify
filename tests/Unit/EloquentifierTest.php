<?php

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use KobyKorman\Eloquentify\Eloquentifier;
use KobyKorman\Eloquentify\ModelMeta;
use Tests\Models\User;
use Tests\Models\Post;
use Tests\Models\Comment;

test('Eloquentifier transforms simple models correctly', function () {
    // Create a simple flat result of users
    $result = createQueryResult([
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ]);

    $userMeta = new ModelMeta(User::class);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();
    $users = $eloquentifier->transform($result, $userMeta);

    expect($users)->toBeInstanceOf(EloquentCollection::class);
    expect($users)->toHaveCount(2);
    expect($users[0])->toBeInstanceOf(User::class);
    expect($users[0]->name)->toBe('John Doe');
    expect($users[1]->name)->toBe('Jane Smith');
});

test('Eloquentifier transforms models with one-to-many relations', function () {
    // Create a flat result of users with posts
    $result = createQueryResult([
        ['id' => 1, 'name' => 'John', 'post_id' => 10, 'post_title' => 'First Post', 'post_content' => 'Content 1'],
        ['id' => 1, 'name' => 'John', 'post_id' => 11, 'post_title' => 'Second Post', 'post_content' => 'Content 2'],
        ['id' => 2, 'name' => 'Jane', 'post_id' => 12, 'post_title' => 'Jane\'s Post', 'post_content' => 'Content 3'],
    ]);

    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);

    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();
    $users = $eloquentifier->transform($result, $userMeta);

    expect($users)->toHaveCount(2);

    // First user should have 2 posts
    expect($users[0]->name)->toBe('John');
    expect($users[0]->posts)->toBeInstanceOf(EloquentCollection::class);
    expect($users[0]->posts)->toHaveCount(2);
    expect($users[0]->posts[0]->title)->toBe('First Post');
    expect($users[0]->posts[1]->title)->toBe('Second Post');

    // Second user should have 1 post
    expect($users[1]->name)->toBe('Jane');
    expect($users[1]->posts)->toHaveCount(1);
    expect($users[1]->posts[0]->title)->toBe('Jane\'s Post');
});

test('Eloquentifier transforms models with one-to-one relations', function () {
    // Create a flat result of users with profiles
    $result = createQueryResult([
        ['id' => 1, 'name' => 'John', 'profile_id' => 100, 'profile_bio' => 'John\'s bio', 'profile_avatar' => 'john.jpg'],
        ['id' => 2, 'name' => 'Jane', 'profile_id' => 101, 'profile_bio' => 'Jane\'s bio', 'profile_avatar' => 'jane.jpg'],
    ]);

    $userMeta = new ModelMeta(User::class);
    $profileMeta = new ModelMeta(Tests\Models\Profile::class);

    $userMeta->nest($profileMeta);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();
    $users = $eloquentifier->transform($result, $userMeta);

    expect($users)->toHaveCount(2);

    // First user should have a profile
    expect($users[0]->name)->toBe('John');
    expect($users[0]->profile)->toBeInstanceOf(Tests\Models\Profile::class);
    expect($users[0]->profile->bio)->toBe('John\'s bio');

    // Second user should have a profile
    expect($users[1]->name)->toBe('Jane');
    expect($users[1]->profile->bio)->toBe('Jane\'s bio');
});

test('Eloquentifier transforms nested relations correctly', function () {
    // Create a flat result with users, posts, and comments
    $result = createQueryResult([
        [
            'id' => 1, 
            'name' => 'John', 
            'post_id' => 10, 
            'post_title' => 'First Post', 
            'post_comment_id' => 100, 
            'post_comment_body' => 'First comment'
        ],
        [
            'id' => 1, 
            'name' => 'John', 
            'post_id' => 10, 
            'post_title' => 'First Post', 
            'post_comment_id' => 101, 
            'post_comment_body' => 'Second comment'
        ],
        [
            'id' => 1, 
            'name' => 'John', 
            'post_id' => 11, 
            'post_title' => 'Second Post', 
            'post_comment_id' => 102, 
            'post_comment_body' => 'Third comment'
        ],
    ]);

    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);
    $commentMeta = new ModelMeta(Comment::class);

    $postMeta->nest($commentMeta);
    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();
    $users = $eloquentifier->transform($result, $userMeta);

    expect($users)->toHaveCount(1);
    expect($users[0]->posts)->toHaveCount(2);

    // First post should have 2 comments
    expect($users[0]->posts[0]->title)->toBe('First Post');
    expect($users[0]->posts[0]->comments)->toHaveCount(2);
    expect($users[0]->posts[0]->comments[0]->body)->toBe('First comment');
    expect($users[0]->posts[0]->comments[1]->body)->toBe('Second comment');

    // Second post should have 1 comment
    expect($users[0]->posts[1]->title)->toBe('Second Post');
    expect($users[0]->posts[1]->comments)->toHaveCount(1);
    expect($users[0]->posts[1]->comments[0]->body)->toBe('Third comment');
});

test('Eloquentifier handles null relations (left joins) correctly', function () {
    // Create a flat result with users and optional posts (some users don't have posts)
    $result = createQueryResult([
        ['id' => 1, 'name' => 'John', 'post_id' => 10, 'post_title' => 'John\'s Post'],
        ['id' => 2, 'name' => 'Jane', 'post_id' => null, 'post_title' => null], // No post
        ['id' => 3, 'name' => 'Bob', 'post_id' => 11, 'post_title' => 'Bob\'s Post'],
    ]);

    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);

    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();
    $users = $eloquentifier->transform($result, $userMeta);

    expect($users)->toHaveCount(3);

    // First user has a post
    expect($users[0]->name)->toBe('John');
    expect($users[0]->posts)->toHaveCount(1);

    // Second user has no posts
    expect($users[1]->name)->toBe('Jane');
    expect($users[1]->posts)->toHaveCount(0);

    // Third user has a post
    expect($users[2]->name)->toBe('Bob');
    expect($users[2]->posts)->toHaveCount(1);
});

test('Eloquentifier throws exception for circular relationships', function () {
    expect(function() {
        $meta1 = new ModelMeta(User::class);
        $meta2 = new ModelMeta(Post::class);

        // Create a circular reference
        $meta1->nest($meta2);
        $meta2->nest($meta1); // This creates a circular reference

        // This should throw a LogicException due to circular reference
        $meta1->setRoot();
    })->toThrow(LogicException::class);
});

test('Eloquentifier handles completely empty result collections', function () {
    $result = collect();

    $userMeta = new ModelMeta(User::class);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();
    $users = $eloquentifier->transform($result, $userMeta);

    expect($users)->toBeInstanceOf(EloquentCollection::class);
    expect($users)->toBeEmpty();
});

test('Eloquentifier handles results with only null relation columns', function () {
    $result = createQueryResult([
        ['id' => 1, 'name' => 'John', 'post_id' => null, 'post_title' => null],
        ['id' => 2, 'name' => 'Jane', 'post_id' => null, 'post_title' => null],
    ]);

    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);

    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();
    $users = $eloquentifier->transform($result, $userMeta);

    expect($users)->toHaveCount(2);
    expect($users[0]->posts)->toHaveCount(0);
    expect($users[1]->posts)->toHaveCount(0);
});

test('Eloquentifier handles mixed null and non-null relations in same result set', function () {
    $result = createQueryResult([
        ['id' => 1, 'name' => 'John', 'post_id' => 10, 'post_title' => 'John Post'],
        ['id' => 1, 'name' => 'John', 'post_id' => 11, 'post_title' => 'John Post 2'],
        ['id' => 2, 'name' => 'Jane', 'post_id' => null, 'post_title' => null],
        ['id' => 3, 'name' => 'Bob', 'post_id' => 12, 'post_title' => 'Bob Post'],
    ]);

    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);

    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();
    $users = $eloquentifier->transform($result, $userMeta);

    expect($users)->toHaveCount(3);
    expect($users[0]->posts)->toHaveCount(2); // John has 2 posts
    expect($users[1]->posts)->toHaveCount(0); // Jane has no posts
    expect($users[2]->posts)->toHaveCount(1); // Bob has 1 post
});

test('Eloquentifier preserves model attributes correctly without mixing relations', function () {
    $result = createQueryResult([
        [
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
            'post_id' => 10,
            'post_title' => 'Post Title',
            'post_content' => 'Post Content',
            'post_user_id' => 1, // This should not interfere with user attributes
        ]
    ]);

    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);

    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();
    $users = $eloquentifier->transform($result, $userMeta);

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('John');
    expect($users[0]->email)->toBe('john@example.com');
    expect($users[0]->posts[0]->title)->toBe('Post Title');
    expect($users[0]->posts[0]->content)->toBe('Post Content');

    // User should not have post-related attributes
    expect($users[0]->getAttributes())->not->toHaveKey('post_id');
    expect($users[0]->getAttributes())->not->toHaveKey('post_title');
});

test('Eloquentifier handles large datasets efficiently', function () {
    // Generate 1000 rows of test data
    $data = [];
    for ($i = 1; $i <= 1000; $i++) {
        $data[] = [
            'id' => $i,
            'name' => "User $i",
            'email' => "user$i@example.com",
            'post_id' => $i,
            'post_title' => "Post $i",
            'post_content' => "Content $i"
        ];
    }

    $result = createQueryResult($data);

    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);

    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    $eloquentifier = new Eloquentifier();

    $startTime = microtime(true);
    $users = $eloquentifier->transform($result, $userMeta);
    $duration = microtime(true) - $startTime;

    expect($users)->toHaveCount(1000);
    expect($duration)->toBeLessThan(1.0); // Should complete in under 1 second
});
