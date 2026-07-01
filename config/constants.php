<?php
/**
 * Constants for ADV CRM Users Module
 */

// Company Types
define('COMPANY_TYPE_ADV', 'ADV');
define('COMPANY_TYPE_CONTRACTOR', 'CONTRACTOR');

// Company Status
define('COMPANY_STATUS_ACTIVE', 'ACTIVE');
define('COMPANY_STATUS_INACTIVE', 'INACTIVE');
define('COMPANY_STATUS_SUSPENDED', 'SUSPENDED');

// User Status
define('USER_STATUS_ACTIVE', 1);
define('USER_STATUS_INACTIVE', 0);
define('USER_STATUS_LOCKED', 2);

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('SESSION_REGENERATE_INTERVAL', 300); // 5 minutes

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// Audit Actions
define('AUDIT_ACTION_CREATE', 'CREATE');
define('AUDIT_ACTION_UPDATE', 'UPDATE');
define('AUDIT_ACTION_DELETE', 'DELETE');
define('AUDIT_ACTION_LOGIN', 'LOGIN');
define('AUDIT_ACTION_LOGOUT', 'LOGOUT');
define('AUDIT_ACTION_PERMISSION_GRANT', 'PERMISSION_GRANT');
define('AUDIT_ACTION_PERMISSION_REVOKE', 'PERMISSION_REVOKE');

// Permission Modules
define('MODULE_USERS', 'users');
define('MODULE_COMPANIES', 'companies');
define('MODULE_ROLES', 'roles');
define('MODULE_PERMISSIONS', 'permissions');
define('MODULE_SYSTEM', 'system');
define('MODULE_ADMIN', 'admin');
define('MODULE_MASTER_DATA', 'master_data');

// Permission Actions
define('ACTION_CREATE', 'create');
define('ACTION_READ', 'read');
define('ACTION_UPDATE', 'update');
define('ACTION_DELETE', 'delete');
define('ACTION_MANAGE', 'manage');
define('ACTION_DELEGATE', 'delegate');

// Security Event Types
define('SECURITY_EVENT_LOGIN_SUCCESS', 'LOGIN_SUCCESS');
define('SECURITY_EVENT_LOGIN_FAILED', 'LOGIN_FAILED');
define('SECURITY_EVENT_LOGOUT', 'LOGOUT');
define('SECURITY_EVENT_ACCOUNT_LOCKOUT', 'ACCOUNT_LOCKOUT');
define('SECURITY_EVENT_UNAUTHORIZED_ACCESS', 'UNAUTHORIZED_ACCESS');
define('SECURITY_EVENT_CROSS_COMPANY_ACCESS', 'CROSS_COMPANY_ACCESS');

// Security Severity Levels
define('SECURITY_SEVERITY_INFO', 'INFO');
define('SECURITY_SEVERITY_WARNING', 'WARNING');
define('SECURITY_SEVERITY_CRITICAL', 'CRITICAL');

// Password Policy
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('PASSWORD_HISTORY_COUNT', 5);

// IP Restriction Types
define('IP_RESTRICTION_WHITELIST', 'WHITELIST');
define('IP_RESTRICTION_BLACKLIST', 'BLACKLIST');