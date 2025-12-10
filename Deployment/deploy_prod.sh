#!/usr/bin/env bash

# DB settings

DB_NAME="deployment"
DB_USER="admin"
DB_PASS="Chrisb200!"
TABLE_NAME="bundle_deployments"


# Prod cluster settings
#
SSH_USER="deployment"

PROD_FRONTEND_HOST="prod-frontend"
PROD_BACKEND_HOST="prod-backend"
PROD_DMZ_HOST="prod-dmz"

PROD_REMOTE_BASE="/var/jobseek"



mysql_fetch() {
  mysql -N -B -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1"
}

if [[ $# -eq 1 ]]; then
  BUNDLE_FILTER="$1"
  echo "Deploying passed '$BUNDLE_FILTER' to production..."
  rows=$(mysql_fetch "SELECT ID, bundle_name, file_path 
                      FROM $TABLE_NAME 
                      WHERE status='passed' AND bundle_name='$BUNDLE_FILTER';")
else
  echo "Deploying all bundles that have 'passed' to production..."
  rows=$(mysql_fetch "SELECT ID, bundle_name, file_path 
                      FROM $TABLE_NAME 
                      WHERE status='passed';")
fi

while IFS=$'\t' read -r ID BUNDLE_NAME FILE_PATH; do
  echo "Deploying bundle ID $ID ($BUNDLE_NAME)"
  echo "Local archive path: $FILE_PATH"

  ARCHIVE_NAME="$(basename "$FILE_PATH")"
  REMOTE_ARCHIVE="$PROD_REMOTE_BASE/$ARCHIVE_NAME"

  for HOST in "$PROD_FRONTEND_HOST" "$PROD_BACKEND_HOST" "$PROD_DMZ_HOST"; do
    echo "Production host: $HOST"

    ssh "${SSH_USER}@${HOST}" "mkdir -p '$PROD_REMOTE_BASE'"

    echo "Copying archive to $HOST:$REMOTE_ARCHIVE..."
    scp "$FILE_PATH" "${SSH_USER}@${HOST}:$REMOTE_ARCHIVE"

    echo "Extracting archive on $HOST..."
    ssh "${SSH_USER}@${HOST}" "cd '$PROD_REMOTE_BASE' && tar xzf '$REMOTE_ARCHIVE'"

    echo "Extracted on $HOST."
  done

  echo "Bundle ID $ID deployment complete."
done <<< "$rows"

echo "Production deployment complete."

