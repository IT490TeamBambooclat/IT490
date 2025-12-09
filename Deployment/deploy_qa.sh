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
QA_FRONTEND_HOST="100.70.27.95"   # e.g. 192.168.195.101
QA_BACKEND_HOST="100.90.181.39"   # e.g. 192.168.195.102
QA_DMZ_HOST="100.70.234.127"      # e.g. 192.168.195.103

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

  ########################################
  # Map bundle name -> list of files
  ########################################
  FILES=()
  case "$BUNDLE_NAME" in
    frontend-rabbit)
      FILES=(
        "frontend/get_host_info.inc"
        "frontend/host.ini"
        "frontend/path.inc"
        "frontend/rabbitMQLib.inc"
        "frontend/testRabbitMQ.ini"
        "frontend/testRabbitMQServer.conf"
      )
      ;;
    backend-rabbit)
      FILES=(
        "backend/host.ini"
        "backend/get_host_info.inc"
        "backend/path.inc"
        "backend/testRabbitMQ.ini"
      )
      ;;
    dmz-rabbit)
      FILES=(
        "DMZ/host.ini"
        "DMZ/path.inc"
        "DMZ/rabbitMQLib.inc"
        "DMZ/testRabbitMQ.ini"
        "DMZ/get_host_info.inc"
      )
      ;;
    auth)
      FILES=(
        "frontend/login.php"
        "frontend/logout.php"
        "frontend/register.php"
        "frontend/register.html"
        "frontend/client.php"
        "backend/server490v2.php"
      )
      ;;
    cron)
      FILES=(
        "DMZ/datacollector.php"
        "backend/joblistener.php"
      )
      ;;
    alerts)
      FILES=(
        "frontend/send_email_alerts.php"
        "frontend/save_alert_prefs.php"
        "DMZ/alertsender.php"
        "DMZ/PHPMailer"
        "backend/joblistener.php"
      )
      ;;
    emp_features)
      FILES=(
        "frontend/employer.php"
        "frontend/view_applicants.php"
        "frontend/my_postings.php"
        "frontend/post_job.php"
      )
      ;;
    jseeker_features)
      FILES=(
        "frontend/jobseeker.php"
        "frontend/view_my_jobs.php"
        "frontend/save_job.php"
        "frontend/apply_job.php"
        "frontend/browse_jobs.php"
        "frontend/search_jobs.php"
        "frontend/upload_resume.php"
        "backend/server490v2.php"
      )
      ;;
    roles)
      FILES=(
        "frontend/role_select.php"
        "frontend/set_role.php"
      )
      ;;
    *)
      echo "[WARN] Unknown bundle name '$BUNDLE_NAME'. Will extract full archive into $QA_REMOTE_BASE."
      ;;
  esac

  # Deploy to all three QA VMs
  for HOST in "$QA_FRONTEND_HOST" "$QA_DMZ_HOST"; do
    echo "[INFO] --- QA host: $HOST ---"

    # Ensure remote base directory exists
    ssh "${SSH_USER}@${HOST}" "mkdir -p '$QA_REMOTE_BASE'"

    echo "[INFO] Copying archive to $HOST:$REMOTE_ARCHIVE..."
    scp "$FILE_PATH" "${SSH_USER}@${HOST}:$REMOTE_ARCHIVE"

    # If we have a specific file list for this bundle, use temp dir + targeted moves
    if [[ ${#FILES[@]} -gt 0 ]]; then
      echo "[INFO] Extracting and placing specific files for bundle $BUNDLE_NAME on $HOST..."

      ssh "${SSH_USER}@${HOST}" 'bash -s' <<EOF
set -e
QA_REMOTE_BASE="$QA_REMOTE_BASE"
ARCHIVE="$REMOTE_ARCHIVE"
TMP_DIR="\$QA_REMOTE_BASE/.tmp_${BUNDLE_NAME}_$ID"

echo "[REMOTE] Using temp dir: \$TMP_DIR"
rm -rf "\$TMP_DIR"
mkdir -p "\$TMP_DIR"

# Unpack archive into temp dir
tar xzf "\$ARCHIVE" -C "\$TMP_DIR"

# Files for this bundle:
FILES=(
$(for f in "${FILES[@]}"; do printf '  "%s"\n' "$f"; done)
)

for rel in "\${FILES[@]}"; do
  src="\$TMP_DIR/\$rel"
  dest="\$QA_REMOTE_BASE/\$rel"
  dest_dir=\$(dirname "\$dest")
  mkdir -p "\$dest_dir"

  if [[ -e "\$src" ]]; then
    echo "[REMOTE] Installing \$rel -> \$dest"
    # Move file/dir into place
    rm -rf "\$dest"
    mv "\$src" "\$dest"
  else
    echo "[REMOTE][WARN] File not found in archive: \$rel"
  fi
done

# Clean up temp and (optionally) the archive
rm -rf "\$TMP_DIR"
# rm -f "\$ARCHIVE"
EOF

    else
      # Fallback: old behavior – just extract everything into /var/jobseek
      echo "[INFO] No specific file mapping for bundle '$BUNDLE_NAME', extracting entire archive into $QA_REMOTE_BASE on $HOST..."
      ssh "${SSH_USER}@${HOST}" "cd '$QA_REMOTE_BASE' && tar xzf '$REMOTE_ARCHIVE'"
      # Optional: ssh "${SSH_USER}@${HOST}" "rm -f '$REMOTE_ARCHIVE'"
    fi

    echo "[INFO] Bundle $BUNDLE_NAME processed on $HOST."
  done

  echo "[INFO] Updating DB status -> 'deployed' for bundle ID $ID..."
  mysql_fetch "UPDATE $TABLE_NAME SET status='deployed' WHERE ID=$ID;"

  echo "[INFO] Bundle ID $ID deployment to QA complete."

done <<< "$rows"

echo "=================================================="
echo "[INFO] All pending bundles deployed to QA."

