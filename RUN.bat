@echo off
setlocal EnableDelayedExpansion
pushd "%~dp0"

rem ========================================
rem Developer Tools Menu - Windows
rem All-in-one utility script
rem ========================================

:MENU
cls
echo.
echo  ********************************************
echo  *                                          *
echo  *         Developer Tools Menu             *
echo  *                                          *
echo  ********************************************
echo    Repository: %cd%
echo.
echo  ********************************************
echo  *                                          *
echo  *  [1] Claude Code (dangerously)           *
echo  *  [2] Codex (--yolo)                      *
echo  *  [3] Gemini CLI (--yolo)                 *
echo  *  [4] Git: Update LOCAL from SERVER       *
echo  *  [5] Git: Update SERVER from LOCAL       *
echo  *  [6] Run npm run build                   *
echo  *  [0] Exit                                *
echo  *                                          *
echo  ********************************************
echo.
set "CHOICE="
set /p CHOICE="Select an option (0-6): "

if "%CHOICE%"=="1" goto CLAUDE
if "%CHOICE%"=="2" goto CODEX
if "%CHOICE%"=="3" goto GEMINI
if "%CHOICE%"=="4" goto GIT_PULL
if "%CHOICE%"=="5" goto GIT_PUSH
if "%CHOICE%"=="6" goto NPM_BUILD
if "%CHOICE%"=="0" goto END
echo Invalid choice.
pause
goto MENU

rem ========================================
rem Option 1: Claude Code
rem ========================================
:CLAUDE
echo.
echo ========================================
echo  Starting Claude Code...
echo  (dangerously skip permissions)
echo ========================================
echo.
claude --dangerously-skip-permissions
pause
goto MENU

rem ========================================
rem Option 2: Codex
rem ========================================
:CODEX
echo.
echo ========================================
echo  Starting Codex --yolo...
echo ========================================
echo.
codex --yolo
pause
goto MENU

rem ========================================
rem Option 3: Gemini CLI
rem ========================================
:GEMINI
echo.
echo ========================================
echo  Starting Gemini CLI --yolo...
echo ========================================
echo.
gemini --yolo
pause
goto MENU

rem ========================================
rem Option 4: Git Pull (Force Local to Match Remote)
rem ========================================
:GIT_PULL
echo.
echo ========================================
echo  FORCING local repo to match remote...
echo  Discarding ALL local changes...
echo ========================================
echo.

git fetch origin main
git reset --hard origin/main
git clean -fd

echo.
echo Local repository successfully forced to latest remote version.
echo.
echo Current commit:
git log --oneline -1

if exist "node_modules\" (
  echo.
  echo **node_modules detected - running npm run build...**
  call npm run build
  if !errorlevel! EQU 0 (
    echo Build completed successfully.
  ) else (
    echo ERROR: Build failed.
  )
) else (
  echo.
  echo No node_modules folder found. Skipping build ^(intentional^)
)

echo.
pause
goto MENU

rem ========================================
rem Option 5: Git Push (Update Remote from Local)
rem ========================================
:GIT_PUSH
echo.
echo ========================================
echo  PUSH: Updating remote with local changes
echo ========================================
echo.

git status -s
echo.
set "CONFIRM="
set /p CONFIRM="Stage and push ALL local changes? (Y/N): "
if /i NOT "%CONFIRM%"=="Y" (
  echo Operation cancelled.
  pause
  goto MENU
)

git add .

set "MSG="
set /p MSG="Enter commit message (or press Enter for auto): "
if "%MSG%"=="" (
  for /f "tokens=2 delims==." %%a in ('"wmic OS Get localdatetime /value"') do set "dt=%%a"
  set "MSG=Auto commit !dt:~0,4!-!dt:~4,2!-!dt:~6,2!_!dt:~8,4!"
)

echo Committing with message: "%MSG%"
git commit -m "%MSG%" || echo Nothing to commit - skipping.

echo.
echo Pushing to origin/main ...
git push --force origin main

if !errorlevel! NEQ 0 (
  echo.
  echo Push failed. Attempting automatic repair...

  echo Setting core.autocrlf to input
  git config --global core.autocrlf input

  for /f %%b in ('git branch --show-current') do set "CUR_BRANCH=%%b"

  if /i NOT "!CUR_BRANCH!"=="main" (
    echo Renaming local branch "!CUR_BRANCH!" to "main"
    git branch -m "!CUR_BRANCH!" main
  )

  echo Retrying push...
  git push --force -u origin main

  if !errorlevel! NEQ 0 (
    echo.
    echo ERROR: Push failed even after auto-fix.
    pause
    goto MENU
  )
)

echo.
echo Remote updated successfully.
pause
goto MENU

rem ========================================
rem Option 6: NPM Build
rem ========================================
:NPM_BUILD
echo.
echo ========================================
echo  Running npm run build (from script dir)...
echo ========================================
echo.
pushd "%~dp0"
call npm run build
set "NPM_ERROR=%ERRORLEVEL%"
popd
if %NPM_ERROR%==0 (
  echo.
  echo Build completed successfully.
) else (
  echo.
  echo ERROR: Build failed.
)
echo.
pause
goto MENU

rem ========================================
rem Exit
rem ========================================
:END
echo.
echo Goodbye!
popd
endlocal
pause
