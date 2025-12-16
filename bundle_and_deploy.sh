#!/usr/bin/env bash
set -e
 
dep_user="cab7"
dep_ip="100.107.92.80"        
bundles_dir="/home/cab7/Bundles"  

 
project_location="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

bundle="$1"

case "$bundle" in
    frontend-rabbit)
        subdir="frontend-rabbit"
        bundle_files="frontend/get_host_info.inc
frontend/host.ini
frontend/path.inc
frontend/rabbitMQLib.inc
frontend/testRabbitMQ.ini
frontend/testRabbitMQServer.conf"
        ;;
    backend-rabbit)
        subdir="backend-rabbit"
        bundle_files="backend/host.ini
backend/get_host_info.inc
backend/path.inc
backend/testRabbitMQ.ini"
        ;;
    dmz-rabbit)
        subdir="dmz-rabbit"
        bundle_files="DMZ/host.ini
DMZ/path.inc
DMZ/rabbitMQLib.inc
DMZ/testRabbitMQ.ini
DMZ/get_host_info.inc"
        ;;
    auth)
        subdir="auth"
        bundle_files="frontend/login.php
frontend/login.php
frontend/logout.php
frontend/register.php
frontend/register.html
frontend/client.php
backend/server490v2.php"
        ;;
    cron)
        subdir="cron"
        bundle_files="DMZ/datacollector.php
backend/joblistener.php"
        ;;
    alerts)
        subdir="alerts"
        bundle_files="frontend/send_email_alerts.php
frontend/save_alert_prefs.php
DMZ/alertsender.php
DMZ/PHPMailer
backend/joblistener.php"
        ;;
    emp_features)
        subdir="emp_features"
        bundle_files="frontend/employer.php
frontend/view_applicants.php
frontend/my_postings.php
frontend/post_job.php"
        ;;
    jseeker_features)
        subdir="jseeker_features"
        bundle_files="frontend/jobseeker.php
frontend/view_my_jobs.php
frontend/save_job.php
frontend/apply_job.php
frontend/browse_jobs.php
frontend/search_jobs.php
frontend/upload_resume.php
backend/server490v2.php"
        ;;
    roles)
        subdir="roles"
        bundle_files="frontend/role_select.php
frontend/set_role.php"
        ;;
    *)
        echo "Unknown bundle: $bundle"
        exit 1
        ;;
esac

cd "$project_location"
archive="${bundle}.tar.gz"
tar czf "$archive" $bundle_files
remote_dir="${bundles_dir}/${subdir}"

echo "Here"
scp "$archive" "${dep_user}@${dep_ip}:${remote_dir}/"
ssh "${dep_user}@${dep_ip}" "/home/cab7/Git/IT490/Deployment/register_bundle.sh '${bundle}'"

echo "Bundle '${bundle} is done'"

