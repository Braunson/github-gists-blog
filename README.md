# GitHub Gist Blog

Transform GitHub Gists into blog interfaces with caching and real-time updates.

## 🚀 Features

- **Dynamic Blog Generation**: Convert any GitHub user's public gists into a blog-style interface
- **Smart Caching**: 4-hour cache with background refresh for optimal performance
- **Real-time Search**: Livewire-powered filtering by username and programming language
- **Queue-based Updates**: Background job processing for seamless user experience
- **Responsive Design**: Beautiful Tailwind CSS interface that works on all devices

## 🛠 Tech Stack

- **Backend**: Laravel 12, PHP 8.4+
- **Frontend**: Blade templates, Livewire, Alpine.js, Tailwind CSS
- **Database**: PostgreSQL
- **Cache**: Redis
- **Queue**: Laravel Queues with database driver
- **API**: GitHub REST API v3

## 📋 Prerequisites

- PHP 8.4 or higher
- Docker (for local Development)
- Composer
- Node.js & NPM
- PostgreSQL
- Redis (for production)
- GitHub Personal Access Token

## 🔧 Local Development Setup

### 1. Clone and Install Dependencies

```bash
git clone https://github.com/yourusername/gist-blog.git
cd gist-blog
composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` file:

```env
# GitHub API Token (public access only needed)
GITHUB_TOKEN=your_github_personal_access_token
```

### 3. Database Setup

```bash
php artisan migrate
```

### 4. Build Assets & Start Development

```bash
# Build frontend assets
npm run build

# Start Laravel development server
php artisan serve

# In another terminal, start queue worker
php artisan queue:work
```

Visit `http://localhost` to see the application.

## 🚀 Laravel Cloud Deployment

### 1. Prepare Repository

- Ensure your code is committed and pushed

### 2. Laravel Cloud Setup

1. **Connect Repository**:
   - Log into [Laravel Cloud](https://cloud.laravel.com)
   - Create new project
   - Connect your GitHub repository

2. **Environment Variables**:
   Configure these in Laravel Cloud dashboard:
   ```
   GITHUB_TOKEN=your_github_token
   APP_URL=https://your-app.laravel.cloud
   ```

3. **Database Configuration**:
   - Laravel Cloud provides PostgreSQL automatically
   - Database credentials are auto-configured

4. **Redis Configuration**:
   - Laravel Cloud provides Redis automatically
   - Cache and session drivers are auto-configured

5. **Queue Configuration**:
   - Set `QUEUE_CONNECTION=database` in environment
   - Laravel Cloud will auto-start queue workers

### 3. Deploy

```bash
# Deploy via Laravel Cloud dashboard or CLI
php artisan cloud:deploy
```

### 4. Post-Deployment

```bash
# Run migrations on production
php artisan cloud:command "php artisan migrate --force"

# Optional: Pre-populate popular users for demo
php artisan cloud:command "php artisan tinker --execute=\"app(\App\Services\GistService::class)->syncUserGists('taylorotwell');\""
```

## 🧪 Testing

### Test Structure

```
tests/
├── Feature/
│   ├── BlogControllerTest.php      # Route and controller tests
│   ├── GistManagementTest.php      # End-to-end gist operations
│   └── LivewireSearchTest.php      # Livewire component tests
├── Unit/
│   ├── GistServiceTest.php         # GitHub API service tests
│   ├── GistModelTest.php           # Model methods and scopes
│   └── JobsTest.php                # Queue job testing
└── Mocks/
    └── GitHubApiResponses.php      # Mock API responses
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/BlogControllerTest.php

# Run tests with parallel processing
php artisan test --parallel
```

### Test Categories

#### 1. Unit Tests

**GistServiceTest.php**:
- ✅ Fetch user gists from GitHub API
- ✅ Handle API rate limiting and errors
- ✅ Parse gist data correctly
- ✅ Cache gist responses
- ✅ Sync individual gist content

**GistModelTest.php**:
- ✅ Model relationships and attributes
- ✅ Cache expiration logic
- ✅ Query scopes (forUsername, recent)
- ✅ Data casting and mutators

**JobsTest.php**:
- ✅ RefreshUserGists job execution
- ✅ Queue job retries and failures
- ✅ Background processing workflow

#### 2. Feature Tests

**BlogControllerTest.php**:
- ✅ Homepage displays recent gists and examples
- ✅ User blog page loads and caches gists
- ✅ Individual gist page displays content
- ✅ Handles non-existent users gracefully
- ✅ Queue dispatch for cache refresh

**GistManagementTest.php**:
- ✅ End-to-end gist fetching and storage
- ✅ Cache invalidation workflows
- ✅ Database transaction integrity
- ✅ API error handling

**LivewireSearchTest.php**:
- ✅ Real-time search functionality
- ✅ Language filtering
- ✅ Component state management
- ✅ DOM updates and interactions

#### 3. HTTP Tests with Mocked APIs

**GitHub API Mocking**:
```php
// Mock successful gist list response
Http::fake([
    'api.github.com/users/*/gists' => Http::response($this->mockGistsList(), 200),
    'api.github.com/gists/*' => Http::response($this->mockSingleGist(), 200),
]);
```

### Database Testing

```bash
# Use in-memory SQLite for speed
# Configured in phpunit.xml

# Test database migrations
php artisan test --testsuite=Feature tests/Feature/DatabaseTest.php
```

### Performance Testing

```bash
# Test API response times
php artisan test tests/Performance/ApiPerformanceTest.php

# Test database query efficiency
php artisan test tests/Performance/DatabasePerformanceTest.php
```

## 📊 Key Testing Scenarios

### 1. GitHub API Integration
- Mock GitHub API responses for consistent testing
- Test rate limiting and error handling
- Verify data parsing and storage

### 2. Caching Strategy
- Test cache hits and misses
- Verify cache expiration logic
- Test background refresh jobs

### 3. User Experience
- Test loading states for new users
- Verify search and filtering functionality
- Test responsive design elements

### 4. Error Handling
- Non-existent GitHub users
- API rate limiting scenarios
- Network connectivity issues
- Invalid gist data

## 🔍 Code Quality

```bash
# Static analysis with PHPStan
./vendor/bin/phpstan analyse
```

## 📈 Performance Monitoring

### Key Metrics to Monitor

1. **GitHub API Usage**: Rate limiting and response times
2. **Database Performance**: Query count and execution time
3. **Cache Hit Ratio**: Redis performance and efficiency
4. **Queue Processing**: Job completion rates and delays

### Laravel Cloud Monitoring

- Built-in performance monitoring
- Database query analysis
- Queue job tracking
- Error logging and alerts

## Troubleshooting

### Common Issues

**GitHub API Rate Limiting**:
```bash
# Check current rate limit status
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/rate_limit
```

**Queue Jobs Not Processing**:
```bash
# Restart queue workers
php artisan queue:restart

# Check failed jobs
php artisan queue:failed
```

**Cache Issues**:
```bash
# Clear application cache
php artisan cache:clear

# Clear config cache
php artisan config:clear
```

## 📝 API Endpoints

- `GET /` - Homepage with recent gists and examples
- `GET /{username}` - User's gist blog
- `GET /{username}/{gistId}` - Individual gist view

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🔗 Links

- [Laravel Cloud Documentation](https://cloud.laravel.com/docs)
- [GitHub API Documentation](https://docs.github.com/en/rest)
- [Laravel Documentation](https://laravel.com/docs)
- [Livewire Documentation](https://livewire.laravel.com)

## Roadmap

- Ability to claim and customize your own blog page
- Ability to hide certain Gists

---

**Built with ❤️ from 🇨🇦**
