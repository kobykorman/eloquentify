<?php

use KobyKorman\Eloquentify\ModelMeta;
use Tests\Models\User;
use Tests\Models\Post;
use Tests\Models\Comment;
use Tests\Models\Profile;

test('ModelMeta constructs with correct values', function () {
    $meta = new ModelMeta(User::class);

    expect($meta->model)->toBeInstanceOf(User::class);
});

test('ModelMeta idColumn returns correct column name', function () {
    $meta = new ModelMeta(User::class);

    expect($meta->idColumn())->toBe('user_id');
});

test('ModelMeta can be set as root', function () {
    $meta = new ModelMeta(User::class);
    $meta->setRoot();

    // Root model has empty prefix, so id column should be just 'id'
    expect($meta->idColumn())->toBe('id');
});

test('ModelMeta can nest relations', function () {
    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);

    $userMeta->nest($postMeta);

    expect($userMeta->relations)->toHaveCount(1);
    expect($userMeta->relations['posts'])->toBe($postMeta);
    expect($userMeta->relations['posts']->isRelationTypeMany)->toBeTrue();
});

test('ModelMeta detects singular relations correctly', function () {
    $userMeta = new ModelMeta(User::class);
    $profileMeta = new ModelMeta(Profile::class);

    $userMeta->nest($profileMeta);

    expect($userMeta->relations)->toHaveCount(1);
    expect($userMeta->relations['profile'])->toBe($profileMeta);
    expect($userMeta->relations['profile']->isRelationTypeMany)->toBeFalse();
});

test('ModelMeta recursively prefixes nested relations', function () {
    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);
    $commentMeta = new ModelMeta(Comment::class);

    $postMeta->nest($commentMeta);
    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    // Root model has no prefix
    expect($userMeta->idColumn())->toBe('id');

    // Posts should have user_ prefix since they're directly under user
    expect($postMeta->idColumn())->toBe('post_id');

    // Comments should have post_ prefix
    expect($commentMeta->idColumn())->toBe('post_comment_id');
});

test('ModelMeta isColumnAttribute identifies model attributes correctly', function () {
    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);

    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    // Root model attributes have no prefix
    expect($userMeta->isColumnAttribute('id'))->toBe('id');
    expect($userMeta->isColumnAttribute('name'))->toBe('name');

    // Related model attributes have prefixes
    expect($userMeta->isColumnAttribute('post_id'))->toBeFalse();
    expect($postMeta->isColumnAttribute('post_id'))->toBe('id');
    expect($postMeta->isColumnAttribute('post_title'))->toBe('title');
});

test('ModelMeta throws exception for invalid relation', function () {
    $userMeta = new ModelMeta(User::class);
    $invalidMeta = new class extends ModelMeta {
        public function __construct() {
            $this->model = new class extends \Illuminate\Database\Eloquent\Model {
                // This class has no name
            };
            $this->basename = 'Invalid';
            $this->prefix = 'invalid_';
        }
    };

    expect(fn() => $userMeta->nest($invalidMeta))->toThrow(LogicException::class);
});


test('ModelMeta handles models with custom primary keys', function () {
    // Create a test model class that we can control the name of
    $testModelClass = new class extends \Illuminate\Database\Eloquent\Model {
        protected $primaryKey = 'custom_id';
        public $timestamps = false;

        // Override the class basename method by creating a predictable class name
        public static function getClassBasename(): string {
            return 'CustomModel';
        }
    };

    // Create a custom ModelMeta that uses our controlled basename
    $meta = new class(get_class($testModelClass)) extends ModelMeta {
        public function __construct(string $class) {
            parent::__construct($class);
            $this->basename = 'CustomModel';
            $this->prefix = 'custom_model_';
        }
    };

    expect($meta->idColumn())->toBe('custom_model_custom_id');
});

test('ModelMeta throws exception when relation method does not exist on model', function () {
    $userMeta = new ModelMeta(User::class);

    // Create a model that won't have the expected relationship methods
    $invalidMeta = new class extends ModelMeta {
        public function __construct() {
            // Create a model that won't have the expected relationship
            $this->model = new class extends \Illuminate\Database\Eloquent\Model {
                protected $table = 'nonexistent';
                // No relationship methods defined
            };
            $this->basename = 'Nonexistent';
            $this->prefix = 'nonexistent_';
        }
    };

    expect(fn() => $userMeta->nest($invalidMeta))->toThrow(LogicException::class);
});

test('ModelMeta handles malformed column names gracefully', function () {
    $userMeta = new ModelMeta(User::class);
    $postMeta = new ModelMeta(Post::class);

    $userMeta->nest($postMeta);
    $userMeta->setRoot();

    // Test with weird column names that don't follow conventions
    expect($userMeta->isColumnAttribute('weird_column_name'))->toBe('weird_column_name');
    expect($userMeta->isColumnAttribute('post_weird_column'))->toBeFalse(); // Should belong to post
    expect($postMeta->isColumnAttribute('post_weird_column'))->toBe('weird_column');
    expect($postMeta->isColumnAttribute('completely_unrelated'))->toBeFalse();
});
