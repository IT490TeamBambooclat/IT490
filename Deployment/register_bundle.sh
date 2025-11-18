#!/usr/bin/env bash
set -e

# ==== DB SETTINGS (EDIT THESE) ====
DB_NAME="deployment"                 # your database name
DB_USER="admin"            # db user
DB_PASS="Chrisb200!"         # db password
TABLE_NAME="bundle_deployments"
# ==================================

# Where bundles live on the Deployment VM
DEPLOY_BASE_DIR="/home/cab7/Bundles"

# Usage: register_bundle.sh <bundle-name>
if [ $# -ne 1 ]; then
  echo "Usage: $0 <bundle-name>"
  echo "Example: $0 auth"
  exit 1
fi

BUNDLE_NAME="$1"   # e.g. auth, frontend_rabbit, alerts, etc.

BUNDLE_DIR="${DEPLOY_BASE_DIR}/${BUNDLE_NAME}"
INCOMING_ARCHIVE="${BUNDLE_DIR}/${BUNDLE_NAME}.tar.gz"

# 1) Make sure the incoming file exists
if [ ! -f "$INCOMING_ARCHIVE" ]; then
  echo "ERROR: Incoming archive not found: $INCOMING_ARCHIVE"
  exit 1
fi

# 2) Insert a row so MySQL assigns version_number (AUTO_INCREMENT)
#    We temporarily store the current file path; we'll update it after rename.
VERSION_NUMBER=$(mysql -N -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
  INSERT INTO ${TABLE_NAME} (bundle_name, file_path, status)
  VALUES ('${BUNDLE_NAME}', '${INCOMING_ARCHIVE}', 'pending');
  SELECT LAST_INSERT_ID();
")

# 3) Rename: bundle.tar.gz -> bundle_v<version_number>.tar.gz
FINAL_ARCHIVE="${BUNDLE_DIR}/${BUNDLE_NAME}_v${VERSION_NUMBER}.tar.gz"
mv "$INCOMING_ARCHIVE" "$FINAL_ARCHIVE"

echo "Registered bundle '${BUNDLE_NAME}' as version ${VERSION_NUMBER}"
echo "  File path: ${FINAL_ARCHIVE}"

# 4) Update the file_path in the row we just inserted
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
  UPDATE ${TABLE_NAME}
  SET file_path='${FINAL_ARCHIVE}'
  WHERE version_number=${VERSION_NUMBER};
"

echo "DB updated (status = 'pending')."

