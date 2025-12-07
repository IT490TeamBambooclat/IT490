#!/usr/bin/env bash
# Safety options:
# -e : exit on error
# -u : treat unset vars as errors
# -o pipefail : pipeline fails if any command fails
set -euo pipefail

########################################
# DB settings
########################################
DB_NAME="deployment"
DB_USER="admin"
DB_PASS="Chrisb200!"
TABLE_NAME="bundle_deployments"

########################################
# QA cluster settings
########################################
SSH_USER="deployment"

# >>> EDIT THESE TO YOUR REAL QA HOSTNAMES / IPs <<<
QA_FRONTEND_HOST="qa-frontend"   # e.g. 192.168.195.101
QA_BACKEND_HOST="qa-backend"     # e.g. 192.168.195.102
QA_DMZ_HOST="qa-dmz"             # e.g. 192.168.195.103

QA_REMOTE_BASE="/var/jobseek"
########################################

mysql_fetch() {
  mysql -N -B -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1"
}

if [[ $# -lt 1 || $# -gt 2 ]]; then
  echo "Usage: $0 <bundle_name> [version_number]"
  echo "  If version_number is omitted, rolls back to previous version."
  exit 1
fi

BUNDLE_NAME="$1"

if [[ $# -eq 2 ]]; then
  TARGET_VERSION="$2"
  echo "[INFO] Rolling back QA bundle '$BUNDLE_NAME' to version $TARGET_VERSION (explicit)."
else
  echo "[INFO] Determining previous version for bundle '$BUNDLE_NAME'..."

  CURRENT_VERSION=$(mysql_fetch "SELECT MAX(version_number)
                                 FROM $TABLE_NAME
                                 WHERE bundle_name='$BUNDLE_NAME';")

  if [[ -z "${CURRENT_VERSION:-}" ]]; then
    echo "[ERROR] No versions found for bundle '$BUNDLE_NAME'."
    exit 1
  fi

  TARGET_VERSION=$(mysql_fetch "SELECT MAX(version_number)
                                FROM $TABLE_NAME
                                WHERE bundle_name='$BUNDLE_NAME'
                                  AND version_number < $CURRENT_VERSION;")

  if [[ -z "${TARGET_VERSION:-}" ]]; then
    echo "[ERROR] No previous version found for bundle '$BUNDLE_NAME' (current is $CURRENT_VERSION)."
    exit 1
  fi

  echo "[INFO] Current version: $CURRENT_VERSION, rolling back QA to: $TARGET_VERSION"
fi

# Get the DB row for the chosen version
ROW=$(mysql_fetch "SELECT ID, file_path
                   FROM $TABLE_NAME
                   WHERE bundle_name='$BUNDLE_NAME'
                     AND version_number=$TARGET_VERSION
                   LIMIT 1;")

if [[ -z "${ROW:-}" ]]; then
  echo "[ERROR] No DB entry found for bundle '$BUNDLE_NAME' version $TARGET_VERSION."
  exit 1
fi

ROLLBACK_ID=$(echo "$ROW" | awk '{print $1}')
FILE_PATH=$(echo "$ROW" | awk '{print $2}')

echo "[INFO] Using DB row ID $ROLLBACK_ID with archive path: $FILE_PATH"

if [[ ! -f "$FILE_PATH" ]]; then
  echo "[ERROR] Archive file not found on Deployment VM: $FILE_PATH"
  exit 1
fi

ARCHIVE_NAME="$(basename "$FILE_PATH")"
REMOTE_ARCHIVE="$QA_REMOTE_BASE/$ARCHIVE_NAME"

# Deploy selected version to all three QA hosts
for HOST in "$QA_FRONTE_

