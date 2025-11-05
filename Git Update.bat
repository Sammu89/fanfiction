```batch
@echo off
setlocal enabledelayedexpansion
REM ========================================
REM Improved Git Sync Script (Local + Remote)
REM ========================================
REM Usage: script.bat [branch] [remote] [rebase]
REM Defaults: branch=main, remote=origin, rebase=no
set "REPO_PATH=%~dp0"
cd /d "%REPO_PATH%"
set "BRANCH=%~1"
if "%BRANCH%"=="" set "BRANCH=main"
set "REMOTE=%~2"
if "%REMOTE%"=="" set "REMOTE=origin"
set "USE_REBASE=%~3"
if /i "%USE_REBASE%"=="rebase" (set "PULL_CMD=git pull --rebase %REMOTE% %BRANCH%") else (set "PULL_CMD=git pull %REMOTE% %BRANCH%")
echo ========================================
echo Starting Git sync at %DATE% %TIME%...
echo ========================================
echo.
echo Starting Git sync on branch '%BRANCH%' from remote '%REMOTE%'...
REM Step 1: Check if repo is initialized
git rev-parse --is-inside-work-tree >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Not a Git repository! Initialize first.
    echo ERROR: Not a Git repository!
    pause
    exit /b 1
)
REM Step 2: Pull latest changes
echo.
echo Pulling latest changes...
%PULL_CMD% 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Pull failed! Check network or credentials.
    echo ERROR: Pull failed!
    pause
    exit /b 1
)
REM Step 3: Check for merge conflicts
set "CONFLICT="
for /f %%i in ('git diff --name-only --diff-filter=U') do set CONFLICT=1
if defined CONFLICT (
    echo.
    echo WARNING: Merge conflicts detected! Resolve manually.
    echo WARNING: Merge conflicts detected!
    pause
    exit /b 1
)
REM Step 4: Check for local changes
git status --porcelain > changes.txt
for %%A in (changes.txt) do set "FILESIZE=%%~zA"
if "%FILESIZE%"=="0" (
    echo No local changes detected.
    echo No local changes. Remote updates pulled.
    del changes.txt
    goto :done
)
del changes.txt
REM Step 5: Review and stage changes
echo.
echo Local changes detected. Review with 'git status':
git status -s
echo.
set /p "CONFIRM=Stage all changes? (Y/N): "
if /i not "%CONFIRM%"=="Y" (
    echo Aborted by user.
    echo Aborted staging.
    pause
    exit /b 0
)
git add . 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Staging failed!
    echo ERROR: Staging failed!
    pause
    exit /b 1
)
REM Step 6: Commit with custom message
echo.
set /p "MSG=Enter commit message (or press Enter for auto): "
if "%MSG%"=="" (
    REM Locale-agnostic timestamp (using WMIC for YYYY-MM-DD_HH-MM)
    for /f "tokens=2 delims==" %%a in ('wmic OS Get LocalDateTime /value') do set "DT=%%a"
    set "DATESTR=%DT:~0,4%-%DT:~4,2%-%DT:~6,2%_%DT:~8,2%-%DT:~10,2%"
    set "MSG=Auto sync %DATESTR%"
)
echo Committing with message: "%MSG%"
git commit -m "%MSG%" 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Commit failed!
    echo ERROR: Commit failed!
    pause
    exit /b 1
)
REM Step 7: Push changes
echo.
echo Pushing changes...
git push %REMOTE% %BRANCH% 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Push failed!
    echo ERROR: Push failed!
    pause
    exit /b 1
)
:done
echo.
echo ========================================
echo Sync complete.
echo ========================================
echo Sync complete.
pause
endlocal
```