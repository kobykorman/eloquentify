<p align="center">
  <img src="./assets/logo.png" alt="Eloquentify Logo" width="700">
</p>

## Eloquentify for Laravel

👎 ~~Lazy Loading (N+1 queries)~~

😑 ~~Eager Loading (R+1 queries)~~

😎 Greedy Loading (1 query)

## Why Eloquentify?

### ⚡ Single Query: Replace N+1/R+1 queries with one efficient query
### 💯 Eloquent Models: Get real Eloquent models, not plain objects
### 🔗 Nested Relations: Support for complex hierarchies of any depth
### ✨ Clean API: Maintain Laravel's elegant syntax

Using Eloquent can be costly in terms of how many queries are fired behind the scenes when a model has many relationships. What if we could leverage the database for what it was meant for while retaining the Eloquent experience?

Eloquentify easily transforms the result of a single custom query into nested Eloquent models, so you can continue enjoying the Eloquent API and all of its benefits.

## Installation

```bash
composer require kobykorman/eloquentify
```

## Quick Start

### 1. Add the trait:

```php
// App\Models\Model
use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends BaseModel
{
    use EloquentifiesQueries;
}
```
### 2. Write the query:

```php
// App\Models\User
class User extends Model
{    
    public static function getById($id) 
    {
        // 1 custom query
        $result = DB::table('users')
        ->select('...')
        ->join('person...')
        ->join('team...')
        // ...
        ->where('id', $id)
        ->get()

```

### 3. Transform the result:

```php
    
        // feed the result and the relations hierarchy
        // and get them all properly hydrated and nested
        return User::eloquentify($result, [
            Person::class
            Team::class,
            Role::nest(Permission::nest(
                Resource::class,
                Ability::class
            ))
            Post::nest(Comment::class)
        ])
    }
}
```

### 4. Enjoy:

```php
// App\Controllers\UserController
$userName = User::getById(1)->person->name
```


## Requirements

- PHP 8.0 or higher
- Laravel 9.0 or higher

### Models:
- Must follow Laravel naming conventions (e.g. `User`, `Post`, `UserProfile` - not `user`, `Posts`, `user_profile`)
- Must be instantiatable without parameters (e.g. `new User()`, `new Post()` - not `new User($data)`)
- All relation methods must exist on the model (e.g. `User` model must have `posts()` method)
  - Relation method names must match either:
      - Singular method names are assumed to be "one" (e.g. `profile()`, `author()`, `category()`)
      - Plural method names are assumed to be "many" (e.g. `posts()`, `comments()`, `tags()`)

### Query:
- Must include all related models' primary keys and the columns for the desired attributes
- Related model columns must be snake_cased prefixed, e.g.:
  ```sql
  (SELECT users.id, users.name, posts.id AS post_id, posts.title AS post_title)
  ```
- Results must be provided as a Laravel Collection (```DB::table()...->get()```)

## License

This library is open-sourced software licensed under the [MIT license](LICENSE.md).
