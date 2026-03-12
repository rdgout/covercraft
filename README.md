# CoverCraft - Code Coverage Tracking Platform

A self-hosted code coverage tracking platform built with Laravel. Track, visualize, and monitor your code coverage across repositories, branches, and pull requests.

## Features

- 📊 **Coverage Tracking** - Upload and track code coverage reports across multiple repositories
- 🌳 **Branch Management** - Monitor coverage for different branches independently
- 📈 **Visual Reports** - Interactive file-level coverage visualization with line-by-line analysis
- 🏷️ **Coverage Badges** - Generate SVG badges for your README files
- 👥 **Team Collaboration** - Multi-user support with team-based access control
- 🔑 **API Tokens** - Secure token-based authentication for CI/CD integration
- 🔗 **GitHub Integration** - Native webhook support for automated coverage updates
- 🎯 **Trend Analysis** - Track coverage changes over time

## Requirements

- PHP 8.4+
- Composer
- Node.js 18+ and npm
- SQLite (default) or MySQL/PostgreSQL

## Local Development Setup

### 1. Clone and Install Dependencies

```bash
git clone https://github.com/yourusername/covercraft.git
cd covercraft
composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` file with your database credentials if not using SQLite:

```env
DB_CONNECTION=sqlite
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=covercraft
# DB_USERNAME=root
# DB_PASSWORD=
```

### 3. Database Setup

```bash
php artisan migrate
```

### 4. Build Frontend Assets

```bash
npm run build
```

For development with hot reloading:

```bash
npm run dev
```

### 5. Start Development Server

```bash
php artisan serve
```

Visit `http://localhost:8000` to access the application.

## Getting Started

### Step 1: Create an Account

1. Navigate to your CoverCraft instance (e.g., `https://covercraft.yourdomain.com`)
2. Click **Register** in the top navigation
3. Fill in your details:
   - Name
   - Email address
   - Password
4. Click **Register** to create your account
5. You'll be automatically logged in and redirected to the dashboard

### Step 2: Create a Repository/Project

1. From the dashboard, click **Repositories** in the navigation
2. Click **New Repository**
3. Fill in the repository details:
   - **Repository Name**: Your repository name (e.g., `my-awesome-project`)
   - **Owner/Organization**: The owner or organization name (e.g., `acme-corp`)
   - **Description** (optional): Brief description of the project
4. Click **Create Repository**
5. Your repository will be created and you'll see it in your repositories list

### Step 3: Generate an API Token

API tokens are used to authenticate coverage uploads from your CI/CD pipeline.

1. Click **API Tokens** in the navigation
2. Click **Generate New Token**
3. Fill in the token details:
   - **Token Name**: A descriptive name (e.g., `GitHub Actions - my-project`)
   - **Description** (optional): Purpose or scope of the token
4. Click **Create Token**
5. **Important**: Copy the generated token immediately - it will only be shown once
6. Store the token securely (you'll need it for the next step)

### Step 4: Configure GitHub Secrets

1. Go to your GitHub repository
2. Navigate to **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Add the following secret:
   - **Name**: `COVERCRAFT_TOKEN`
   - **Value**: The API token you copied from Step 3
5. Click **Add secret**

### Step 5: Set Up GitHub Actions Workflow

Create a new workflow file in your repository at `.github/workflows/coverage.yml`:

```yaml
name: Code Coverage

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  coverage:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run tests with coverage
        run: vendor/bin/phpunit --coverage-clover coverage.xml

      - name: Upload coverage to CoverCraft
        env:
          COVERCRAFT_TOKEN: ${{ secrets.COVERCRAFT_TOKEN }}
          COVERCRAFT_URL: https://covercraft.yourdomain.com
        run: |
          curl -X POST "$COVERCRAFT_URL/api/coverage" \
            -H "Authorization: Bearer $COVERCRAFT_TOKEN" \
            -H "Content-Type: application/json" \
            -d @- << EOF
          {
            "repository": "${{ github.repository }}",
            "branch": "${{ github.ref_name }}",
            "commit": "${{ github.sha }}",
            "coverage": $(cat coverage.xml | base64 -w 0)
          }
          EOF
```

For **JavaScript/TypeScript** projects using Jest:

```yaml
- name: Run tests with coverage
  run: npm test -- --coverage --coverageReporters=json

- name: Upload coverage to CoverCraft
  env:
    COVERCRAFT_TOKEN: ${{ secrets.COVERCRAFT_TOKEN }}
    COVERCRAFT_URL: https://covercraft.yourdomain.com
  run: |
    curl -X POST "$COVERCRAFT_URL/api/coverage" \
      -H "Authorization: Bearer $COVERCRAFT_TOKEN" \
      -H "Content-Type: application/json" \
      -d @- << EOF
    {
      "repository": "${{ github.repository }}",
      "branch": "${{ github.ref_name }}",
      "commit": "${{ github.sha }}",
      "coverage": $(cat coverage/coverage-final.json | base64 -w 0)
    }
    EOF
```

### Step 6: Commit and Push

```bash
git add .github/workflows/coverage.yml
git commit -m "Add code coverage reporting"
git push
```

The workflow will run automatically on your next push or pull request.

## Adding Coverage Badges to Your README

Once you've uploaded coverage data, you can add a badge to your repository's README:

```markdown
![Coverage](https://covercraft.yourdomain.com/badge/owner/repository-name/main)
```

Replace:
- `covercraft.yourdomain.com` with your CoverCraft instance URL
- `owner` with your repository owner/organization
- `repository-name` with your repository name
- `main` with your default branch name

### Badge Examples

```markdown
<!-- Basic badge -->
![Coverage](https://covercraft.yourdomain.com/badge/acme-corp/my-project/main)

<!-- Badge with link to coverage report -->
[![Coverage](https://covercraft.yourdomain.com/badge/acme-corp/my-project/main)](https://covercraft.yourdomain.com/dashboard/acme-corp/my-project/main)
```

## API Reference

### Upload Coverage

**Endpoint**: `POST /api/coverage`

**Headers**:
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body**:
```json
{
  "repository": "owner/repository-name",
  "branch": "main",
  "commit": "abc123def456",
  "coverage": "base64_encoded_coverage_data"
}
```

**Response**:
```json
{
  "success": true,
  "report_id": "uuid",
  "coverage_percentage": 85.4
}
```

### Check Coverage Status

**Endpoint**: `GET /api/coverage/status/{report_id}`

**Response**:
```json
{
  "status": "processed",
  "repository": "owner/repository-name",
  "branch": "main",
  "coverage": 85.4,
  "processed_at": "2026-02-17T10:30:00Z"
}
```

## Deployment

### Using Laravel Forge

1. Create a new server in Laravel Forge
2. Add your repository
3. Set environment variables
4. Configure the deployment script
5. Enable Quick Deploy

### Using Docker (Laravel Sail)

```bash
# Start the application
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate

# Build assets
./vendor/bin/sail npm run build
```

### Manual Deployment

```bash
# On your server
git pull origin main
composer install --no-dev --optimize-autoloader
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Configuration

### Storage

Configure storage for coverage reports in `config/filesystems.php`. By default, reports are stored locally.

### Queue Configuration

For better performance with large coverage reports, configure queues:

```env
QUEUE_CONNECTION=database
```

Then run the queue worker:

```bash
php artisan queue:work
```

## Troubleshooting

### Coverage Upload Fails

1. Verify your API token is correct
2. Check that the repository exists in CoverCraft
3. Ensure the coverage file format is supported (Clover XML, JaCoCo XML, LCOV)
4. Check application logs: `storage/logs/laravel.log`

### Badge Not Displaying

1. Verify the badge URL is correct
2. Ensure coverage has been uploaded for that branch
3. Check that the repository and branch exist

### GitHub Actions Timeout

If coverage generation takes too long:

1. Reduce the number of files being analyzed
2. Use parallel test execution
3. Cache dependencies between workflow runs

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For issues, questions, or feature requests, please open an issue on GitHub.

---

Built with ❤️ using Laravel 12
