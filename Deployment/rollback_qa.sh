#!/usr/bin/env bash
set -euo pipefail

# DB settings
DB_NAME="deployment"
DB_USER="admin"
DB_PASS="Chrisb200!"
TABLE_NAME="bundle_deployments"

SSH_USER="deployment"

# EDIT THESE to REAL IPs <<<
QA_FRONTEND_HOST="100.70.27.95"
QA_BACKEND_HOST="100.90.181.39"
QA_DMZ_HOST="100.70.234.127"

QA_REMOTE_BASE="/var/jobseek"

mysql_fetch() {
  mysql -N -B -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1"
}

echo "[INFO] Looking for bundles with status='deployed'..."
rows=$(mysql_fetch "SELECT ID, bundle_name, version_number, file_path
                    FROM $TABLE_NAME
                    WHERE status='deployed'
                    ORDER BY bundle_name, version_number;")

if [[ -z "${rows:-}" ]]; then
  echo "[INFO] No deployed bundles found to evaluate."
  exit 0
fi

# Loop over each deployed bundle and ask pass/fail
while IFS=$'\t' read -r ID BUNDLE_NAME CURRENT_VERSION FILE_PATH; do
  echo "=================================================="
  echo "[INFO] Deployed bundle:"
  echo "  ID:            $ID"
  echo "  Bundle name:   $BUNDLE_NAME"
  echo "  Version:       $CURRENT_VERSION"
  echo "  Archive path:  $FILE_PATH"

  while true; do
    # *** IMPORTANT PART: force read from /dev/tty ***
    echo -n "Mark this bundle as pass or fail [p/f] "
    if ! IFS= read -r ANSWER < /dev/tty; then
      echo "[ERROR] Could not read from terminal (/dev/tty)."
      exit 1
    fi

    case "$ANSWER" in
      p|P)
        echo "[INFO] Marking bundle ID $ID as 'pass' in DB..."
        mysql_fetch "UPDATE $TABLE_NAME SET status='passed' WHERE ID=$ID;"
        break
        ;;
      f|F)
        echo "[INFO] Marking bundle ID $ID as 'failed' in DB..."
        mysql_fetch "UPDATE $TABLE_NAME SET status='failed' WHERE ID=$ID;"

        echo "[INFO] Determining previous version for bundle '$BUNDLE_NAME'..."

        TARGET_VERSION=$(mysql_fetch "SELECT MAX(version_number)
                                      FROM $TABLE_NAME
                                      WHERE bundle_name='$BUNDLE_NAME'
                                        AND version_number < $CURRENT_VERSION;")

        if [[ -z "${TARGET_VERSION:-}" ]]; then
          echo "[ERROR] No previous version found for bundle '$BUNDLE_NAME' (current is $CURRENT_VERSION)."
          echo "[WARN] Cannot rollback QA for this failure."
          break
        fi

        echo "[INFO] Rolling back QA bundle '$BUNDLE_NAME' to version $TARGET_VERSION..."

        # Get the DB row for the chosen rollback version
        ROW=$(mysql_fetch "SELECT ID, file_path
                           FROM $TABLE_NAME
                           WHERE bundle_name='$BUNDLE_NAME'
                             AND version_number=$TARGET_VERSION
                           LIMIT 1;")

        if [[ -z "${ROW:-}" ]]; then
          echo "[ERROR] No DB entry found for bundle '$BUNDLE_NAME' version $TARGET_VERSION."
          echo "[WARN] Cannot rollback QA for this failure."
          break
        fi

        ROLLBACK_ID=$(echo "$ROW" | awk '{print $1}')
        ROLLBACK_FILE_PATH=$(echo "$ROW" | awk '{print $2}')

        echo "[INFO] Using rollback DB row ID $ROLLBACK_ID with archive path: $ROLLBACK_FILE_PATH"

        if [[ ! -f "$ROLLBACK_FILE_PATH" ]]; then
          echo "[ERROR] Archive file not found on Deployment VM: $ROLLBACK_FILE_PATH"
          echo "[WARN] Cannot rollback QA for this failure."
          break
        fi

        ARCHIVE_NAME="$(basename "$ROLLBACK_FILE_PATH")"
        REMOTE_ARCHIVE="$QA_REMOTE_BASE/$ARCHIVE_NAME"

        # Deploy selected rollback version to the QA hosts
        for HOST in "$QA_DMZ_HOST"; do
          echo "[INFO] --- QA host: $HOST ---"

          ssh "${SSH_USER}@${HOST}" "mkdir -p '$QA_REMOTE_BASE'"

          echo "[INFO] Copying rollback archive to $HOST:$REMOTE_ARCHIVE..."
          scp "$ROLLBACK_FILE_PATH" "${SSH_USER}@${HOST}:$REMOTE_ARCHIVE"

          echo "[INFO] Extracting rollback archive on $HOST into $QA_REMOTE_BASE..."
          ssh "${SSH_USER}@${HOST}" "cd '$QA_REMOTE_BASE' && tar xzf '$REMOTE_ARCHIVE'"

          echo "[INFO] Rollback bundle '$BUNDLE_NAME' version $TARGET_VERSION extracted on $HOST."
        done

        echo "[INFO] Marking rollback bundle ID $ROLLBACK_ID (version $TARGET_VERSION) as 'deployed' in DB..."
        mysql_fetch "UPDATE $TABLE_NAME SET status='deployed' WHERE ID=$ROLLBACK_ID;"

        echo "[INFO] QA rollback of bundle '$BUNDLE_NAME' to version $TARGET_VERSION complete."
        break
        ;;
      *)
        echo "[WARN] Please answer 'p' for pass or 'f' for fail."
        ;;
    esac
  done

done <<< "$rows"

echo "=================================================="
echo "[INFO] Pass/fail evaluation complete for all deployed bundles."

