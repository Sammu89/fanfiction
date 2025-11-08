#!/bin/bash
# ========================================
# Git Sync Utility (Interactive: Pull or Push)
# ========================================
# Usage: ./git-sync.sh [branch] [remote]
# Defaults: branch=main, remote=origin

pause() {
  echo
  read -rp "Press Enter to continue..."
}

# Go to the script directory
cd "$(dirname "$0")" || exit 1

BRANCH=${1:-main}
REMOTE=${2:-origin}

echo "========================================"
echo " Git Sync Utility"
echo " Repository: $(pwd)"
echo " Branch: $BRANCH"
echo " Remote: $REMOTE"
echo "========================================"
echo
echo "What do you want to do?"
echo "[1] Update LOCAL from SERVER  (git pull)"
echo "[2] Update SERVER from LOCAL  (git push)"
echo
read -rp "Select 1 or 2: " CHOICE

case "$CHOICE" in
  1)
    echo
    echo "========================================"
    echo " PULL: Updating local repository from remote..."
    echo "========================================"
    git fetch "$REMOTE"
    if [ $? -ne 0 ]; then
      echo "ERROR: Fetch failed! Check your network or credentials."
      pause
      exit 1
    fi

    git pull "$REMOTE" "$BRANCH"
    if [ $? -ne 0 ]; then
      echo "ERROR: Pull failed! Resolve conflicts manually."
      pause
      exit 1
    fi

    echo
    echo "Local repository updated successfully."
    pause
    ;;

  2)
    echo
    echo "========================================"
    echo " PUSH: Updating remote server with local changes..."
    echo "========================================"
    git status -s
    echo
    read -rp "Stage and push ALL local changes? (Y/N): " CONFIRM
    if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
      echo "Operation cancelled."
      pause
      exit 0
    fi

    git add .
    if [ $? -ne 0 ]; then
      echo "ERROR: Staging failed!"
      pause
      exit 1
    fi

    echo
    read -rp "Enter commit message (or press Enter for auto): " MSG
    if [ -z "$MSG" ]; then
      DATESTR=$(date +"%Y-%m-%d_%H-%M")
      MSG="Auto commit $DATESTR"
    fi

    echo "Committing with message: \"$MSG\""
    git commit -m "$MSG"
    if [ $? -ne 0 ]; then
      echo "ERROR: Commit failed or nothing to commit."
      pause
      exit 1
    fi

    echo
    echo "Pushing changes to $REMOTE/$BRANCH ..."
    git push "$REMOTE" "$BRANCH"
    if [ $? -ne 0 ]; then
      echo "ERROR: Push failed!"
      pause
      exit 1
    fi

    echo
    echo "Remote updated successfully."
    pause
    ;;

  *)
    echo "Invalid choice. Exiting."
    pause
    exit 1
    ;;
esac

