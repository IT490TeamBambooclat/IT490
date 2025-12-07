#!/usr/bin/env bash
set -euo pipefail

########################################
# DB settings
########################################
DB_NAME="deployment"
DB_USER="admin"
DB_PASS="Chrisb200!"
TABLE_NAME="bundle_deployments"

########################################
# Prod cluster settings
########################################
SSH_USER="deployment"          # SSH user on PROD VMs

# >>> EDIT THESE TO YOUR REAL PROD HOSTNAMES / IPs <<<
PROD_FRONTEND_HOST="prod-frontend"   # e.g. 192.168.196.101
PROD_BACKEND_HOST="prod-backend"     # e.g. 192.168.196.102
PROD_DMZ_HOST="prod-dmz"             # e.g. 192.168.196.103

PROD_REMOTE_BASE="/var/jobseek"      # app root on PROD

########################################

mysql_fetch() {
  mysql -N -B -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1"
}

if [[ $# -gt 1 ]]; then
  echo "Usage: $0 [bundle_name]"
  echo "  No args      -> deploy all bundles with status='passed'"
  echo "  bundle_name  -> deploy only that bundle (with status='passed')"
  exit 1
fi

if [[ $# -eq 1 ]]; then
  BUNDLE_FILTER="$1"
  echo "[INFO] Deploying PASSED bundles with bundle_name='$BUNDLE_FILTER' to PROD..."
  rows=$(mysql_fetch "SELECT ID, bundle_name, file_path 
                      FROM $TABLE_NAME 
                      WHERE status='passed' AND bundle_name='$BUNDLE_FILTER';")
else
  echo "[INFO] Deploying ALL bundles with status='passed' to PROD..."
  rows=$(mysql_fetch "SELECT ID, bundle_name, file_path 
                      FROM $TABLE_NAME 
                      WHERE status='passed';")
fi

if [[ -z "${rows:-}" ]]; then
  echo "[INFO] No bundles found with status='passed' (and matching any filter)."
  exit 0
fi

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
  REMOTE_ARCHIVE="$PROD_REMOTE_BASE/$ARCHIVE_NAME"

  # Deploy to all three PROD VMs
  for HOST in "$PROD_FRONTEND_HOST" "$PROD_BACKEND_HOST" "$PROD_DMZ_HOST"; do
    echo "[INFO] --- PROD host: $HOST ---"

    ssh "${SSH_USER}@${HOST}" "mkdir -p '$PROD_REMOTE_BASE'"

    echo "[INFO] Copying archive to $HOST:$REMOTE_ARCHIVE..."
    scp "$FILE_PATH" "${SSH_USER}@${HOST}:$REMOTE_ARCHIVE"

    echo "[INFO] Extracting archive on $HOST into $PROD_REMOTE_BASE..."
    ssh "${SSH_USER}@${HOST}" "cd '$PROD_REMOTE_BASE' && tar xzf '$REMOTE_ARCHIVE'"

    # Optional: remove the archive after extraction
    # ssh "${SSH_USER}@${HOST}" "rm -f '$REMOTE_ARCHIVE'"

    echo "[INFO] Bundle $BUNDLE_NAME extracted on $HOST."
  done

  # NOTE: we *keep* status='passed' to mean "this is the version that passed QA".
  # If you later add a 'prod' status value to the ENUM, you can uncomment:
  # mysql_fetch "UPDATE $TABLE_NAME SET status='prod' WHERE ID=$ID;"

  echo "[INFO] Bundle ID $ID deployment to PROD complete."

done <<< "$rows"

echo "=================================================="
echo "[INFO] Production deployment complete."

