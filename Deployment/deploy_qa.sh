#!/usr/bin/env bash
set -euo pipefail

########################################
# DB settings (from register_bundle.sh)
########################################
DB_NAME="deployment"
DB_USER="admin"
DB_PASS="Chrisb200!"
TABLE_NAME="bundle_deployments"

########################################
# QA cluster settings
########################################
SSH_USER="deployment"          # user on the QA VMs

# >>> EDIT THESE TO YOUR REAL QA HOSTNAMES / IPs <<<
QA_FRONTEND_HOST="qa-frontend"   # e.g. 192.168.195.101
QA_BACKEND_HOST="qa-backend"     # e.g. 192.168.195.102
QA_DMZ_HOST="qa-dmz"             # e.g. 192.168.195.103

QA_REMOTE_BASE="/var/jobseek"    # where the app lives on each QA VM

########################################

mysql_fetch() {
  mysql -N -B -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1"
}

echo "[INFO] Looking for bundles with status='pending'..."
rows=$(mysql_fetch "SELECT ID, bundle_name, file_path FROM $TABLE_NAME WHERE status='pending';")

if [[ -z "$rows" ]]; then
  echo "[INFO] No pending bundles to deploy to QA."
  exit 0
fi

# Loop over each pending bundle
while IFS=$'\t' read -r ID BUNDLE_NAME FILE_PATH; do
  echo "=================================================="
  echo "[INFO] Deploying bundle ID $ID ($BUNDLE_NAME)"
  echo "[INFO] Local archive path: $FILE_PATH"

  if [[ ! -f "$FILE_PATH" ]]; then
    echo "[ERROR] Archive file not found: $FILE_PATH"
    echo "[WARN] Skipping bundle ID $ID"
    continue
  fi

  ARCHIVE_NAME="$(basename "$FILE_PATH")"
  REMOTE_ARCHIVE="$QA_REMOTE_BASE/$ARCHIVE_NAME"

  # Deploy to all three QA VMs
  for HOST in "$QA_FRONTEND_HOST" "$QA_BACKEND_HOST" "$QA_DMZ_HOST"; do
    echo "[INFO] --- QA host: $HOST ---"

    # Ensure remote base directory exists
    ssh "${SSH_USER}@${HOST}" "mkdir -p '$QA_REMOTE_BASE'"

    echo "[INFO] Copying archive to $HOST:$REMOTE_ARCHIVE..."
    scp "$FILE_PATH" "${SSH_USER}@${HOST}:$REMOTE_ARCHIVE"

    echo "[INFO] Extracting archive on $HOST into $QA_REMOTE_BASE..."
    ssh "${SSH_USER}@${HOST}" "cd '$QA_REMOTE_BASE' && tar xzf '$REMOTE_ARCHIVE'"

    # Optional: keep the archive or remove it after extraction
    # ssh "${SSH_USER}@${HOST}" "rm -f '$REMOTE_ARCHIVE'"

    echo "[INFO] Bundle $BUNDLE_NAME extracted on $HOST."
  done

  echo "[INFO] Updating DB status -> 'deployed' for bundle ID $ID..."
  mysql_fetch "UPDATE $TABLE_NAME SET status='deployed' WHERE ID=$ID;"

  echo "[INFO] Bundle ID $ID deployment to QA complete."

done <<< "$rows"

echo "=================================================="
echo "[INFO] All pending bundles deployed to QA."

