@echo off
REM Batch file to run JWT token cleanup cron job
REM Schedule this in Windows Task Scheduler to run daily at midnight
REM 
REM Requirements: 3.5
REM - Remove expired entries from token_blacklist table
REM - Remove expired entries from refresh_tokens table

REM Change to the script directory
cd /d "%~dp0"

REM Run the PHP script
php cleanup_tokens.php

REM Exit with the PHP script's exit code
exit /b %ERRORLEVEL%
