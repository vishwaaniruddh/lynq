@echo off
REM Batch file to run IP lock expiry cron job
REM Schedule this in Windows Task Scheduler to run every minute
REM 
REM Requirements: 4.3, 9.3
REM - 4.3: Auto-expire locks after 20-minute timeout
REM - 9.3: Record timeout events in audit log

REM Change to the script directory
cd /d "%~dp0"

REM Run the PHP script
php expire_ip_locks.php

REM Exit with the PHP script's exit code
exit /b %ERRORLEVEL%
