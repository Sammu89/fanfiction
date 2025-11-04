@echo off
REM === Two-way Git Sync Script ===
cd /d "C:\xampp\htdocs\smpt\wp-content\plugins\fanfiction"

echo =========================================
echo 🔄 Syncing with GitHub (pull + push)
echo =========================================

REM Step 1: Pull latest changes from GitHub
git pull origin main

REM Step 2: Add all local changes
git add .

REM Step 3: Commit with timestamp
for /f "tokens=1-3 delims=/ " %%a in ("%date%") do (
    for /f "tokens=1-2 delims=: " %%x in ("%time%") do (
        set datestr=%%a-%%b-%%c_%%x-%%y
    )
)
set msg=Auto sync %datestr%
git commit -m "%msg%" >nul 2>&1

REM Step 4: Push to GitHub
git push origin main

echo =========================================
echo ✅ Sync complete! Local and remote are up to date.
echo =========================================
pause
