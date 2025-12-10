#!/usr/bin/env bash
set -e

db_name="deployment"
db_user="admin"
db_passwd="Chrisb200!"
table="bundle_deployments"
dep_user="deployment"

frontend_qa="100.70.27.95"   
backend_qa="100.90.181.39"  
dmz_qa="100.70.234.127"      

app_path="/var/jobseek"

mysql_fetch() { mysql -N -B -u "$db_user" -p"$db_passwd" "$db_name" -e "$1"}

echo "here"

rows=$(mysql_fetch "SELECT ID, bundle_name, file_path FROM $table WHERE status='pending';")

if [[ -z "$rows" ]]; then
  echo "[INFO] No pending bundles to deploy to QA."
  exit 0
fi

while IFS=$'\t' read -r id bundle path; do
  echo "Deploying bundle ID $id ($bundle)"
  echo "Local archive path: $path"

  if [[ ! -f "$path" ]]; then
    echo "[ERROR] Archive file not found: $path"
    echo "[WARN] Skipping bundle ID $id"
    continue
  fi
  
  echo "You are here"
  archive="$(basename "$path")"
  rem_archive="$app_path/$archive"

  bundle_files=()
  case "$bundle" in
    frontend-rabbit)
      bundle_files=(
        "frontend/get_host_info.inc"
        "frontend/host.ini"
        "frontend/path.inc"
        "frontend/rabbitMQLib.inc"
        "frontend/testRabbitMQ.ini"
        "frontend/testRabbitMQServer.conf"
      )
      ;;
    backend-rabbit)
      bundle_files=(
        "backend/host.ini"
        "backend/get_host_info.inc"
        "backend/path.inc"
        "backend/testRabbitMQ.ini"
      )
      ;;
    dmz-rabbit)
      bundle_files=(
        "DMZ/host.ini"
        "DMZ/path.inc"
        "DMZ/rabbitMQLib.inc"
        "DMZ/testRabbitMQ.ini"
        "DMZ/get_host_info.inc"
      )
      ;;
    auth)
      bundle_files=(
        "frontend/login.php"
        "frontend/logout.php"
        "frontend/register.php"
        "frontend/register.html"
        "frontend/client.php"
        "backend/server490v2.php"
      )
      ;;
    cron)
      bundle_files=(
        "DMZ/datacollector.php"
        "backend/joblistener.php"
      )
      ;;
    alerts)
      bundle_files=(
        "frontend/send_email_alerts.php"
        "frontend/save_alert_prefs.php"
        "DMZ/alertsender.php"
        "DMZ/PHPMailer"
        "backend/joblistener.php"
      )
      ;;
    emp_features)
      bundle_files=(
        "frontend/employer.php"
        "frontend/view_applicants.php"
        "frontend/my_postings.php"
        "frontend/post_job.php"
      )
      ;;
    jseeker_features)
      bundle_files=(
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
      bundle_files=(
        "frontend/role_select.php"
        "frontend/set_role.php"
      )
      ;;
    *)
      echo " Bad bundle name'"
      ;;
  esac

  for machine in "$frontend_qa" "$backend_qa" "$dmz_qa"; do
    echo "Machine: $machine "

    ssh "${dep_user}@${machine}" "mkdir -p '$app_path'"
    
    echo "You are here"
    echo "Copying to $machine:$rem_archive..."
    scp "$path" "${dep_user}@${machine}:$rem_archive"

    if [[ ${#bundle_files[@]} -gt 0 ]]; then
      echo "Extracting and placing specific files for bundle $bundle on $machine..."

      ssh "${dep_user}@${machine}" 'bash -s' <<EOF
set -e
app_path="$app_path"
archive="$rem_archive"
temp_dir="\$app_path/.tmp_${bundle}_$id"

echo " Using temp dir: \$temp_dir"
rm -rf "\$temp_dir"
mkdir -p "\$temp_dir"

tar xzf "\$archive" -C "\$temp_dir"

bundle_files=(
$(for f in "${bundle_files[@]}"; do printf '  "%s"\n' "$f"; done)
)

for rel in "\${bundle_files[@]}"; do
  src="\$temp_dir/\$rel"
  dest="$app_path/\$rel"
  dest_dir=\$(dirname "\$dest")
  mkdir -p "\$dest_dir"

  if [[ -e "\$src" ]]; then
    rm -rf "\$dest"
    mv "\$src" "\$dest"
  else
    echo "Bad file"
  fi
done


rm -rf "\$temp_dir"
rm -f "\$archive"
EOF

    else
      echo " No destination, just gonna put it there and you'll see it"
      ssh "${dep_user}@${machine}" "cd '$app_path' && tar xzf '$rem_archive'"
    fi

    echo " Bundle $bundle processed on $machine."
  done

  echo " Changing status to deployed for bundle ID $id"
  mysql_fetch "UPDATE $table SET status='deployed' WHERE ID=$id;"

  echo " Bundle ID $id is deployed"

done <<< "$rows"

echo " FInally done"

