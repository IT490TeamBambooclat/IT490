#!/usr/bin/env bash
set -e

db_name="deployment"
db_user="admin"
db_passwd="Chrisb200!"
table="bundle_deployments"
dep_user="deployment"

# Production hosts
frontend_prod="100.101.154.73"
backend_prod="100.79.165.125"
dmz_prod="100.100.6.42"

app_path="/var/jobseek"

mysql_fetch() { mysql -N -B -u "$db_user" -p"$db_passwd" "$db_name" -e "$1"; }

rows=$(mysql_fetch "SELECT ID, bundle_name, file_path FROM $table WHERE status='passed';")

if [[ -z "$rows" ]]; then
  echo "[INFO] No passed bundles to deploy to production."
  exit 0
fi

while IFS=$'\t' read -r id bundle path; do
  echo "Deploying bundle ID $id ($bundle) to production"
  echo "Local archive path: $path"

  if [[ ! -f "$path" ]]; then
    echo "[ERROR] Archive file not found: $path"
    echo "[WARN] Skipping bundle ID $id"
    continue
  fi

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
        "frontend/testRabbitMQServer.conf"
      )
      ;;
    backend-rabbit)
      bundle_files=(
        "backend/host.ini"
        "backend/get_host_info.inc"
        "backend/path.inc"
      )
      ;;
    dmz-rabbit)
      bundle_files=(
        "DMZ/host.ini"
        "DMZ/path.inc"
        "DMZ/rabbitMQLib.inc"
        "DMZ/get_host_info.inc"
      )
      ;;
    auth)
      bundle_files=(
	"index.html"
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
      echo "[ERROR] Bad bundle name: $bundle"
      mysql_fetch "UPDATE $table SET status='failed' WHERE ID=$id;"
      continue
      ;;
  esac

  for machine in  "$backend_prod" "$dmz_prod"; do
    echo "Machine: $machine"

    ssh "${dep_user}@${machine}" "mkdir -p '$app_path'"

    echo "Copying to $machine:$rem_archive..."
    scp "$path" "${dep_user}@${machine}:$rem_archive"

    if [[ ${#bundle_files[@]} -gt 0 ]]; then
      ssh "${dep_user}@${machine}" 'bash -s' <<EOF
set -e
app_path="$app_path"
archive="$rem_archive"
temp_dir="\$app_path/.tmp_${bundle}_$id"

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
    echo "Bad file: \$rel"
    exit 1
  fi
done

rm -rf "\$temp_dir"
rm -f "\$archive"
EOF
    else
      ssh "${dep_user}@${machine}" "cd '$app_path' && tar xzf '$rem_archive' && rm -f '$rem_archive'"
    fi

    echo "Bundle $bundle processed on $machine."
  done

  echo "Changing status to prod for bundle ID $id"
  mysql_fetch "UPDATE $table SET status='prod' WHERE ID=$id;"

  echo "Bundle ID $id is deployed to production"
done <<< "$rows"

echo "Finally done"

