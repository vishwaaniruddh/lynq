# ADV CRM Users Module - Database Setup

## Database Information

**Database Name**: `clarity_db`
**Host**: `localhost`
**User**: `root`
**Password**: (empty)
**Charset**: `utf8mb4`
**Collation**: `utf8mb4_unicode_ci`

## Database Creation

The database was created using the script `create_database.php` with the following command:
```sql
CREATE DATABASE IF NOT EXISTS `clarity_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
```

## Tables Created

The following 10 tables were created in the `clarity_db` database:

### Core Tables
1. **companies** - Stores ADV and contractor company information
2. **roles** - User roles with hierarchy levels and company type restrictions
3. **permissions** - System permissions with module.action structure
4. **users** - User accounts with company and role associations

### Junction Tables
5. **role_permissions** - Maps roles to their permissions
6. **company_permissions** - Tracks delegated permissions to contractor companies

### Security & Audit Tables
7. **user_sessions** - Secure session management
8. **user_audit_log** - Audit trail for user operations
9. **permission_audit_log** - Audit trail for permission operations

### System Tables
10. **migrations** - Tracks executed database migrations

## Default Data Inserted

### Companies
- ADV Systems (ID: 1, Type: ADV, Status: ACTIVE)

### Roles (9 roles)
- Super Admin (Level 10, ADV)
- ADV Admin (Level 8, ADV)
- ADV Manager (Level 6, ADV)
- ADV User (Level 4, ADV)
- Contractor Admin (Level 7, CONTRACTOR)
- Contractor Manager (Level 5, CONTRACTOR)
- Contractor User (Level 3, CONTRACTOR)
- Engineer (Level 2, CONTRACTOR)
- Viewer (Level 1, BOTH)

### Permissions (26 permissions)
- User management: users.create, users.read, users.update, users.delete, users.manage
- Company management: companies.create, companies.read, companies.update, companies.delete, companies.manage
- Role management: roles.create, roles.read, roles.update, roles.delete, roles.manage
- Permission management: permissions.delegate, permissions.revoke, permissions.read, permissions.manage
- System administration: system.admin, system.audit, system.backup
- Master data: master_data.read, master_data.manage
- Admin functions: admin.dashboard, admin.reports

### Role-Permission Mappings (35 mappings)
- Super Admin: All permissions
- ADV Admin: User management, company read, role read, permission delegation
- Contractor Admin: Basic user CRUD operations

## Connection Configuration

The database connection is managed by the `DatabaseConfig` class in `config/database.php`:

```php
class DatabaseConfig {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbname = "clarity_db";
    
    // Singleton pattern with prepared statements
}
```

## Migration System

- Migrations are tracked in the `migrations` table
- Migration files are stored in `migrations/` directory
- Run migrations using: `php migrate.php`
- Migration naming convention: `YYYY_MM_DD_HHMMSS_description.php`

## Verification

To verify the database setup:
```bash
php check_tables.php
```

This will show all tables and their row counts in the `clarity_db` database.

## Property-Based Testing

The database schema integrity is validated using property-based tests:
```bash
cd tests
php run_property_tests.php
```

**Property 15: Referential Integrity Maintenance** - Validates that foreign key constraints prevent orphaned records and maintain data integrity.

## Security Features

- All queries use prepared statements
- Foreign key constraints enforce referential integrity
- Password hashing using PHP's `password_hash()`
- Session management with expiration
- Audit logging for all operations
- Input validation and sanitization