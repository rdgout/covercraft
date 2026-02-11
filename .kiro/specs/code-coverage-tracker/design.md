# Design Document: Code Coverage Tracker

## Overview

The Code Coverage Tracker is a Laravel 12 application that provides automated tracking and visualization of PHPUnit code coverage data. The system receives clover.xml files from CI/CD pipelines, parses them asynchronously, integrates with GitHub for repository file management, and presents coverage data through a Codecov-style dashboard with file tree navigation and line-by-line coverage viewing.

### Key Design Principles

1. **Asynchronous Processing**: Coverage parsing happens in background jobs to ensure fast API responses
2. **Scalable Storage**: Line coverage stored as compressed JSON to handle large codebases efficiently
3. **Cached File Lists**: Repository files cached to avoid GitHub API rate limits and improve performance
4. **Single Source of Truth**: One current coverage report per branch, with archived historical data for trends
5. **Comparison Against Main**: All branch comparisons reference the main branch for consistency

## Architecture

### System Components

```
┌─────────────────┐
│   CI/CD Pipeline │
└────────┬────────┘
         │ POST clover.xml
         ▼
┌─────────────────────────────────────────────────────────┐
│                     Laravel Application                  │
│                                                          │
│  ┌──────────────┐    ┌──────────────┐   ┌────────────┐ │
│  │ Coverage API │───▶│ Queue System │──▶│ Parser Job │ │
│  └──────────────┘    └──────────────┘   └─────┬──────┘ │
│                                                 │        │
│  ┌──────────────┐    ┌──────────────┐         │        │
│  │   Dashboard  │◀───│   Database   │◀────────┘        │
│  └──────────────┘    └──────────────┘                   │
│         ▲                                                │
│         │            ┌──────────────┐                    │
│         └────────────│ GitHub API   │                    │
│                      └──────┬───────┘                    │
└─────────────────────────────┼──────────────────────────┘
                              │
                    ┌─────────▼────────┐
                    │  GitHub Webhooks │
                    └──────────────────┘
```

### Technology Stack

- **Framework**: Laravel 12 (PHP 8.4)
- **Database**: MySQL/PostgreSQL with indexed queries
- **Queue System**: Laravel Queues (Redis/Database driver)
- **Storage**: Laravel Storage for clover.xml files
- **GitHub Integration**: GitHub REST API v3
- **Frontend**: Blade templates with Tailwind CSS (or Livewire for interactivity)
- **XML Parsing**: PHP's SimpleXML or DOMDocument

## Components and Interfaces

### 1. Coverage API Controller

**Responsibility**: Receive coverage submissions from CI/CD pipelines

**Endpoints**:

```php
POST /api/coverage
Request:
{
  "repository": "owner/repo-name",
  "branch": "feature-branch",
  "commit_sha": "abc123...",
  "clover_file": <multipart file upload>
}

Response (202 Accepted):
{
  "status": "queued",
  "job_id": "uuid-here",
  "message": "Coverage processing queued"
}

Response (422 Validation Error):
{
  "error": "Validation failed",
  "details": {
    "clover_file": ["The clover file is required"],
    "branch": ["The branch field is required"]
  }
}
```

```php
GET /api/coverage/status/{job_id}
Response:
{
  "status": "completed|pending|failed",
  "coverage_report_id": 123,
  "error": "Error message if failed"
}
```

**Implementation**:

```php
class CoverageController extends Controller
{
    public function store(StoreCoverageRequest $request): JsonResponse
    {
        // Validate request
        $validated = $request->validated();
        
        // Store clover.xml file
        $filename = $this->generateUniqueFilename(
            $validated['repository'],
            $validated['branch'],
            $validated['commit_sha']
        );
        $path = $request->file('clover_file')->storeAs('coverage', $filename);
        
        // Create pending coverage report
        $report = CoverageReport::create([
            'repository_id' => $this->getOrCreateRepository($validated['repository']),
            'branch' => $validated['branch'],
            'commit_sha' => $validated['commit_sha'],
            'clover_file_path' => $path,
            'status' => 'pending',
        ]);
        
        // Dispatch job
        $job = new ProcessCoverageJob($report->id);
        dispatch($job);
        
        return response()->json([
            'status' => 'queued',
            'job_id' => $job->getJobId(),
            'message' => 'Coverage processing queued',
        ], 202);
    }
    
    private function generateUniqueFilename(string $repo, string $branch, string $commit): string
    {
        $timestamp = now()->format('YmdHis');
        $sanitizedRepo = str_replace('/', '_', $repo);
        $sanitizedBranch = str_replace('/', '_', $branch);
        $shortCommit = substr($commit, 0, 8);
        
        return "{$sanitizedRepo}_{$sanitizedBranch}_{$shortCommit}_{$timestamp}.xml";
    }
}
```

### 2. Coverage Parser Job

**Responsibility**: Parse clover.xml files and store structured coverage data

**Implementation**:

```php
class ProcessCoverageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public int $coverageReportId
    ) {}
    
    public function handle(CloverParser $parser, GitHubService $github): void
    {
        $report = CoverageReport::findOrFail($this->coverageReportId);
        
        try {
            // Parse clover.xml
            $cloverPath = Storage::path($report->clover_file_path);
            $coverageData = $parser->parse($cloverPath);
            
            // Fetch repository files from GitHub (uses cache if available)
            $repoFiles = $github->getOrFetchRepositoryFiles(
                $report->repository,
                $report->branch,
                $report->commit_sha
            );
            
            // Store coverage data
            DB::transaction(function () use ($report, $coverageData, $repoFiles) {
                // Archive previous report for this branch
                $this->archivePreviousReport($report);
                
                // Update report with coverage percentage
                $report->update([
                    'coverage_percentage' => $coverageData['overall_percentage'],
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                
                // Store file coverage
                foreach ($coverageData['files'] as $fileData) {
                    CoverageFile::create([
                        'coverage_report_id' => $report->id,
                        'file_path' => $fileData['path'],
                        'total_lines' => $fileData['total_lines'],
                        'covered_lines' => $fileData['covered_lines'],
                        'coverage_percentage' => $fileData['percentage'],
                        'line_coverage_data' => gzcompress(json_encode($fileData['lines'])),
                    ]);
                }
            });
            
        } catch (Exception $e) {
            Log::error('Coverage processing failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    private function archivePreviousReport(CoverageReport $newReport): void
    {
        CoverageReport::where('repository_id', $newReport->repository_id)
            ->where('branch', $newReport->branch)
            ->where('id', '!=', $newReport->id)
            ->where('archived', false)
            ->update(['archived' => true, 'archived_at' => now()]);
    }
}
```

### 3. Clover XML Parser

**Responsibility**: Parse clover.xml format into structured data

**Implementation**:

```php
class CloverParser
{
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException("Clover file not found: {$filePath}");
        }
        
        $xml = simplexml_load_file($filePath);
        
        if ($xml === false) {
            throw new InvalidCloverFormatException("Failed to parse XML file");
        }
        
        $files = [];
        $totalLines = 0;
        $coveredLines = 0;
        
        // Parse project metrics
        foreach ($xml->xpath('//file') as $fileNode) {
            $fileData = $this->parseFile($fileNode);
            $files[] = $fileData;
            $totalLines += $fileData['total_lines'];
            $coveredLines += $fileData['covered_lines'];
        }
        
        return [
            'overall_percentage' => $totalLines > 0 
                ? round(($coveredLines / $totalLines) * 100, 2) 
                : 0.0,
            'total_lines' => $totalLines,
            'covered_lines' => $coveredLines,
            'files' => $files,
        ];
    }
    
    private function parseFile(SimpleXMLElement $fileNode): array
    {
        $filePath = (string) $fileNode['name'];
        $lines = [];
        $totalLines = 0;
        $coveredLines = 0;
        
        foreach ($fileNode->line as $lineNode) {
            $lineNum = (int) $lineNode['num'];
            $type = (string) $lineNode['type'];
            $count = (int) $lineNode['count'];
            
            // Only count statement lines
            if ($type === 'stmt') {
                $totalLines++;
                $isCovered = $count > 0;
                
                if ($isCovered) {
                    $coveredLines++;
                }
                
                $lines[$lineNum] = [
                    'covered' => $isCovered,
                    'count' => $count,
                ];
            }
        }
        
        return [
            'path' => $filePath,
            'total_lines' => $totalLines,
            'covered_lines' => $coveredLines,
            'percentage' => $totalLines > 0 
                ? round(($coveredLines / $totalLines) * 100, 2) 
                : 0.0,
            'lines' => $lines,
        ];
    }
}
```

### 4. GitHub Service

**Responsibility**: Interact with GitHub API for repository data and webhooks

**Implementation**:

```php
class GitHubService
{
    public function __construct(
        private HttpClient $client,
        private string $apiUrl = 'https://api.github.com'
    ) {}
    
    public function fetchRepositoryFiles(Repository $repository, string $commitSha): array
    {
        $url = "{$this->apiUrl}/repos/{$repository->owner}/{$repository->name}/git/trees/{$commitSha}";
        
        $response = $this->client->get($url, [
            'headers' => [
                'Authorization' => "Bearer {$repository->access_token}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'query' => ['recursive' => 1],
        ]);
        
        if ($response->failed()) {
            throw new GitHubApiException(
                "Failed to fetch repository files: " . $response->body()
            );
        }
        
        $data = $response->json();
        
        // Filter to only include blob (file) entries
        return collect($data['tree'])
            ->filter(fn($item) => $item['type'] === 'blob')
            ->pluck('path')
            ->toArray();
    }
    
    public function getOrFetchRepositoryFiles(Repository $repository, string $branch, string $commitSha): array
    {
        // Check if we have cached files for this exact commit
        $cache = RepositoryFileCache::where('repository_id', $repository->id)
            ->where('branch', $branch)
            ->where('commit_sha', $commitSha)
            ->first();
        
        if ($cache) {
            // Cache hit - return cached files without fetching from GitHub
            return $cache->files;
        }
        
        // Cache miss - fetch from GitHub
        $files = $this->fetchRepositoryFiles($repository, $commitSha);
        
        // Store in cache
        RepositoryFileCache::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'branch' => $branch,
            ],
            [
                'commit_sha' => $commitSha,
                'files' => $files,
                'cached_at' => now(),
            ]
        );
        
        return $files;
    }
    
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
    
    public function handlePushWebhook(array $payload): void
    {
        $repository = Repository::where('owner', $payload['repository']['owner']['login'])
            ->where('name', $payload['repository']['name'])
            ->firstOrFail();
        
        $branch = str_replace('refs/heads/', '', $payload['ref']);
        $commitSha = $payload['after'];
        
        // Always fetch and update cache for webhook events (branch was updated)
        $files = $this->fetchRepositoryFiles($repository, $commitSha);
        
        RepositoryFileCache::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'branch' => $branch,
            ],
            [
                'commit_sha' => $commitSha,
                'files' => $files,
                'cached_at' => now(),
            ]
        );
    }
}
```

### 5. Dashboard Controller

**Responsibility**: Serve coverage visualization pages

**Routes and Methods**:

```php
// routes/web.php
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/{repository}', [DashboardController::class, 'repository'])->name('dashboard.repository');
Route::get('/dashboard/{repository}/{branch}', [DashboardController::class, 'branch'])->name('dashboard.branch');
Route::get('/dashboard/{repository}/{branch}/file', [DashboardController::class, 'file'])->name('dashboard.file');
```

**Implementation**:

```php
class DashboardController extends Controller
{
    public function index()
    {
        $repositories = Repository::withCount('coverageReports')
            ->with(['latestCoverageReport'])
            ->get();
        
        return view('dashboard.index', compact('repositories'));
    }
    
    public function repository(Repository $repository)
    {
        $branches = CoverageReport::where('repository_id', $repository->id)
            ->where('archived', false)
            ->with('repository')
            ->get()
            ->groupBy('branch');
        
        return view('dashboard.repository', compact('repository', 'branches'));
    }
    
    public function branch(Repository $repository, string $branch)
    {
        $report = CoverageReport::where('repository_id', $repository->id)
            ->where('branch', $branch)
            ->where('archived', false)
            ->with(['files', 'repository'])
            ->firstOrFail();
        
        // Get main branch coverage for comparison
        $mainReport = null;
        if ($branch !== 'main') {
            $mainReport = CoverageReport::where('repository_id', $repository->id)
                ->where('branch', 'main')
                ->where('archived', false)
                ->first();
        }
        
        // Get cached repository files from database
        $cache = RepositoryFileCache::where('repository_id', $repository->id)
            ->where('branch', $branch)
            ->firstOrFail();
        
        // Build file tree
        $fileTree = $this->buildFileTree($report, $cache->files);
        
        return view('dashboard.branch', compact('report', 'mainReport', 'fileTree'));
    }
    
    public function file(Repository $repository, string $branch, Request $request)
    {
        $filePath = $request->query('path');
        
        $report = CoverageReport::where('repository_id', $repository->id)
            ->where('branch', $branch)
            ->where('archived', false)
            ->firstOrFail();
        
        $file = CoverageFile::where('coverage_report_id', $report->id)
            ->where('file_path', $filePath)
            ->firstOrFail();
        
        // Decompress line coverage data
        $lineCoverage = json_decode(gzuncompress($file->line_coverage_data), true);
        
        // Fetch file content from GitHub
        $fileContent = $this->fetchFileContent($repository, $report->commit_sha, $filePath);
        
        return view('dashboard.file', compact('file', 'lineCoverage', 'fileContent'));
    }
    
    private function buildFileTree(CoverageReport $report, array $allFiles): array
    {
        $tree = [];
        $coveredFiles = $report->files->keyBy('file_path');
        
        foreach ($allFiles as $filePath) {
            $coverage = $coveredFiles->get($filePath);
            
            $parts = explode('/', $filePath);
            $current = &$tree;
            
            foreach ($parts as $index => $part) {
                if ($index === count($parts) - 1) {
                    // Leaf node (file)
                    $current[$part] = [
                        'type' => 'file',
                        'path' => $filePath,
                        'coverage' => $coverage?->coverage_percentage ?? 0.0,
                        'covered' => $coverage !== null,
                    ];
                } else {
                    // Directory node
                    if (!isset($current[$part])) {
                        $current[$part] = ['type' => 'directory', 'children' => []];
                    }
                    $current = &$current[$part]['children'];
                }
            }
        }
        
        return $tree;
    }
}
```

## Data Models

### Database Schema

```php
// Migration: create_repositories_table
Schema::create('repositories', function (Blueprint $table) {
    $table->id();
    $table->string('owner');
    $table->string('name');
    $table->string('github_url');
    $table->text('access_token')->nullable();
    $table->string('webhook_secret')->nullable();
    $table->timestamps();
    
    $table->unique(['owner', 'name']);
});

// Migration: create_coverage_reports_table
Schema::create('coverage_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
    $table->string('branch');
    $table->string('commit_sha');
    $table->decimal('coverage_percentage', 5, 2)->nullable();
    $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
    $table->text('error_message')->nullable();
    $table->string('clover_file_path');
    $table->boolean('archived')->default(false);
    $table->timestamp('archived_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    
    $table->index(['repository_id', 'branch', 'archived']);
    $table->index(['repository_id', 'created_at']);
});

// Migration: create_coverage_files_table
Schema::create('coverage_files', function (Blueprint $table) {
    $table->id();
    $table->foreignId('coverage_report_id')->constrained()->cascadeOnDelete();
    $table->string('file_path', 500);
    $table->integer('total_lines');
    $table->integer('covered_lines');
    $table->decimal('coverage_percentage', 5, 2);
    $table->binary('line_coverage_data'); // Compressed JSON
    $table->timestamps();
    
    $table->index(['coverage_report_id', 'file_path']);
});

// Migration: create_repository_file_cache_table
Schema::create('repository_file_cache', function (Blueprint $table) {
    $table->id();
    $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
    $table->string('branch');
    $table->string('commit_sha');
    $table->json('files'); // Array of file paths
    $table->timestamp('cached_at');
    $table->timestamps();
    
    $table->unique(['repository_id', 'branch']);
});
```

### Eloquent Models

```php
class Repository extends Model
{
    protected $fillable = ['owner', 'name', 'github_url', 'access_token', 'webhook_secret'];
    
    protected $hidden = ['access_token', 'webhook_secret'];
    
    public function coverageReports(): HasMany
    {
        return $this->hasMany(CoverageReport::class);
    }
    
    public function latestCoverageReport(): HasOne
    {
        return $this->hasOne(CoverageReport::class)
            ->where('archived', false)
            ->latest();
    }
    
    public function fileCache(): HasMany
    {
        return $this->hasMany(RepositoryFileCache::class);
    }
}

class CoverageReport extends Model
{
    protected $fillable = [
        'repository_id', 'branch', 'commit_sha', 'coverage_percentage',
        'status', 'error_message', 'clover_file_path', 'archived', 
        'archived_at', 'completed_at'
    ];
    
    protected $casts = [
        'coverage_percentage' => 'decimal:2',
        'archived' => 'boolean',
        'archived_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
    
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
    
    public function files(): HasMany
    {
        return $this->hasMany(CoverageFile::class);
    }
}

class CoverageFile extends Model
{
    protected $fillable = [
        'coverage_report_id', 'file_path', 'total_lines', 
        'covered_lines', 'coverage_percentage', 'line_coverage_data'
    ];
    
    protected $casts = [
        'coverage_percentage' => 'decimal:2',
    ];
    
    public function coverageReport(): BelongsTo
    {
        return $this->belongsTo(CoverageReport::class);
    }
    
    public function getLineCoverageAttribute(): array
    {
        return json_decode(gzuncompress($this->line_coverage_data), true);
    }
}

class RepositoryFileCache extends Model
{
    protected $table = 'repository_file_cache';
    
    protected $fillable = [
        'repository_id', 'branch', 'commit_sha', 'files', 'cached_at'
    ];
    
    protected $casts = [
        'files' => 'array',
        'cached_at' => 'datetime',
    ];
    
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: API Request Validation

*For any* API request to submit coverage, if the request is missing required fields (clover_file, branch, or commit_sha) or contains empty values, then the API should reject the request with a validation error response.

**Validates: Requirements 1.2, 1.3**

### Property 2: Unique File Storage

*For any* set of coverage submissions, each clover.xml file should be stored with a unique filename such that no two files have the same storage path, even when submitted for the same repository.

**Validates: Requirements 1.6**

### Property 3: Job Queuing

*For any* valid coverage submission, after the API accepts the request, a processing job should exist in the queue system.

**Validates: Requirements 1.7**

### Property 4: Complete File Path Extraction

*For any* valid clover.xml file, the parser should extract all file paths present in the XML, with no files omitted from the parsed output.

**Validates: Requirements 2.1**

### Property 5: Line Coverage Extraction

*For any* file in a clover.xml report, the parser should extract line-level coverage data for all executable lines in that file.

**Validates: Requirements 2.2**

### Property 6: Coverage Calculation Correctness

*For any* file with N total executable lines and M covered lines, the calculated coverage percentage should equal round((M / N) * 100, 2), or 0.0 if N is zero.

**Validates: Requirements 2.3, 5.1, 5.2, 5.3**

### Property 7: Overall Coverage Aggregation

*For any* coverage report containing multiple files, the overall coverage percentage should equal the sum of all covered lines divided by the sum of all total lines across all files, rounded to two decimal places.

**Validates: Requirements 2.4, 5.4**

### Property 8: Malformed XML Handling

*For any* malformed or invalid XML file, the parser should fail gracefully by logging the error and marking the job as failed, without crashing the application.

**Validates: Requirements 2.6**

### Property 9: Coverage Report Persistence

*For any* successfully parsed coverage data, a coverage report record should be created in the database containing repository_id, branch, commit_sha, coverage_percentage, and timestamp.

**Validates: Requirements 3.1**

### Property 10: File Coverage Storage

*For any* coverage report with N files, exactly N file coverage records should be created in the database, each with file_path, total_lines, covered_lines, and coverage_percentage.

**Validates: Requirements 3.2**

### Property 11: Line Coverage Compression Round Trip

*For any* line coverage data stored as compressed JSON, decompressing and parsing the data should produce an equivalent data structure to the original input.

**Validates: Requirements 3.3**

### Property 12: Branch Report Replacement and Archival

*For any* branch with an existing coverage report, when a new report is submitted for the same branch, the previous report should be marked as archived (archived=true) and the new report should be the only non-archived report for that branch.

**Validates: Requirements 3.4, 3.5**

### Property 13: Current Coverage Query

*For any* branch with multiple coverage reports (some archived, some not), querying for current coverage should return only the non-archived report with the most recent timestamp.

**Validates: Requirements 3.6**

### Property 14: Repository File Caching on Submission

*For any* coverage submission with commit SHA X on branch B, if a cached file list already exists for repository R, branch B, and commit SHA X, then the system should reuse the cached file list without fetching from GitHub. If no cache exists or the commit SHA differs, then the system should fetch from GitHub and update the cache.

**Validates: Requirements 9.1, 9.2, 9.3**

### Property 15: Webhook Signature Verification

*For any* GitHub webhook payload, the signature verification should return true if and only if the HMAC-SHA256 hash of the payload with the webhook secret matches the provided signature.

**Validates: Requirements 4.9**

### Property 16: Branch Coverage Comparison Calculation

*For any* non-main branch with coverage percentage X and main branch with coverage percentage Y, the comparison difference should equal round(X - Y, 2).

**Validates: Requirements 6.1, 6.2**

### Property 17: File Coverage Classification

*For any* file in a coverage report, the file should be classified as "covered" if its coverage percentage is greater than 0%, and "uncovered" if its coverage percentage equals 0%.

**Validates: Requirements 8.1, 8.2**

### Property 18: Uncovered File Identification

*For any* branch coverage report, files that exist in the cached repository file list but not in the coverage report should be identified and assigned 0% coverage.

**Validates: Requirements 9.4, 9.5**

### Property 19: Covered File Coverage Display

*For any* file that exists in both the cached repository file list and the coverage report, the displayed coverage percentage should match the percentage from the coverage report.

**Validates: Requirements 9.6**

### Property 20: File Exclusion Pattern Filtering

*For any* configured exclusion pattern (glob), files matching that pattern should be filtered out from the coverage display.

**Validates: Requirements 9.7, 9.8**

## Error Handling

### API Validation Errors

**Strategy**: Use Laravel Form Requests for validation

```php
class StoreCoverageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'repository' => 'required|string',
            'branch' => 'required|string|max:255',
            'commit_sha' => 'required|string|size:40',
            'clover_file' => 'required|file|mimes:xml',
        ];
    }
    
    public function messages(): array
    {
        return [
            'clover_file.required' => 'The clover.xml file is required',
            'clover_file.mimes' => 'The file must be an XML file',
            'branch.required' => 'The branch name is required',
            'commit_sha.required' => 'The commit SHA is required',
            'commit_sha.size' => 'The commit SHA must be 40 characters',
        ];
    }
}
```

### Job Processing Errors

**Strategy**: Use try-catch blocks with logging and status updates

```php
public function handle(): void
{
    try {
        // Processing logic
    } catch (FileNotFoundException $e) {
        Log::error('Clover file not found', [
            'report_id' => $this->coverageReportId,
            'error' => $e->getMessage(),
        ]);
        
        $this->fail($e);
    } catch (InvalidCloverFormatException $e) {
        Log::error('Invalid clover format', [
            'report_id' => $this->coverageReportId,
            'error' => $e->getMessage(),
        ]);
        
        $this->fail($e);
    } catch (Exception $e) {
        Log::error('Unexpected error processing coverage', [
            'report_id' => $this->coverageReportId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        $this->fail($e);
    }
}

public function failed(Throwable $exception): void
{
    $report = CoverageReport::find($this->coverageReportId);
    
    if ($report) {
        $report->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
```

### GitHub API Errors

**Strategy**: Retry with exponential backoff for rate limits, fail gracefully for other errors

```php
public function fetchRepositoryFiles(Repository $repository, string $commitSha): array
{
    try {
        $response = Http::retry(3, 100, function ($exception, $request) {
            // Retry on rate limit errors
            return $exception instanceof RequestException 
                && $exception->response?->status() === 429;
        })
        ->withHeaders([
            'Authorization' => "Bearer {$repository->access_token}",
            'Accept' => 'application/vnd.github.v3+json',
        ])
        ->get("{$this->apiUrl}/repos/{$repository->owner}/{$repository->name}/git/trees/{$commitSha}", [
            'recursive' => 1,
        ]);
        
        if ($response->failed()) {
            throw new GitHubApiException(
                "Failed to fetch repository files: HTTP {$response->status()}"
            );
        }
        
        return collect($response->json()['tree'])
            ->filter(fn($item) => $item['type'] === 'blob')
            ->pluck('path')
            ->toArray();
            
    } catch (ConnectionException $e) {
        Log::error('GitHub API connection failed', [
            'repository' => $repository->owner . '/' . $repository->name,
            'commit' => $commitSha,
            'error' => $e->getMessage(),
        ]);
        
        throw new GitHubApiException('Failed to connect to GitHub API', 0, $e);
    }
}
```

### Database Errors

**Strategy**: Use database transactions for atomic operations

```php
DB::transaction(function () use ($report, $coverageData, $repoFiles) {
    // Archive previous report
    $this->archivePreviousReport($report);
    
    // Update current report
    $report->update([
        'coverage_percentage' => $coverageData['overall_percentage'],
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    
    // Store file coverage
    foreach ($coverageData['files'] as $fileData) {
        CoverageFile::create([
            'coverage_report_id' => $report->id,
            'file_path' => $fileData['path'],
            'total_lines' => $fileData['total_lines'],
            'covered_lines' => $fileData['covered_lines'],
            'coverage_percentage' => $fileData['percentage'],
            'line_coverage_data' => gzcompress(json_encode($fileData['lines'])),
        ]);
    }
    
    // Cache repository files
    $this->cacheRepositoryFiles($report, $repoFiles);
});
```

## Testing Strategy

### Dual Testing Approach

This application requires both **unit tests** and **property-based tests** for comprehensive coverage:

- **Unit tests**: Verify specific examples, edge cases, error conditions, and integration points
- **Property tests**: Verify universal properties across all inputs through randomization

### Unit Testing

**Focus Areas**:
- Specific examples demonstrating correct behavior
- Edge cases (empty files, zero coverage, missing data)
- Error conditions (malformed XML, API failures, validation errors)
- Integration between components (API → Queue → Parser → Database)

**Example Unit Tests**:

```php
// tests/Feature/CoverageApiTest.php
class CoverageApiTest extends TestCase
{
    public function test_api_accepts_valid_coverage_submission(): void
    {
        $repository = Repository::factory()->create();
        
        $response = $this->postJson('/api/coverage', [
            'repository' => $repository->owner . '/' . $repository->name,
            'branch' => 'feature-branch',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100, 'text/xml'),
        ]);
        
        $response->assertStatus(202)
            ->assertJsonStructure(['status', 'job_id', 'message']);
    }
    
    public function test_api_rejects_missing_clover_file(): void
    {
        $response = $this->postJson('/api/coverage', [
            'repository' => 'owner/repo',
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['clover_file']);
    }
    
    public function test_api_rejects_empty_branch_name(): void
    {
        $response = $this->postJson('/api/coverage', [
            'repository' => 'owner/repo',
            'branch' => '',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml'),
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch']);
    }
}

// tests/Unit/CloverParserTest.php
class CloverParserTest extends TestCase
{
    public function test_parser_handles_empty_clover_file(): void
    {
        $parser = new CloverParser();
        $xml = '<?xml version="1.0"?><coverage></coverage>';
        $tempFile = tmpfile();
        fwrite($tempFile, $xml);
        $path = stream_get_meta_data($tempFile)['uri'];
        
        $result = $parser->parse($path);
        
        $this->assertEquals(0.0, $result['overall_percentage']);
        $this->assertEmpty($result['files']);
    }
    
    public function test_parser_throws_exception_for_malformed_xml(): void
    {
        $this->expectException(InvalidCloverFormatException::class);
        
        $parser = new CloverParser();
        $tempFile = tmpfile();
        fwrite($tempFile, 'not valid xml');
        $path = stream_get_meta_data($tempFile)['uri'];
        
        $parser->parse($path);
    }
}
```

### Property-Based Testing

**Configuration**:
- Use a property-based testing library for PHP (e.g., Eris, PHPUnit with data providers)
- Minimum 100 iterations per property test
- Each test references its design document property

**Property Test Examples**:

```php
// tests/Property/CoverageCalculationPropertyTest.php
class CoverageCalculationPropertyTest extends TestCase
{
    /**
     * Feature: code-coverage-tracker, Property 6: Coverage Calculation Correctness
     * 
     * @test
     */
    public function coverage_percentage_calculation_is_correct(): void
    {
        // Run 100 iterations with random values
        for ($i = 0; $i < 100; $i++) {
            $totalLines = rand(1, 1000);
            $coveredLines = rand(0, $totalLines);
            
            $expected = round(($coveredLines / $totalLines) * 100, 2);
            
            $file = CoverageFile::factory()->create([
                'total_lines' => $totalLines,
                'covered_lines' => $coveredLines,
            ]);
            
            $this->assertEquals($expected, $file->coverage_percentage);
        }
    }
    
    /**
     * Feature: code-coverage-tracker, Property 6: Coverage Calculation Correctness (Edge Case)
     * 
     * @test
     */
    public function coverage_percentage_is_zero_when_no_lines(): void
    {
        $file = CoverageFile::factory()->create([
            'total_lines' => 0,
            'covered_lines' => 0,
        ]);
        
        $this->assertEquals(0.0, $file->coverage_percentage);
    }
    
    /**
     * Feature: code-coverage-tracker, Property 11: Line Coverage Compression Round Trip
     * 
     * @test
     */
    public function line_coverage_compression_preserves_data(): void
    {
        for ($i = 0; $i < 100; $i++) {
            // Generate random line coverage data
            $lineCount = rand(10, 500);
            $originalData = [];
            
            for ($line = 1; $line <= $lineCount; $line++) {
                $originalData[$line] = [
                    'covered' => (bool) rand(0, 1),
                    'count' => rand(0, 100),
                ];
            }
            
            // Compress and store
            $compressed = gzcompress(json_encode($originalData));
            
            $file = CoverageFile::factory()->create([
                'line_coverage_data' => $compressed,
            ]);
            
            // Decompress and verify
            $decompressed = json_decode(gzuncompress($file->line_coverage_data), true);
            
            $this->assertEquals($originalData, $decompressed);
        }
    }
    
    /**
     * Feature: code-coverage-tracker, Property 12: Branch Report Replacement and Archival
     * 
     * @test
     */
    public function new_report_archives_previous_report_for_same_branch(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $repository = Repository::factory()->create();
            $branch = 'branch-' . rand(1, 100);
            
            // Create first report
            $firstReport = CoverageReport::factory()->create([
                'repository_id' => $repository->id,
                'branch' => $branch,
                'archived' => false,
            ]);
            
            // Create second report for same branch
            $secondReport = CoverageReport::factory()->create([
                'repository_id' => $repository->id,
                'branch' => $branch,
                'archived' => false,
            ]);
            
            // Simulate archival process
            CoverageReport::where('repository_id', $repository->id)
                ->where('branch', $branch)
                ->where('id', '!=', $secondReport->id)
                ->update(['archived' => true]);
            
            // Verify first is archived, second is not
            $this->assertTrue($firstReport->fresh()->archived);
            $this->assertFalse($secondReport->fresh()->archived);
            
            // Verify only one non-archived report exists
            $nonArchived = CoverageReport::where('repository_id', $repository->id)
                ->where('branch', $branch)
                ->where('archived', false)
                ->count();
            
            $this->assertEquals(1, $nonArchived);
        }
    }
}
```

### Integration Testing

**Focus**: Test complete workflows from API to database

```php
// tests/Feature/CoverageWorkflowTest.php
class CoverageWorkflowTest extends TestCase
{
    public function test_complete_coverage_submission_workflow(): void
    {
        Queue::fake();
        Storage::fake('local');
        
        $repository = Repository::factory()->create();
        
        // Submit coverage
        $response = $this->postJson('/api/coverage', [
            'repository' => $repository->owner . '/' . $repository->name,
            'branch' => 'feature-test',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => UploadedFile::fake()->create('clover.xml', 100, 'text/xml'),
        ]);
        
        $response->assertStatus(202);
        
        // Verify file stored
        Storage::disk('local')->assertExists('coverage/' . $response->json('job_id') . '.xml');
        
        // Verify job queued
        Queue::assertPushed(ProcessCoverageJob::class);
        
        // Verify coverage report created
        $this->assertDatabaseHas('coverage_reports', [
            'repository_id' => $repository->id,
            'branch' => 'feature-test',
            'status' => 'pending',
        ]);
    }
}
```

### Test Coverage Goals

- **Unit Test Coverage**: 80%+ of application code
- **Property Test Coverage**: All 20 correctness properties implemented
- **Integration Test Coverage**: All major workflows (submission, parsing, display)
- **Edge Case Coverage**: All identified edge cases (zero lines, empty files, malformed data)
