# Xero Integration Backend

This backend service provides integration with Xero's API for managing financial data and transactions.

## Business Requirements

-   Data Extraction: Fetch Vendors (Contacts) and Accounts from Xero via API.
-   Storage: Persist data as JSON files on disk for subsequent processing.
-   Security: Securely manage Xero OAuth2 credentials and tokens.
-   Auditability: Log extraction activities and errors.
-   User Interface: Provide a simple web UI to trigger data extraction.

## Features

-   OAuth2 authentication with Xero
-   Automatic token refresh handling
-   Fetch and store Vendors (Contacts) data
-   Fetch and store Chart of Accounts
-   JSON file storage for data persistence
-   Error handling and logging
-   Simple web interface for manual data extraction

## Technology Stack

-   PHP 8.x
-   Laravel 11.x
-   Laravel Xero OAuth2 Package
-   React (Frontend)
-   MySQL (Session Storage)
-   File System Storage (JSON)

## Setup Steps

1.  **Prerequisites**

    -   PHP 8
    -   Laravel 11
    -   Composer
    -   Node.js (v18 or higher)
    -   npm

2.  **Installation**

    ```bash
    # Clone the repository
    git clone https://github.com/francispham23/xero-integration-be.git

    # Navigate to project directory
    cd xero-integration-be

    # Install PHP dependencies
    composer install

    # Install Node.js dependencies
    npm install

    # Generate application key
    php artisan key:generate

    # Run database migrations
    php artisan migrate
    ```

3.  **Setup Xero Developer Account**
    -   Register at [Xero Developer Portal](https://developer.xero.com/)
    -   Create a sample company and load it with sample data
    -   Setup follow [this documentation](https://webfox.github.io/laravel-xero-oauth2/)
    -   Configure OAuth2 credentials in your Xero app:
        -   Set redirect URL to: `http://localhost:8000/api/xero/callback`
        -   Add required scopes: `accounting.settings.read`, `accounting.contacts.read`

## Environment Variables

Add the following variables to your `.env` file:

```env
# Xero OAuth
XERO_CLIENT_ID=your_client_id
XERO_CLIENT_SECRET=your_client_secret
XERO_REDIRECT_URI=http://localhost:8000/api/xero/auth/callback
XERO_CREDENTIAL_DISK=local
```

## System Architecture

```
[Frontend (React)]
       ↑
       |
       ↓
[Backend (Laravel)]─────→[Xero API]
       ↑                     |
       |                     ↓
       └─────────────[Disk Storage (JSON)]
```

## Backend System Components

-   OAuth2 authentication with Xero
-   API endpoints to fetch and store Vendors/Accounts
-   File storage and data extraction (accounts.json and vendors.json)
-   Error Handling & Logging

## API Endpoints

### Authentication

-   `GET /api/xero/auth/authorize` - Initiate Xero OAuth2 flow
-   `GET /api/xero/callback` - Handle OAuth2 callback
-   `POST /api/xero/auth/disconnect` - Disconnect from Xero

### Data Extraction

-   `GET /api/xero/local/vendors` - Get stored vendors data
-   `GET /api/xero/local/accounts` - Get stored accounts data
-   `POST /api/xero/sync/vendors` - Trigger vendors sync from Xero
-   `POST /api/xero/sync/accounts` - Trigger accounts sync from Xero

## Error Handling

The application handles various error scenarios:

-   OAuth2 authentication failures
-   Xero API rate limits
-   Network connectivity issues
-   Invalid data formats
-   File system errors

Errors are:

-   Logged to Laravel's logging system
-   Returned as JSON responses with appropriate HTTP status codes
-   Displayed in the UI with user-friendly messages

## Development

```bash
# Start the development server
php artisan serve

# Watch for frontend changes (React App)
npm run dev
```

## Testing

```bash
# Run PHP unit tests
php artisan test

# Run frontend tests
npm test
```

## Troubleshooting

Common issues and solutions:

1. **OAuth2 Connection Issues**

    - Verify Xero credentials in `.env`
    - Check redirect URI configuration
    - Ensure required scopes are enabled

2. **Data Sync Problems**

    - Check Xero API status
    - Verify file system permissions
    - Review Laravel logs

3. **Development Setup**
    - Clear Laravel cache: `php artisan cache:clear`
    - Reset database: `php artisan migrate:fresh`
    - Update dependencies: `composer update && npm update`
