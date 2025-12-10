#!/usr/bin/env bash
set -euo pipefail

DB_NAME="deployment"
DB_USER="admin"
DB_PASS="Chrisb200!"
TABLE_NAME="bundle_deployments"

SSH_USER="deployment"

<<<<<<< HEAD
=======
# EDIT THESE to REAL IPs <<<
>>>>>>> 0ae0eb0185427edd7bb0009820983be5d0c123cd
QA_FRONTEND_HOST="100.70.27.95"
QA_BACKEND_HOST="100.90.181.39"
QA_DMZ_HOST="100.70.234.127"

QA_REMOTE_BASE="/var/jobseek"

mysql_fetch() {
  mysql -N -B -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1"
}

rows=$(mysql_fetch "SELECT ID, bundle_name, version_number, file_path
                    FROM $TABLE_NAME
                    WHERE status='deployed'
                    ORDER BY bundle_name, version_number;")

while IFS=$'\t' read -r ID BUNDLE_NAME CURRENT_VERSION FILE_PATH; do
  echo "Bundle: $BUNDLE_NAME (version $CURRENT_VERSION)"

  while true; do
<<<<<<< HEAD
    echo -n "[p/f] "
    IFS= read -r ANSWER < /dev/tty

    case "$ANSWER" in
      p|P)
=======
    # *** IMPORTANT PART: force read from /dev/tty ***
    echo -n "Mark this bundle as pass or fail [p/f] "
    if ! IFS= read -r ANSWER < /dev/tty; then
      echo "[ERROR] Could not read from terminal (/dev/tty)."
      exit 1
    fi

    case "$ANSWER" in
      p|P)
        echo "[INFO] Marking bundle ID $ID as 'pass' in DB..."
>>>>>>> 0ae0eb0185427edd7bb0009820983be5d0c123cd
        mysql_fetch "UPDATE $TABLE_NAME SET status='passed' WHERE ID=$ID;"
        break
        ;;
      f|F)
        mysql_fetch "UPDATE $TABLE_NAME SET status='failed' WHERE ID=$ID;"

        TARGET_VERSION=$(mysql_fetch "SELECT MAX(version_number)
                                      FROM $TABLE_NAME
                                      WHERE bundle_name='$BUNDLE_NAME'
                                        AND version_number < $CURRENT_VERSION;")

        if [[ -z "${TARGET_VERSION:-}" ]]; then
          echo "No previous version available.bouy does that suck"
          break
        fi

        ROW=$(mysql_fetch "SELECT ID, file_path
                           FROM $TABLE_NAME
                           WHERE bundle_name='$BUNDLE_NAME'
                             AND version_number=$TARGET_VERSION
                           LIMIT 1;")

        if [[ -z "${ROW:-}" ]]; then
          echo "Rollback version missing from DB silly"
          break
        fi

        ROLLBACK_ID=$(echo "$ROW" | awk '{print $1}')
        ROLLBACK_FILE_PATH=$(echo "$ROW" | awk '{print $2}')

        if [[ ! -f "$ROLLBACK_FILE_PATH" ]]; then
          echo "Rollback archive not found."
          break
        fi

        ARCHIVE_NAME=$(basename "$ROLLBACK_FILE_PATH")
        REMOTE_ARCHIVE="$QA_REMOTE_BASE/$ARCHIVE_NAME"

<<<<<<< HEAD
        for HOST in "$QA_DMZ_HOST"; do
=======
        # Deploy selected rollback version to the QA hosts
        for HOST in "$QA_DMZ_HOST"; do
          echo "[INFO] --- QA host: $HOST ---"

>>>>>>> 0ae0eb0185427edd7bb0009820983be5d0c123cd
          ssh "${SSH_USER}@${HOST}" "mkdir -p '$QA_REMOTE_BASE'"
          scp "$ROLLBACK_FILE_PATH" "${SSH_USER}@${HOST}:$REMOTE_ARCHIVE"
          ssh "${SSH_USER}@${HOST}" "cd '$QA_REMOTE_BASE' && tar xzf '$REMOTE_ARCHIVE'"
<<<<<<< HEAD
=======

          echo "[INFO] Rollback bundle '$BUNDLE_NAME' version $TARGET_VERSION extracted on $HOST."
>>>>>>> 0ae0eb0185427edd7bb0009820983be5d0c123cd
        done

        mysql_fetch "UPDATE $TABLE_NAME SET status='deployed' WHERE ID=$ROLLBACK_ID;"
        break
        ;;
      *)
        echo "Enter p or f."
        ;;
    esac
  done

done <<< "$rows"

