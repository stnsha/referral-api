# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

MyReferral API is a Laravel 10 healthcare referral management system for tracking and managing multi-level referrals between healthcare providers and external organizations.

## Technology Stack

- **Framework**: Laravel 10.10+ with PHP 8.1+
- **Authentication**: Custom JWT (HS256) - verified in app/Http/Middleware/TokenMiddleware.php
- **API Documentation**: L5-Swagger (OpenAPI 3.0) at /api/documentation
- **Frontend**: Vite + Tailwind CSS 4
- **Database**: MySQL 8.0+
- **Key Dependencies**:
  - barryvdh/laravel-dompdf (PDF generation)
  - maatwebsite/excel (Excel exports)
  - simplesoftwareio/simple-qrcode (QR codes)
  - guzzlehttp/guzzle (HTTP client for ODB integration)

## Development Commands

### Essential Commands

```bash
# Start development server
php artisan serve

# Run tests
php artisan test                          # Run all tests
php artisan test --filter=ReferralTest   # Run specific test
php artisan test tests/Feature/ReferralControllerTest.php  # Run specific file

# Code formatting
./vendor/bin/pint                         # Format code with PSR-12

# Database
php artisan migrate                       # Run pending migrations
php artisan migrate:fresh --seed         # Reset DB and seed with test data
php artisan db:seed                      # Seed with test data only

# API Documentation
php artisan l5-swagger:generate          # Regenerate Swagger docs
php artisan postman:generate             # Generate Postman collection

# Cache management
php artisan cache:clear
php artisan config:clear
php artisan route:list

# Frontend
npm run dev                               # Development server with hot reload
npm run build                             # Production build
```

## Architecture Overview

### API Routes
All API routes are prefixed with `/api` and are defined in `routes/api.php`. The codebase uses custom JWT authentication via the `token.auth` middleware (app/Http/Middleware/TokenMiddleware.php).

**Key Route Groups**:
- `/api/auth` - Authentication endpoints (no auth required)
- `/api/referral` - Referral CRUD operations
- `/api/external-referees` & `/api/external-organizations` - External stakeholder management
- `/api/business-units` - Business unit management
- `/api/form` & `/api/formDetails` - Dynamic form configuration
- `/api/report` - Analytics and reporting endpoints
- `/api/library` - Library data (status, priority enums)

### Code Organization

```
app/
├── Http/
│   ├── Controllers/        # API endpoint handlers (thin - delegate to services)
│   │   ├── AuthController.php
│   │   ├── ODBController.php        # JWT generation/verification logic
│   │   ├── ReferralController.php   # 114KB - main referral logic
│   │   ├── FormController.php       # Dynamic form management
│   │   └── ReportController.php     # Analytics and exports
│   ├── Middleware/
│   │   └── TokenMiddleware.php      # JWT token validation
│   └── Requests/           # Form request validation classes
├── Models/                 # Eloquent ORM models
│   ├── Referral.php
│   ├── ReferralHierarchy.php        # Multi-level referral chains
│   ├── ReferralDetails.php
│   ├── ReferralCreateForm.php       # Forms during referral creation
│   ├── ReferralReplyForm.php        # Forms in referral replies
│   ├── ReferralAttachment.php       # File uploads
│   ├── Form.php & FormDetails.php   # Dynamic form schema
│   ├── ExternalReferee.php
│   ├── ExternalOrganization.php
│   └── BusinessUnit.php
├── Services/              # (Currently minimal) - consider expanding
├── Traits/
│   ├── AccessControl.php            # Role-based filtering logic
│   └── Octopus.php                  # ODB API integration
├── Exports/               # Excel export classes (Maatwebsite)
├── Mail/                  # Email notification classes
├── Helpers/              # Global helper functions
└── Swagger/              # OpenAPI annotation classes
```

### Access Control & Role System

The `AccessControl` trait (app/Traits/AccessControl.php) handles role-based filtering:

**JWT Token Payload Fields Used**:
- `referral` (int): Role level
  - `1` = Superadmin (can access everything)
  - `2` = Admin (filtered to their business unit and outlets)
  - `0` = Normal User (filtered to their outlet only)
- `business_unit_id` (int): User's business unit
- `outlet` (array): List of outlet IDs user can access
- `exp` (int): Token expiration timestamp

**Trait Methods**:
- `isSuperadmin($jwtPayload)` - Check if referral = 1
- `isReadOnly($jwtPayload)` - Check if referral = 0 (view-only)
- `applyBusinessUnitFilter()` - Filter queries by business_unit_id
- `applyOutletFilter()` - Filter queries by outlet location
- `canAccessBusinessUnit()` - Check if user can access specific BU

**Detailed Permission Rules**:

**Role 1: Superadmin**
- Identified by `staff_department_id = 16`
- Can access all features
- Can view all referrals from all outlets and business units
- No data filtering applied

**Role 2: Admin**
- Belongs to a specific business unit
- Has access to outlets under their business unit
- Can access all features
- Can view referrals only from their own outlets (all outlets under their business unit)
- Data filtered by `business_unit_id` and outlet access list

**Role 0: Normal User**
- Belongs to a specific business unit and outlet
- Can view referrals only from their own outlet
- Can create and update referrals
- Read-only access (no write permissions) for:
  - External referees
  - External organizations
  - CSV exports
  - Forms (view only)
  - Form details (view only)

### Authentication & JWT

- Custom JWT implementation in ODBController.php (lines 11-50)
- Tokens signed with app key (HS256)
- Token verification in TokenMiddleware.php
- Tokens expire after 24 hours (86400 seconds)
- Payload decoded and attached to request as `jwt_payload`

All protected routes use `Route::middleware('token.auth')` which validates the token and ensures valid payload exists.

## Coding Standards & Conventions

### 1. Import Statements
Always use proper `use` statements at the top of files:
```php
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

// Good: Use the imported class
$referral = new Referral();

// Bad: Never use inline class paths
$referral = new \App\Models\Referral();
```

### 2. Database Transactions
Wrap all write operations in try/catch with transactions:
```php
DB::beginTransaction();
try {
    // Your database operations
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    Log::error('Error message', ['exception' => $e]);
    // Handle error
}
```

### 3. Logging
Log all important actions:
```php
Log::info('Referral created', ['referral_id' => $referral->id]);
Log::warning('Unusual activity', ['user_id' => $userId]);
Log::error('Database error', ['exception' => $e, 'sql' => $query]);
```

### 4. Exception Handling
Never use generic `\Exception`. Use specific Laravel exceptions or create domain-specific ones.

### 5. Code Formatting
Use PSR-12 standard. Run `./vendor/bin/pint` before committing.

### 6. Controller Design
Keep controllers thin - delegate business logic to service classes. Most complex logic should live in Services/ (currently underutilized).

## Database Schema Overview

Key tables include:
- `referrals` - Main referral records (114KB ReferralController handles most logic)
- `referral_hierarchies` - Multi-level referral chains
- `referral_details` - Additional referral data
- `referral_attachments` - File uploads
- `referral_create_forms` - Forms submitted during referral creation
- `referral_reply_forms` - Forms submitted in replies to referrals
- `forms` - Dynamic form templates per business unit
- `form_details` - Form field definitions
- `business_units` - Healthcare facility divisions
- `external_referees` - External specialist contacts
- `external_organizations` - External healthcare facilities

See `database/migrations/` for schema details.

## API Documentation

Generate and access Swagger UI:
```bash
php artisan l5-swagger:generate
# Then visit: http://127.0.0.1:8000/api/documentation
```

Swagger annotations use OpenAPI 3.0 format. Base schema definitions are in app/Http/Controllers/Controller.php (lines 9-142).

## Testing

Tests are in `tests/` directory:
- `tests/Feature/` - Integration tests
- `tests/Unit/` - Unit tests

Run tests with `php artisan test`. Configuration in `phpunit.xml`.

Current test coverage includes:
- `ReferralControllerTest.php` - Referral CRUD operations

## Environment Configuration

Key .env variables:
- `APP_KEY` - Auto-generated, used for JWT signing
- `DB_*` - Database credentials
- `ODB_API_*` - ODB integration credentials (authentication)
- `MAIL_*` - Email configuration (Mailpit for local development)
- `LOG_LEVEL` - Set to 'error' in production

See README.md for complete environment setup instructions.

## Common Development Patterns

### Creating a New Endpoint

1. **Define route** in `routes/api.php` with `token.auth` middleware
2. **Create controller** in `app/Http/Controllers/`
3. **Add request validation** in `app/Http/Requests/`
4. **Use AccessControl trait** to filter data by role
5. **Add Swagger annotations** using OpenAPI format
6. **Implement database transactions** for write operations
7. **Add logging** for audit trail

### Querying with Role Filtering

Use the AccessControl trait in controllers:
```php
use App\Traits\AccessControl;

class ReferralController extends Controller {
    use AccessControl;

    public function index(Request $request) {
        $jwtPayload = $request->get('jwt_payload');
        $query = Referral::query();

        // Apply filters based on role
        $this->applyBusinessUnitFilter($query, $jwtPayload);
        $this->applyOutletFilter($query, $jwtPayload, 'location');

        return $query->get();
    }
}
```

## Key Files Reference

- **Routing**: `routes/api.php`
- **JWT Logic**: `app/Http/Controllers/ODBController.php`
- **Access Control**: `app/Traits/AccessControl.php`
- **Token Verification**: `app/Http/Middleware/TokenMiddleware.php`
- **Swagger Schemas**: `app/Http/Controllers/Controller.php`
- **Main Models**: `app/Models/Referral.php`, `ReferralHierarchy.php`

## Additional Resources

- Laravel 10 docs: https://laravel.com/docs/10.x
- L5-Swagger: https://github.com/DarkaOnLine/L5-Swagger
- OpenAPI 3.0: https://swagger.io/specification/
- PSR-12: https://www.php-fig.org/psr/psr-12/
