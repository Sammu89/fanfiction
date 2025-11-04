@echo off
REM === Auto Git Push Script ===
cd /d "C:\xampp\htdocs\smpt\wp-content\plugins\fanfiction"

echo =========================================
echo Updating local repo and pushing to GitHub
echo =========================================

REM Add all changes
git add .

REM Commit with timestamp
for /f "tokens=1-3 delims=/ " %%a in ("%date%") do (
    for /f "tokens=1-2 delims=: " %%x in ("%time%") do (
        set datestr=%%a-%%b-%%c_%%x-%%y
    )
)
set msg=Auto update %datestr%
git commit -m "%msg%"

REM Push to GitHub
git push origin main

echo =========================================
echo ✅ Push complete! Repo is updated.
echo =========================================
pause
