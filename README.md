# Personal Finance Tracker

A simple, secure personal finance tracker built with pure PHP, SQLite database, and Google OAuth authentication.

## Features

- 🔐 **Google OAuth Authentication** - Secure login with Google accounts
- 💰 **Transaction Management** - Add, view, and track financial transactions
- 📊 **Balance Calculation** - Real-time balance display with visual indicators
- 🗄️ **SQLite Database** - Lightweight, file-based database (no server required)
- ✅ **Automated Testing** - Comprehensive test suite with PHPUnit
- 🚀 **CI/CD Ready** - GitHub Actions for automated testing and deployment
- 📱 **Responsive Design** - Clean, simple web interface

## Requirements

- PHP 8.0 or higher
- Composer
- SQLite3 extension (usually included with PHP)

## Local Development

### Installation

1. Clone the repository:
```bash
git clone https://github.com/bholsinger09/PersonalFinanceTracker.git
cd PersonalFinanceTracker
```

2. Install dependencies:
```bash
composer install
```

3. Set up environment variables:
```bash
cp .env.example .env
# Edit .env with your Google OAuth credentials
```

4. Start the development server:
```bash
php -S localhost:8000 -t public/
```

5. Open your browser to `http://localhost:8000`

### Google OAuth Setup

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the Google+ API
4. Create OAuth 2.0 credentials
5. Add your domain to authorized origins:
   - For local development: `http://localhost:8000`
   - For production: `https://your-app-name.herokuapp.com`
6. Add redirect URIs:
   - `http://localhost:8000/oauth.php`
   - `https://your-app-name.herokuapp.com/oauth.php`

### Running Tests

```bash
# Run all tests
composer test

# Run with code coverage
composer test -- --coverage-html=coverage

# Run linting
composer lint

# Run static analysis
composer analyze

# Run full CI pipeline
composer ci
```

## Deployment

### Heroku Deployment (Recommended)

1. **Install Heroku CLI** and login:
```bash
# Install Heroku CLI (if not already installed)
# macOS with Homebrew:
brew tap heroku/brew && brew install heroku

# Login to Heroku
heroku login
```

2. **Create Heroku app**:
```bash
heroku create your-app-name
```

3. **Set environment variables**:
```bash
heroku config:set GOOGLE_CLIENT_ID=your_google_client_id
heroku config:set GOOGLE_CLIENT_SECRET=your_google_client_secret
heroku config:set OAUTH_REDIRECT_URI=https://your-app-name.herokuapp.com/oauth.php
```

4. **Deploy to Heroku**:
```bash
git push heroku main
```

5. **Open your app**:
```bash
heroku open
```

### Manual Server Deployment

For deployment to a VPS or shared hosting:

1. **Upload files** to your web server
2. **Set environment variables** in your server configuration or `.htaccess`
3. **Ensure database directory is writable**:
```bash
chmod 755 database/
```
4. **Configure your web server** to point to the `public/` directory

### Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `GOOGLE_CLIENT_ID` | Google OAuth Client ID | Yes |
| `GOOGLE_CLIENT_SECRET` | Google OAuth Client Secret | Yes |
| `OAUTH_REDIRECT_URI` | OAuth redirect URL | Yes |
| `DATABASE_URL` | Database connection URL (optional) | No |
| `APP_ENV` | Application environment | No |
| `APP_DEBUG` | Debug mode (true/false) | No |

## Project Structure

```
├── database/           # Database files and schema
│   └── schema.sql     # Database schema
├── public/            # Web-accessible files
│   ├── index.php      # Main entry point
│   ├── login.php      # Login page
│   ├── oauth.php      # OAuth callback handler
│   ├── logout.php     # Logout handler
│   ├── add_transaction.php  # Transaction form
│   └── .htaccess      # Apache configuration
├── src/               # Application source code
│   ├── OAuthGoogle.php # Google OAuth handler
│   ├── Database.php   # Database connection class
│   ├── Transaction.php # Transaction model
│   └── dashboard.php  # Dashboard view
├── tests/             # Test files
│   ├── OAuthGoogleTest.php
│   ├── DatabaseTest.php
│   ├── TransactionTest.php
│   └── TransactionUITest.php
├── .github/           # GitHub Actions CI/CD
├── composer.json      # PHP dependencies
├── phpunit.xml        # PHPUnit configuration
├── phpcs.xml          # CodeSniffer configuration
├── phpstan.neon       # PHPStan configuration
├── Procfile           # Heroku deployment config
└── .env.example       # Environment variables template
```

## API Reference

### Transaction Model

```php
use FinanceTracker\Transaction;

// Create a new transaction
$transaction = Transaction::create($userId, 100.50, 'Grocery shopping');

// Find a transaction by ID
$transaction = Transaction::find(1);

// Get all transactions for a user
$transactions = Transaction::findByUserId($userId);

// Update a transaction
$transaction->update(150.00, 'Updated description');

// Delete a transaction
$transaction->delete();

// Get formatted amount
echo $transaction->getFormattedAmount(); // "$150.00" or "($50.00)"
```

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature-name`
3. Make your changes and add tests
4. Run the test suite: `composer ci`
5. Commit your changes: `git commit -am 'Add feature'`
6. Push to the branch: `git push origin feature-name`
7. Submit a pull request

## License

This project is open source and available under the [MIT License](LICENSE).

## Security

- All user input is validated and sanitized
- OAuth tokens are handled securely
- Session management follows PHP best practices
- Database queries use prepared statements

## Support

If you encounter any issues or have questions:

1. Check the [Issues](https://github.com/bholsinger09/PersonalFinanceTracker/issues) page
2. Create a new issue with detailed information
3. Include error messages, PHP version, and steps to reproduce