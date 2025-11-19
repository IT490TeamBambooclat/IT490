#!/usr/bin/env bash
set -e

DB_NAME="deployment"              
DB_USER="admin"            
DB_PASS="Chrisb200!"         
TABLE_NAME="bundle_deployments"

DEPLOY_BASE_DIR="/home/cab7/Bundles"

if [ $# -ne 1 ]; then
  echo "Usage: $0 <bundle-name>"
  echo "Example: $0 auth"
  exit 1
fi

BUNDLE_NAME="$1"   

BUNDLE_DIR="${DEPLOY_BASE_DIR}/${BUNDLE_NAME}"
INCOMING_ARCHIVE="${BUNDLE_DIR}/${BUNDLE_NAME}.tar.gz"


if [ ! -f "$INCOMING_ARCHIVE" ]; then
  echo "ERROR: Incoming archive not found: $INCOMING_ARCHIVE"
  exit 1
fi

VERSION_NUMBER=$(mysql -N -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
  Select Coalesce(Max(version_number) + 1, 1)
  From ${TABLE_NAME}
  Where bundle_name = '${BUNDLE_NAME}';
")


FINAL_ARCHIVE="${BUNDLE_DIR}/${BUNDLE_NAME}_v${VERSION_NUMBER}.tar.gz"
mv "$INCOMING_ARCHIVE" "$FINAL_ARCHIVE"

echo "Registered bundle '${BUNDLE_NAME}' as version ${VERSION_NUMBER}"
echo "  File path: ${FINAL_ARCHIVE}"

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
Insert Into ${TABLE_NAME} 
(bundle_name, version_number, file_path, status)
Values
('${BUNDLE_NAME}', ${VERSION_NUMBER}, '${FINAL_ARCHIVE}', 'pending');
EOF

echo "DB updated (status = 'pending')."

