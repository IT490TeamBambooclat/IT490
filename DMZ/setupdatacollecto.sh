#!/bin/bash
# ==============================================================
# Script Name: setup_datacollector_cron.sh
# Description: Automatically schedule datacollector.php to run
#              every 5 hours and execute it once immediately.
# ==============================================================

# === 1. Configuration ===
# 👉 Replace this path with the FULL absolute path to your datacollector.php
PHP_SCRIPT="datacollector.php"

# === 2. Find PHP binary path ===
PHP_PATH=$(which php)

# === 3. Check if the PHP script exists ===
if [ ! -f "$PHP_SCRIPT" ]; then
    echo "❌ ERROR: datacollector.php not found at $PHP_SCRIPT"
    echo "Please update the script path above and re-run."
    exit 1
fi

# === 4. Ensure log file directory exists ===
LOG_FILE="/var/log/datacollector.log"
sudo touch $LOG_FILE
sudo chmod 666 $LOG_FILE

# === 5. Backup current crontab ===
echo "📦 Backing up current crontab (if any)..."
crontab -l > ~/crontab_backup_$(date +%F_%H-%M-%S).bak 2>/dev/null

# === 6. Define cron job ===
CRON_JOB="0 */5 * * * $PHP_PATH $PHP_SCRIPT >> $LOG_FILE 2>&1"

# === 7. Install new cron job ===
# Remove old versions of this job to avoid duplicates
( crontab -l 2>/dev/null | grep -v "$PHP_SCRIPT" ; echo "$CRON_JOB" ) | crontab -

# === 8. Confirmation ===
echo "✅ datacollector.php has been scheduled to run every 5 hours."
echo "🕓 Cron entry added:"
echo "   $CRON_JOB"
echo "🪵 Logs will be saved at: $LOG_FILE"

# === 9. Run once immediately ===
echo "🚀 Running datacollector.php now for the first time..."
$PHP_PATH $PHP_SCRIPT >> $LOG_FILE 2>&1

echo "✅ Initial execution complete. Check log at $LOG_FILE"

