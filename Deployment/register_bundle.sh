#!/usr/bin/env bash
set -e

db_name="deployment"              
db_user="admin"            
db_pass="Chrisb200!"        
table="bundle_deployments"

bundles_dir="/home/cab7/Bundles"

if [ $# -ne 1 ]; then
  echo "Usage: $0 <bundle-name>"
  echo "Example: $0 auth"
  exit 1
fi

bundle="$1"   

archive="${bundles_dir}/${bundle}/${bundle}.tar.gz"


if [ ! -f "$archive" ]; then
  echo "Bad archive: $archive"
  exit 1
fi

version_num=$(mysql -N -u "$db_user" -p"$db_pass" "$db_name" -e "
  Select Coalesce(Max(version_number) + 1, 1)
  From ${table}
  Where bundle_name = '${bundle}';
")


result_archive="${bundles_dir}/${bundle}/${bundle}_v${version_num}.tar.gz"
mv "$archive" "$result_archive"

echo "Here"

mysql -u "$db_user" -p"$db_pass" "$db_name" <<EOF
Insert Into ${table} 
(bundle_name, version_number, file_path, status)
Values
('${bundle}', ${version_num}, '${result_archive}', 'pending');
EOF

echo "Done"

