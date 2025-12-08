#!/usr/bin/env bash
# Stop on any error
set -e

# deployment settings 
DEPLOY_USER="cab7"                 # deployment VM user
DEPLOY_HOST="100.107.92.80"        # deployment VM IP
DEPLOY_BASE_DIR="/home/cab7/Bundles"  # base directory;

# Get folder where this script is 
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Give only 1 bundle name
if [ $# -ne 1 ]; then
    echo "Usage: $0 <bundle-name>"
    echo "Bundles: frontend_rabbit backend-rabbit dmz-rabbit auth cron alerts emp_features jseeker_features roles"
    exit 1
fi

BUNDLE="$1"

# Pick files for each bundle and map to remote subfolder name
case "$BUNDLE" in
    frontend_rabbit)
        REMOTE_SUBDIR="frontend-rabbit"
        FILES="frontend/get_host_info.inc
frontend/host.ini
frontend/path.inc
frontend/rabbitMQLib.inc
frontend/testRabbitMQ.ini
frontend/testRabbitMQServer.conf"
        ;;
    backend-rabbit)
        REMOTE_SUBDIR="backend-rabbit"
        FILES="backend/host.ini
backend/get_host_info.inc
backend/path.inc
backend/testRabbitMQ.ini"
        ;;
    dmz-rabbit)
        REMOTE_SUBDIR="dmz-rabbit"
        FILES="DMZ/host.ini
DMZ/path.inc
DMZ/rabbitMQLib.inc
DMZ/testRabbitMQ.ini
DMZ/get_host_info.inc"
        ;;
    auth)
        REMOTE_SUBDIR="auth"
        FILES="frontend/login.php
frontend/logout.php
frontend/register.php
frontend/register.html
frontend/client.php
backend/server490v2.php"
        ;;
    cron)
        REMOTE_SUBDIR="cron"
        FILES="DMZ/datacollector.php
backend/joblistener.php"
        ;;
    alerts)
        REMOTE_SUBDIR="alerts"
        FILES="frontend/send_email_alerts.php
frontend/save_alerts_prefs.php
DMZ/alertsender.php
DMZ/PHPMailer
backend/joblistener.php"
        ;;
    emp_features)
        REMOTE_SUBDIR="emp_features"
        FILES="frontend/employer.php
frontend/view_applicants.php
frontend/my_postings
frontend/post_job.php"
        ;;
    jseeker_features)
        REMOTE_SUBDIR="jseeker_features"
        FILES="frontend/jobseeker.php
frontend/view_my_jobs.php
frontend/save_job.php
frontend/apply_job.php
frontend/browse_jobs.php
frontend/search_jobs.php
frontend/upload_resume.php
backend/server490v2.php"
        ;;
    roles)
        REMOTE_SUBDIR="roles"
        FILES="frontend/role_select.php
frontend/set_role.php"
        ;;
    *)
        echo "Unknown bundle: $BUNDLE"
        exit 1
        ;;
esac

# Build archive name
cd "$PROJECT_ROOT"
ARCHIVE="${BUNDLE}.tar.gz"

echo "Creating $ARCHIVE..."
tar czf "$ARCHIVE" $FILES   # bundle and compress files

# Remote directory for this bundle: /home/cab7/Bundles/<bundle-name>
REMOTE_DIR="${DEPLOY_BASE_DIR}/${REMOTE_SUBDIR}"

echo "Copying to ${DEPLOY_USER}@${DEPLOY_HOST}:${REMOTE_DIR}..."
scp "$ARCHIVE" "${DEPLOY_USER}@${DEPLOY_HOST}:${REMOTE_DIR}/"

echo "Calling register script on deployment VM..."
ssh "${DEPLOY_USER}@${DEPLOY_HOST}" "/home/cab7/Git/IT490/Deployment/register_bundle.sh '${BUNDLE}'"

echo "Bundle '${BUNDLE}'  has been registered into the DB"

