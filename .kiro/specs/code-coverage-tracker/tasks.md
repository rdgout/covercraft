# Implementation Plan: Code Coverage Tracker

## Overview

This implementation plan breaks down the Code Coverage Tracker feature into discrete, incremental tasks. Each task builds on previous work, with testing integrated throughout. The implementation follows Laravel 12 conventions and uses PHPUnit for testing.

## Tasks

- [ ] 1. Set up database schema and models
  - Create migrations for repositories, coverage_reports, coverage_files, and repository_file_cache tables
  - Create Eloquent models with relationships and casts
  - Run migrations and verify schema
  - _Requirements: 3.1, 3.2, 3.3, 16.1, 16.2, 16.5_

- [ ] 2. Implement Clover XML parser
  - [ ] 2.1 Create CloverParser class with parse method
    - Implement XML parsing logic to extract file paths, line coverage, and metrics
    - Handle file-level and line-level coverage extraction
    - Calculate coverage percentages
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.7_
  
  - [ ]* 2.2 Write property test for coverage calculation correctness
    - **Property 6: Coverage Calculation Correctness**
    - **Validates: Requirements 2.3, 5.1, 5.2, 5.3**
  
  - [ ]* 2.3 Write property test for overall coverage aggregation
    - **Property 7: Overall Coverage Aggregation**
    - **Validates: Requirements 2.4, 5.4**
  
  - [ ]* 2.4 Write unit tests for parser edge cases
    - Test empty clover files
    - Test malformed XML handling
    - Test files with zero lines
    - _Requirements: 2.6, 5.3_

- [ ] 3. Create Coverage API endpoint
  - [ ] 3.1 Create StoreCoverageRequest form request with validation rules
    - Validate required fields: repository, branch, commit_sha, clover_file
    - Add custom error messages
    - _Requirements: 1.2, 1.3, 1.4, 1.5_
  
  - [ ] 3.2 Create CoverageController with store method
    - Handle file upload and storage with unique filenames
    - Create pending coverage report record
    - Dispatch ProcessCoverageJob
    - Return 202 Accepted response
    - _Requirements: 1.1, 1.6, 1.7, 1.8_
  
  - [ ] 3.3 Create status endpoint for job tracking
    - Implement GET /api/coverage/status/{job_id}
    - Return job status and coverage report ID
    - _Requirements: 14.6_
  
  - [ ]* 3.4 Write property test for API request validation
    - **Property 1: API Request Validation**
    - **Validates: Requirements 1.2, 1.3**
  
  - [ ]* 3.5 Write property test for unique file storage
    - **Property 2: Unique File Storage**
    - **Validates: Requirements 1.6**
  
  - [ ]* 3.6 Write unit tests for API endpoints
    - Test successful submission
    - Test validation errors
    - Test status endpoint
    - _Requirements: 1.1, 1.4, 1.5, 14.6_

- [ ] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. Implement coverage processing job
  - [ ] 5.1 Create ProcessCoverageJob with handle method
    - Parse clover.xml file using CloverParser
    - Fetch repository files from GitHub
    - Store coverage data in database transaction
    - Archive previous reports for the branch
    - Update report status to completed
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4, 3.5, 14.3, 14.4_
  
  - [ ] 5.2 Implement failed method for error handling
    - Update report status to failed
    - Store error message
    - _Requirements: 13.2, 14.5_
  
  - [ ]* 5.3 Write property test for job queuing
    - **Property 3: Job Queuing**
    - **Validates: Requirements 1.7**
  
  - [ ]* 5.4 Write property test for complete file path extraction
    - **Property 4: Complete File Path Extraction**
    - **Validates: Requirements 2.1**
  
  - [ ]* 5.5 Write property test for line coverage extraction
    - **Property 5: Line Coverage Extraction**
    - **Validates: Requirements 2.2**
  
  - [ ]* 5.6 Write property test for malformed XML handling
    - **Property 8: Malformed XML Handling**
    - **Validates: Requirements 2.6**

- [ ] 6. Implement GitHub integration service
  - [ ] 6.1 Create GitHubService class
    - Implement fetchRepositoryFiles method with GitHub API
    - Implement getOrFetchRepositoryFiles method that checks cache first
    - Implement webhook signature verification
    - Implement handlePushWebhook method
    - Handle rate limiting with retry logic
    - _Requirements: 4.4, 4.5, 4.6, 4.7, 4.9, 4.10, 9.1, 9.2, 9.3_
  
  - [ ] 6.2 Create webhook controller
    - Verify webhook signature
    - Handle push events
    - Update file cache for main branch
    - _Requirements: 4.4, 4.9, 4.10_
  
  - [ ]* 6.3 Write property test for webhook signature verification
    - **Property 15: Webhook Signature Verification**
    - **Validates: Requirements 4.9**
  
  - [ ]* 6.4 Write property test for repository file caching
    - **Property 14: Repository File Caching on Submission**
    - **Validates: Requirements 9.1, 9.2, 9.3**
  
  - [ ]* 6.5 Write unit tests for GitHub service
    - Test API authentication
    - Test rate limit handling
    - Test webhook handling
    - Test cache hit (files not fetched from GitHub)
    - Test cache miss (files fetched from GitHub)
    - _Requirements: 4.2, 4.3, 4.6, 4.7, 9.1, 9.2_

- [ ] 7. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 8. Implement data storage and retrieval
  - [ ] 8.1 Add methods to CoverageReport model
    - Implement archivePreviousReport method
    - Add scopes for current (non-archived) reports
    - Add relationship methods
    - _Requirements: 3.4, 3.5, 3.6_
  
  - [ ] 8.2 Add accessor to CoverageFile model for line coverage decompression
    - Implement getLineCoverageAttribute accessor
    - Handle JSON decompression
    - _Requirements: 3.3, 16.3, 16.4_
  
  - [ ]* 8.3 Write property test for coverage report persistence
    - **Property 9: Coverage Report Persistence**
    - **Validates: Requirements 3.1**
  
  - [ ]* 8.4 Write property test for file coverage storage
    - **Property 10: File Coverage Storage**
    - **Validates: Requirements 3.2**
  
  - [ ]* 8.5 Write property test for line coverage compression round trip
    - **Property 11: Line Coverage Compression Round Trip**
    - **Validates: Requirements 3.3**
  
  - [ ]* 8.6 Write property test for branch report replacement and archival
    - **Property 12: Branch Report Replacement and Archival**
    - **Validates: Requirements 3.4, 3.5**
  
  - [ ]* 8.7 Write property test for current coverage query
    - **Property 13: Current Coverage Query**
    - **Validates: Requirements 3.6**

- [ ] 9. Create dashboard views and controllers
  - [ ] 9.1 Create DashboardController with index method
    - Display list of repositories
    - Show latest coverage for each repository
    - _Requirements: 7.1_
  
  - [ ] 9.2 Implement repository method in DashboardController
    - Display all branches for a repository
    - Show coverage percentage and timestamp for each branch
    - _Requirements: 7.2, 7.3, 12.1, 12.2_
  
  - [ ] 9.3 Implement branch method in DashboardController
    - Display current coverage for branch
    - Calculate comparison with main branch
    - Fetch cached files from database (never from GitHub)
    - Build file tree from coverage data and cached files
    - _Requirements: 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 7.10, 7.11, 7.12, 7.13, 9.5_
  
  - [ ] 9.4 Implement file method in DashboardController
    - Display file contents with line-by-line coverage
    - Decompress line coverage data
    - Fetch file content from GitHub
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8_
  
  - [ ]* 9.5 Write property test for branch coverage comparison calculation
    - **Property 16: Branch Coverage Comparison Calculation**
    - **Validates: Requirements 6.1, 6.2**
  
  - [ ]* 9.6 Write property test for file coverage classification
    - **Property 17: File Coverage Classification**
    - **Validates: Requirements 8.1, 8.2**
  
  - [ ]* 9.7 Write unit tests for dashboard controllers
    - Test index page displays repositories
    - Test repository page displays branches
    - Test branch page displays file tree
    - Test branch page reads from cache (not GitHub)
    - Test file page displays line coverage
    - _Requirements: 7.1, 7.2, 7.3, 9.5, 10.1_

- [ ] 10. Implement file tree building logic
  - [ ] 10.1 Create FileTreeBuilder service class
    - Build hierarchical tree structure from file paths
    - Merge coverage data with cached repository files from database
    - Identify uncovered files
    - Apply exclusion patterns
    - _Requirements: 7.9, 7.10, 7.11, 9.5, 9.6, 9.7, 9.8_
  
  - [ ]* 10.2 Write property test for uncovered file identification
    - **Property 18: Uncovered File Identification**
    - **Validates: Requirements 9.6, 9.7**
  
  - [ ]* 10.3 Write property test for covered file coverage display
    - **Property 19: Covered File Coverage Display**
    - **Validates: Requirements 9.8**
  
  - [ ]* 10.4 Write property test for file exclusion pattern filtering
    - **Property 20: File Exclusion Pattern Filtering**
    - **Validates: Requirements 9.9, 9.10**
  
  - [ ]* 10.5 Write unit tests for file tree builder
    - Test tree structure generation
    - Test coverage merging with cached files
    - Test exclusion patterns
    - _Requirements: 7.9, 9.9, 9.10_

- [ ] 11. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 12. Create Blade views for dashboard
  - [ ] 12.1 Create dashboard layout template
    - Set up base layout with navigation
    - Include Tailwind CSS
    - Add common UI components
  
  - [ ] 12.2 Create index view for repository list
    - Display repositories with coverage badges
    - Show overall coverage percentage
    - _Requirements: 7.1_
  
  - [ ] 12.3 Create repository view for branch list
    - Display branches with coverage percentages
    - Show comparison with main branch
    - Add sparkline graphs for trends
    - _Requirements: 7.2, 7.3, 12.1, 12.2, 12.3, 12.6_
  
  - [ ] 12.4 Create branch view with file tree
    - Display expandable/collapsible file tree
    - Show coverage percentage for each file
    - Use visual indicators for covered/uncovered files
    - Display comparison with main branch
    - _Requirements: 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 7.10, 7.11, 7.12, 7.13, 8.3_
  
  - [ ] 12.5 Create file view with line-by-line coverage
    - Display file contents with line numbers
    - Highlight covered/uncovered lines
    - Add syntax highlighting
    - Show file coverage percentage
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8_

- [ ] 13. Implement configuration management
  - [ ] 13.1 Create configuration file for coverage tracker
    - Add file exclusion patterns
    - Add default branch name configuration
    - Add GitHub API endpoint configuration
    - _Requirements: 15.1, 15.2, 15.3_
  
  - [ ] 13.2 Update FileTreeBuilder to use exclusion patterns from config
    - Read patterns from configuration
    - Apply patterns to file filtering
    - _Requirements: 9.7, 9.8, 15.1_
  
  - [ ] 13.3 Update GitHubService to use configurable API endpoint
    - Read API URL from configuration
    - Support GitHub Enterprise
    - _Requirements: 15.3_
  
  - [ ]* 13.4 Write unit tests for configuration
    - Test exclusion patterns are applied
    - Test custom branch names
    - Test custom API endpoints
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5_

- [ ] 14. Add error handling and logging
  - [ ] 14.1 Add comprehensive logging to ProcessCoverageJob
    - Log parsing errors with XML content
    - Log GitHub API errors
    - Log database errors
    - _Requirements: 13.1, 13.2, 13.3_
  
  - [ ] 14.2 Create error display in dashboard
    - Show failed processing attempts
    - Display error messages and timestamps
    - _Requirements: 13.6_
  
  - [ ]* 14.3 Write unit tests for error handling
    - Test malformed XML handling
    - Test GitHub API error handling
    - Test database error handling
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ] 15. Implement cache refresh logic
  - [ ] 15.1 Create scheduled command for cache refresh (OPTIONAL)
    - This is now optional since cache is commit-specific
    - Only needed if you want to proactively update caches
    - _Requirements: N/A_
  
  - [ ] 15.2 Register command in console kernel (OPTIONAL)
    - Schedule to run daily if implemented
    - _Requirements: N/A_
  
  - [ ]* 15.3 Write unit tests for cache refresh (OPTIONAL)
    - Test stale cache detection
    - Test cache refresh logic
    - _Requirements: N/A_

- [ ] 16. Create repository management interface
  - [ ] 16.1 Create repository CRUD controller
    - Implement create, read, update, delete operations
    - Validate repository exists on GitHub
    - _Requirements: 4.1, 4.2, 4.3_
  
  - [ ] 16.2 Create repository management views
    - Form for adding repositories
    - List of configured repositories
    - Edit/delete functionality
    - _Requirements: 4.1_
  
  - [ ]* 16.3 Write unit tests for repository management
    - Test repository creation
    - Test validation
    - Test error handling
    - _Requirements: 4.1, 4.2, 4.3_

- [ ] 17. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 18. Integration and final wiring
  - [ ] 18.1 Set up routes for all endpoints
    - API routes for coverage submission and status
    - Web routes for dashboard
    - Webhook routes for GitHub integration
  
  - [ ] 18.2 Configure queue system
    - Set up Redis or database queue driver
    - Configure queue worker
  
  - [ ] 18.3 Set up storage for clover files
    - Configure storage disk
    - Set up file cleanup for old files
  
  - [ ]* 18.4 Write integration tests for complete workflows
    - Test end-to-end coverage submission
    - Test webhook to dashboard flow
    - Test branch comparison flow
    - _Requirements: 1.1, 1.6, 1.7, 2.1, 3.1, 4.4, 7.4_

- [ ] 19. Final checkpoint - Run full test suite
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties (minimum 100 iterations each)
- Unit tests validate specific examples and edge cases
- Integration tests validate end-to-end workflows
- All code follows Laravel 12 conventions and uses PHP 8.4 features
