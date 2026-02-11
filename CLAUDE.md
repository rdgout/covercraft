<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.17
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== codecov application rules ===

# Codecov Coverage Tracker Application

This is a Codecov-style code coverage tracker built with Laravel 12 and SQLite. It receives clover.xml files from CI/CD pipelines, parses them asynchronously via queues, integrates with GitHub for file lists, and provides a dashboard for visualizing coverage.

**Implementation Plan**: `/Users/rickgout/.claude/plans/ethereal-dazzling-allen.md`

## Core Architecture

### Data Flow
1. **Upload**: POST `/api/coverage` with clover.xml → Store file → Create pending CoverageReport
2. **Processing**: Dispatch `ProcessCoverageJob` → Parse XML → Archive old reports → Store CoverageFile records
3. **Display**: Dashboard reads from database + RepositoryFileCache (not live GitHub calls)
4. **Webhook**: GitHub push events update RepositoryFileCache

### Key Components
- **CloverParser**: Parses clover.xml using `simplexml_load_file()`, counts only `stmt` type lines, handles division by zero
- **GitHubService**: Uses app-level `GITHUB_TOKEN`, fetches repo files, manages RepositoryFileCache, verifies webhook signatures
- **FileTreeBuilder**: Merges coverage with repo files, applies exclusion patterns, calculates directory coverage recursively
- **ProcessCoverageJob**: Archives previous reports in DB transaction, creates CoverageFile records with compressed line data

## Database Conventions

### Status Column Pattern
- Use `string` type for status columns, NOT `enum` (SQLite compatibility)
- Example: `'status' => 'pending'` | `'completed'` | `'failed'`

### Per-Repository Configuration
- Each repository has its own `default_branch` column (NOT a global config)
- Selected during repository creation from GitHub branch list
- All branch comparisons use `$repository->default_branch`

### Binary Data Storage
- Use `gzcompress()` for storing large JSON data in binary columns
- Example: `'line_coverage_data' => gzcompress(json_encode($lineData))`
- Decompress with accessor: `gzuncompress($this->attributes['line_coverage_data'])`

### Model Casts
- Use `casts()` method (NOT `$casts` property) - matches Laravel 12 convention
- Example from CoverageReport model:
```php
protected function casts(): array
{
    return [
        'coverage_percentage' => 'decimal:2',
        'archived' => 'boolean',
        'archived_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
```

## Service Patterns

### Parser Services
- Return typed arrays with PHPDoc shape annotations
- Handle edge cases (division by zero, empty files, malformed input)
- Throw custom exceptions for error conditions
- Example: `CloverParser::parse()` returns array with keys: `overall_percentage`, `total_lines`, `covered_lines`, `files`

### GitHub Integration
- Use single app-level `config('coverage.github_token')` for all API calls (NOT per-repo tokens)
- Cache file lists in `RepositoryFileCache` table to avoid rate limits
- Verify webhook signatures with HMAC-SHA256
- Retry on 429 (rate limit) with exponential backoff
- Methods: `listUserRepositories()`, `listBranches()`, `fetchRepositoryFiles()`, `getOrFetchRepositoryFiles()`

### File Tree Building
- Merge coverage data with repository file lists
- Apply exclusion patterns from `config('coverage.exclude_patterns')`
- Calculate directory coverage recursively (aggregate child file/directory coverage)
- Return hierarchical array structure for view rendering

## Job Patterns

### Queue Job Structure
- Use constructor property promotion: `public function __construct(public int $coverageReportId) {}`
- Implement `ShouldQueue` interface
- Use `Queueable` trait
- Type-hint dependencies in `handle()` method (resolved via DI)

### Transaction Usage
- Wrap multi-step database operations in `DB::transaction()`
- Archive previous reports atomically with creating new records
- Example pattern from ProcessCoverageJob:
```php
DB::transaction(function () use ($report, $coverageData): void {
    // Archive old reports
    CoverageReport::query()
        ->where('repository_id', $report->repository_id)
        ->where('branch', $report->branch)
        ->where('id', '!=', $report->id)
        ->where('archived', false)
        ->update(['archived' => true, 'archived_at' => now()]);

    // Update current report
    $report->update([...]);

    // Create related records
    foreach ($coverageData['files'] as $fileData) {
        $report->files()->create([...]);
    }
});
```

### Failed Job Handling
- Always implement `failed(Throwable $exception)` method
- Update status to 'failed' and store error message
- Handle case where model might not exist (use `find()` not `findOrFail()`)

## Form Request Patterns

### Validation Rules
- Use array-based validation rules: `['required', 'string', 'max:255']`
- Always include custom error messages in `messages()` method
- Return proper PHPDoc types for `rules()` and `messages()`

### Custom Validation Messages
```php
public function messages(): array
{
    return [
        'clover_file.required' => 'The clover.xml file is required',
        'clover_file.file' => 'The clover file must be a valid file upload',
        'commit_sha.size' => 'The commit SHA must be 40 characters',
    ];
}
```

## Factory Patterns

### Faker Helper
- Use `fake()` helper (NOT `$this->faker`) - matches existing UserFactory convention
- Examples: `fake()->userName()`, `fake()->slug(2)`, `fake()->sha256()`

### State Methods
- Create chainable state methods for common variations
- Use arrow functions for state: `fn (array $attributes) => [...]`
- Examples: `withWebhookSecret()`, `withDefaultBranch(string $branch)`, `pending()`, `archived()`

### Auto-Creating Related Models
- Factories auto-create parent relationships (e.g., CoverageReportFactory creates Repository)
- This simplifies test setup: `CoverageReport::factory()->create()` gives you a full hierarchy

## Testing Patterns

### Property-Based Testing
- Create property tests in `tests/Feature/Properties/` directory
- Run 100 iterations per property test using `for ($i = 0; $i < 100; $i++)`
- Log seed for reproducibility: `$seed = rand(0, PHP_INT_MAX); srand($seed);`
- Include seed in assertion messages: `"Seed: {$seed}, iteration: {$i}"`
- Document property being tested in PHPDoc block
- Clean up temp files: `unlink($path)` after each iteration

Example property test structure:
```php
/**
 * Property 4: Complete File Path Extraction
 *
 * For any valid clover.xml file, the parser should extract all file paths
 * present in the XML, with no files omitted from the parsed output.
 */
public function test_property_4_complete_file_path_extraction(): void
{
    $seed = rand(0, PHP_INT_MAX);
    srand($seed);

    for ($i = 0; $i < 100; $i++) {
        // Generate random test data
        // Run test
        // Assert with seed info
        $this->assertEquals($expected, $actual, "Seed: {$seed}, iteration: {$i}");
    }
}
```

### Feature Test Organization
- Group tests by domain: `Models/`, `Api/`, `Jobs/`, `Services/`, `Dashboard/`, `Repositories/`, `Webhooks/`, `Integration/`, `ErrorHandling/`
- Test relationships and cascade deletes in model tests
- Use `Http::fake()` for testing external API calls (GitHub)
- Test both success and failure paths for jobs
- Create end-to-end integration tests for complete workflows

### Test Database
- Use `RefreshDatabase` trait in feature tests
- Use factories for all model creation in tests
- Check for factory states before manually setting up models

## PHPDoc Patterns

### Array Shape Documentation
- Document return types with detailed array shapes
- Use generics for collections: `@param Builder<CoverageReport> $query`
- Document factory generics: `@use HasFactory<RepositoryFactory>`

Examples:
```php
/**
 * @return array{overall_percentage: float, total_lines: int, covered_lines: int, files: list<array{path: string, total_lines: int, covered_lines: int, percentage: float, lines: array<int, array{covered: bool, count: int}>}>}
 */
public function parse(string $filePath): array

/**
 * @param Builder<CoverageReport> $query
 * @return Builder<CoverageReport>
 */
public function scopeCurrent(Builder $query): Builder
```

## Routing Patterns

### Branch Names with Slashes
- Use regex constraint for branch parameters: `->where('branch', '.*')`
- Order routes carefully: more specific routes (with `/file`) before general ones
```php
Route::get('/dashboard/{repository}/{branch}/file', ...)->where('branch', '.*');
Route::get('/dashboard/{repository}/{branch}', ...)->where('branch', '.*');
```

### Route Naming
- Use dot notation: `dashboard.repository`, `dashboard.branch`, `dashboard.file`
- API routes: `api.coverage.store`, `api.coverage.status`

### Root Route
- Root `/` redirects to `/dashboard` (not welcome page)

## Configuration Patterns

### Coverage Config
- `config/coverage.php` contains GitHub token, API URL, exclusion patterns, storage disk
- Access with `config('coverage.github_token')`, never `env('GITHUB_TOKEN')` outside config

### Storage
- Use configured disk: `Storage::disk(config('coverage.storage_disk'))`
- Get absolute path: `Storage::disk(...)->path($relativePath)`

## Dashboard Patterns

### Repository File Lists
- Read from `RepositoryFileCache` (NOT live GitHub calls) for performance
- Cache is updated via webhook on push events
- Cache is also populated during repository creation via `fetchBranches()` AJAX call

### Coverage Comparison
- Always compare branches against `$repository->default_branch`
- Display coverage percentage with color coding (high = green, medium = yellow, low = red)
- Show file tree with hierarchical directory structure

### Line-by-Line Display
- Decompress line coverage data: `json_decode(gzuncompress($data))`
- Highlight covered lines (green) vs uncovered lines (red)
- Display hit count for each line
</laravel-boost-guidelines>
