#!/bin/bash
# ========================================
# Developer Tools Menu - Linux/Mac
# All-in-one utility script
# ========================================

pause() {
  echo
  read -rp "Press Enter to continue..."
}

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

DEFAULT_REPO_DIR=$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null || echo "$SCRIPT_DIR")
REPO_DIR=${REPO_DIR:-$DEFAULT_REPO_DIR}

show_repo_info() {
  echo "Current git root target: $REPO_DIR"
}

change_repo_dir() {
  echo
  read -rp "Enter path to the desired git root (blank to keep current): " NEW_DIR
  if [ -z "$NEW_DIR" ]; then
    echo "Git root unchanged."
    return
  fi

  if ! NEW_ABS=$(cd "$NEW_DIR" 2>/dev/null && pwd); then
    echo "ERROR: Cannot access '$NEW_DIR'."
    return
  fi

  if ! git -C "$NEW_ABS" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "ERROR: '$NEW_ABS' is not inside a git repository."
    return
  fi

  REPO_DIR=$(git -C "$NEW_ABS" rev-parse --show-toplevel)
  echo "Git root updated to: $REPO_DIR"
}

# Load nvm if present (so npm works when we need it)
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh" >/dev/null 2>&1
nvm use default >/dev/null 2>&1 || true

BRANCH=${BRANCH:-main}
REMOTE=${REMOTE:-origin}

# ========================================
# Main Menu Loop
# ========================================
while true; do
  clear
  echo "********************************************"
  echo "*                                          *"
  echo "*         Developer Tools Menu             *"
  echo "*                                          *"
  echo "********************************************"
  show_repo_info
  echo "********************************************"
  echo "*                                          *"
  echo "*  [1] Claude Code (dangerously)           *"
  echo "*  [2] Codex (--yolo)                      *"
  echo "*  [3] Gemini CLI (--yolo)                 *"
  echo "*  [4] Git: Update LOCAL from SERVER       *"
  echo "*  [5] Git: Update SERVER from LOCAL       *"
  echo "*  [6] Run npm run build (from script dir) *"
  echo "*  [7] Change git root target              *"
  echo "*  [0] Exit                                *"
  echo "*                                          *"
  echo "********************************************"
  echo
  read -rp "Select an option (0-7): " CHOICE

  case "$CHOICE" in
    1)
      # ========================================
      # Option 1: Claude Code
      # ========================================
      echo
      echo "========================================"
      echo " Starting Claude Code..."
      echo " (dangerously skip permissions)"
      echo "========================================"
      echo
      claude --dangerously-skip-permissions
      pause
      ;;

    2)
      # ========================================
      # Option 2: Codex
      # ========================================
      echo
      echo "========================================"
      echo " Starting Codex --yolo..."
      echo "========================================"
      echo
      codex --yolo
      pause
      ;;

    3)
      # ========================================
      # Option 3: Gemini CLI
      # ========================================
      echo
      echo "========================================"
      echo " Starting Gemini CLI --yolo..."
      echo "========================================"
      echo
      gemini --yolo
      pause
      ;;

    4)
      # ========================================
      # Option 4: Git Pull (Force Local to Match Remote)
      # ========================================
      echo
      echo "========================================"
      echo " PULL: Forcing local repo to EXACTLY match $REMOTE/$BRANCH"
      echo " → Discarding ALL local changes automatically..."
      echo "========================================"

      if ! pushd "$REPO_DIR" >/dev/null; then
        echo "ERROR: Cannot access repository at $REPO_DIR"
        pause
        continue
      fi

      git fetch "$REMOTE" "$BRANCH" --quiet
      git reset --hard "$REMOTE/$BRANCH"
      git clean -fd

      LATEST=$(git log --format="%h" -n 1)
      echo
      echo "Local repository successfully forced to latest remote version."
      echo "HEAD is now at $LATEST"

      if [ -d "node_modules" ]; then
        echo
        echo "**node_modules detected → running npm run build automatically...**"
        npm run build
        if [ $? -eq 0 ]; then
          echo "Build completed successfully ✔"
        else
          echo "ERROR: Build failed ✘"
        fi
      else
        echo
        echo "No node_modules folder found → skipping build (this is intentional)"
      fi

      popd >/dev/null
      pause
      ;;

    5)
      # ========================================
      # Option 5: Git Push (Update Remote from Local)
      # ========================================
      echo
      echo "========================================"
      echo " PUSH: Updating remote with local changes"
      echo "========================================"

      if ! pushd "$REPO_DIR" >/dev/null; then
        echo "ERROR: Cannot access repository at $REPO_DIR"
        pause
        continue
      fi

      git status -s
      echo
      read -rp "Stage and push ALL local changes? (Y/N): " CONFIRM
      if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
        echo "Operation cancelled."
        popd >/dev/null
        pause
        continue
      fi

      git add .
      read -rp "Commit message (or Enter for auto): " MSG
      [ -z "$MSG" ] && MSG="Auto commit $(date +'%Y-%m-%d %H:%M')"

      git commit -m "$MSG" || echo "Nothing to commit"

      echo
      echo "Pushing to $REMOTE/$BRANCH ..."
      git push --force "$REMOTE" "$BRANCH"

      if [ $? -ne 0 ]; then
        echo
        echo "Push failed. Attempting automatic repair..."

        echo "Setting core.autocrlf to input"
        git config --global core.autocrlf input

        CUR_BRANCH=$(git branch --show-current)

        if [ "$CUR_BRANCH" != "$BRANCH" ]; then
          echo "Renaming local branch '$CUR_BRANCH' → '$BRANCH'"
          git branch -m "$CUR_BRANCH" "$BRANCH"
        fi

        echo "Retrying push..."
        git push --force -u "$REMOTE" "$BRANCH"

        if [ $? -ne 0 ]; then
          echo
          echo "ERROR: Push failed even after auto-fix."
          popd >/dev/null
          pause
          continue
        fi
      fi

      echo
      echo "Remote updated successfully."
      popd >/dev/null
      pause
      ;;

    6)
      # ========================================
      # Option 6: NPM Build
      # ========================================
      echo
      echo "========================================"
      echo " Running npm run build (from script dir)..."
      echo "========================================"

      if ! pushd "$SCRIPT_DIR" >/dev/null; then
        echo "ERROR: Cannot access script directory at $SCRIPT_DIR"
        pause
        continue
      fi

      echo
      npm run build
      if [ $? -eq 0 ]; then
        echo
        echo "Build completed successfully ✔"
      else
        echo
        echo "ERROR: Build failed ✘"
      fi
      popd >/dev/null
      pause
      ;;

    7)
      change_repo_dir
      pause
      ;;

    0)
      # ========================================
      # Exit
      ========================================
      echo
      echo "Goodbye!"
      exit 0
      ;;

    *)
      echo "Invalid choice."
      pause
      ;;
  esac
done
