# Cron Jobs

This directory contains scheduled tasks for the ADV CRM system.

## IP Lock Expiry Cron Job

**File:** `expire_ip_locks.php`

**Purpose:** Automatically expires timed-out IP locks (after 20 minutes) and logs timeout events to the audit log.

**Requirements:** 4.3, 9.3
- 4.3: Auto-expire locks after 20-minute timeout
- 9.3: Record timeout events in audit log

### Scheduling

This cron job should run frequently (every minute) to ensure locks are expired promptly.

#### Linux/Unix (crontab)

Run every minute:
```bash
* * * * * php /path/to/clarity/new_crm/cron/expire_ip_locks.php >> /path/to/logs/cron.log 2>&1
```

#### Windows Task Scheduler

1. Open Task Scheduler
2. Create a new Basic Task
3. Set trigger: Daily, repeat every 1 minute
4. Set action: Start a program
   - Program: `php.exe` (or full path to PHP)
   - Arguments: `C:\path\to\clarity\new_crm\cron\expire_ip_locks.php`
   - Start in: `C:\path\to\clarity\new_crm\cron`

Alternatively, use the provided batch file:
- Program: `C:\path\to\clarity\new_crm\cron\run_expire_ip_locks.bat`

### Logs

Logs are written to: `clarity/new_crm/logs/cron_expire_ip_locks.log`

### Manual Execution

To run manually:

```bash
# Linux/Unix
php /path/to/clarity/new_crm/cron/expire_ip_locks.php

# Windows
php C:\path\to\clarity\new_crm\cron\expire_ip_locks.php
# or
run_expire_ip_locks.bat
```

---

## Overdue Notifications Cron Job

**File:** `overdue_notifications.php`

**Purpose:** Sends reminder notifications to users who have pending receives that are overdue (older than the configured threshold).

**Requirements:** 11.4
- Check for overdue pending receives daily
- Send reminder notifications to recipients

### Configuration

The overdue threshold can be configured by defining `OVERDUE_THRESHOLD_DAYS` constant. Default is 7 days.

### Scheduling

#### Linux/Unix (crontab)

Run daily at 9:00 AM:
```bash
0 9 * * * php /path/to/clarity/new_crm/cron/overdue_notifications.php >> /path/to/logs/cron.log 2>&1
```

#### Windows Task Scheduler

1. Open Task Scheduler
2. Create a new Basic Task
3. Set trigger: Daily at 9:00 AM
4. Set action: Start a program
   - Program: `php.exe` (or full path to PHP)
   - Arguments: `C:\path\to\clarity\new_crm\cron\overdue_notifications.php`
   - Start in: `C:\path\to\clarity\new_crm\cron`

Alternatively, use the provided batch file:
- Program: `C:\path\to\clarity\new_crm\cron\run_overdue_notifications.bat`

### Logs

Logs are written to: `clarity/new_crm/logs/cron_overdue_notifications.log`

### Manual Execution

To run manually:

```bash
# Linux/Unix
php /path/to/clarity/new_crm/cron/overdue_notifications.php

# Windows
php C:\path\to\clarity\new_crm\cron\overdue_notifications.php
# or
run_overdue_notifications.bat
```

### Exit Codes

- `0`: Success
- `1`: Failure (check logs for details)

---

## JWT Token Cleanup Cron Job

**File:** `cleanup_tokens.php`

**Purpose:** Removes expired refresh tokens and blacklist entries to prevent unbounded growth of the database tables.

**Requirements:** 3.5
- Remove expired entries from token_blacklist table
- Remove expired entries from refresh_tokens table

### Scheduling

This cron job should run daily to clean up expired tokens.

#### Linux/Unix (crontab)

Run daily at midnight:
```bash
0 0 * * * php /path/to/clarity/new_crm/cron/cleanup_tokens.php >> /path/to/logs/cron.log 2>&1
```

#### Windows Task Scheduler

1. Open Task Scheduler
2. Create a new Basic Task
3. Set trigger: Daily at midnight
4. Set action: Start a program
   - Program: `php.exe` (or full path to PHP)
   - Arguments: `C:\path\to\clarity\new_crm\cron\cleanup_tokens.php`
   - Start in: `C:\path\to\clarity\new_crm\cron`

Alternatively, use the provided batch file:
- Program: `C:\path\to\clarity\new_crm\cron\run_cleanup_tokens.bat`

### Logs

Logs are written to: `clarity/new_crm/logs/cron_cleanup_tokens.log`

### Manual Execution

To run manually:

```bash
# Linux/Unix
php /path/to/clarity/new_crm/cron/cleanup_tokens.php

# Windows
php C:\path\to\clarity\new_crm\cron\cleanup_tokens.php
# or
run_cleanup_tokens.bat
```
