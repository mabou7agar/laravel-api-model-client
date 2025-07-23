# Laravel API Model Relations Demo Application

This demo application showcases the Laravel API Model Relations package. It demonstrates how to use the package to interact with external APIs using Eloquent-like models, including relationships, caching, error handling, and more.

## Installation

1. Clone the repository:

```bash
git clone https://github.com/api-model-relations/demo.git
cd demo
```

2. Install dependencies:

```bash
composer install
```

3. Copy the environment file:

```bash
cp .env.example .env
```

4. Generate an application key:

```bash
php artisan key:generate
```

5. Configure the API endpoints in the `.env` file:

```
API_MODEL_RELATIONS_BASE_URL=https://jsonplaceholder.typicode.com
API_MODEL_RELATIONS_AUTH_STRATEGY=none
API_MODEL_RELATIONS_DEBUG=true
```

6. Run the application:

```bash
php artisan serve
```

7. Visit the application in your browser at `http://localhost:8000`.

## Demo Features

This demo application showcases the following features:

- **API Models**: Interact with JSONPlaceholder API using Eloquent-like models
- **API Relationships**: Define and use relationships between API resources
- **Caching**: Cache API responses with configurable TTL and strategies
- **Query Builder**: Use Eloquent-like query builder methods for API queries
- **Pagination**: Handle paginated API responses like Eloquent collections
- **Error Handling**: Comprehensive error handling and logging for API failures
- **Debugging**: Debug API calls with the built-in debugging UI

## Demo Routes

- `/`: Home page with links to demo features
- `/users`: List of users from the API
- `/users/{id}`: User details with related posts and todos
- `/posts`: List of posts from the API
- `/posts/{id}`: Post details with related user and comments
- `/todos`: List of todos from the API
- `/albums`: List of albums from the API
- `/photos`: List of photos from the API
- `/debug`: API debugging dashboard

## Demo Models

The demo includes the following API models:

- `User`: Represents a user from the API
- `Post`: Represents a post from the API
- `Comment`: Represents a comment from the API
- `Todo`: Represents a todo from the API
- `Album`: Represents an album from the API
- `Photo`: Represents a photo from the API

## Demo Controllers

The demo includes the following controllers:

- `UserController`: Handles user-related routes
- `PostController`: Handles post-related routes
- `TodoController`: Handles todo-related routes
- `AlbumController`: Handles album-related routes
- `PhotoController`: Handles photo-related routes

## Demo Views

The demo includes views for all the above routes, showcasing how to use the API models in Blade templates.

## Learn More

For more information about the Laravel API Model Relations package, check out the [documentation](https://github.com/api-model-relations/laravel-api-model-relations).
