@echo off
REM Overdue Notifications Cron Job Runner
REM Schedule this in Windows Task Scheduler to run daily at 9:00 AM
REM 
REM Requirements: 11.4
REM - Check for overdue pending receives daily
REM - Send reminder notifications to recipients

cd /d "%~dp0"
php overdue_notifications.php

if %ERRORLEVEL% NEQ 0 (
    echo Cron job failed with error code %ERRORLEVEL%
    exit /b %ERRORLEVEL%
)

echo Cron job completed successfully
