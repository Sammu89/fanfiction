@echo off
setlocal enabledelayedexpansion
title Git Sync Utility (Pull or Push)

REM ========================================
REM Git Sync Utility (Interactive: Pull or Push)
REM ========================================
REM Usage: git-sync.bat [branch] [remote]
REM Defaults: branch=main, remote=origin
set "REPO_PATH=%~dp0"
cd /d "%REPO_PATH%"

set "BRANCH=%~1"
if "%BRANCH%"=="" set "BRANCH=main"
set "REMOTE=%~2"
if "%REMOTE%"=="" set "REMOTE=origin"

echo ========================================
echo Git Sync Utility
echo Repository: %REPO_PATH%
echo Branch: %BRANCH%
echo Remote: %REMOTE%
echo ========================================
echo.
echo What do you want to do?
echo [1] Update LOCAL from SERVER  (git pull)
echo [2] Update SERVER from LOCAL  (git push)
echo.
set /p "CHOICE=Select 1 or 2: "

if "%CHOICE%"=="1" goto :pull
if "%CHOICE%"=="2" goto :push

echo Invalid choice. Exiting.
pause
exit /b 1

:: ================================
:pull
echo.
echo ========================================
echo PULL: Updating local repository from remote...
echo ========================================
git fetch %REMOTE%
if %errorlevel% neq 0 (
    echo ERROR: Fetch failed! Check your network or credentials.
    pause
    exit /b 1
)

git pull %REMOTE% %BRANCH%
if %errorlevel% neq 0 (
    echo ERROR: Pull failed! Resolve conflicts manually.
    pause
    exit /b 1
)

echo.
echo Local repository updated successfully.
pause
exit /b 0

:: ================================
:push
echo.
echo ========================================
echo PUSH: Updating remote server with local changes...
echo ========================================
git status -s
echo.
set /p "CONFIRM=Stage and push ALL local changes? (Y/N): "
if /i not "%CONFIRM%"=="Y" (
    echo Operation cancelled.
    pause
    exit /b 0
)

git add .
if %errorlevel% neq 0 (
    echo ERROR: Staging failed!
    pause
    exit /b 1
)

echo.
set /p "MSG=Enter commit message (or press Enter for auto): "
if "%MSG%"=="" (
    for /f "tokens=2 delims==" %%a in ('wmic OS Get LocalDateTime /value') do set "DT=%%a"
    set "DATESTR=%DT:~0,4%-%DT:~4,2%-%DT:~6,2%_%DT:~8,2%-%DT:~10,2%"
    set "MSG=Auto commit %DATESTR%"
)

echo Committing with message: "%MSG%"
git commit -m "%MSG%"
if %errorlevel% neq 0 (
    echo ERROR: Commit failed or nothing to commit.
    pause
    exit /b 1
)

echo.
echo Pushing changes to %REMOTE%/%BRANCH% ...
git push %REMOTE% %BRANCH%
if %errorlevel% neq 0 (
    echo ERROR: Push failed!
    pause
    exit /b 1
)

echo.
echo Remote updated successfully.
pause
exit /b 0
