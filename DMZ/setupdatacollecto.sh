#!/bin/bash
PHP_SCRIPT="datacollector.php"
PHP_PATH=$(which php)
if [ ! -f "$PHP_SCRIPT" ]; then
    echo "❌ ERROR: datacollector.php not found at $PHP_SCRIPT"
    exit 1
fi
LOG_FILE="/var/log/datacollector.log"
sudo touch $LOG_FILE
sudo chmod 666 $LOG_FILE
echo "📦 Backing up current crontab (if any)..."
crontab -l > ~/crontab_backup_$(date +%F_%H-%M-%S).bak 2>/dev/null
CRON_JOB="0 */5 * * * $PHP_PATH $PHP_SCRIPT >> $LOG_FILE 2>&1"
( crontab -l 2>/dev/null | grep -v "$PHP_SCRIPT" ; echo "$CRON_JOB" ) | crontab -
echo "✅ datacollector.php has been scheduled to run every 5 hours."
echo "🕓 Cron entry added:"
echo "   $CRON_JOB"
echo "🪵 Logs will be saved at: $LOG_FILE"
echo "🚀 Running datacollector.php now for the first time..."
$PHP_PATH $PHP_SCRIPT >> $LOG_FILE 2>&1
echo "✅ Initial execution complete. Check log at $LOG_FILE"

QUICK_SEARCH_PHP="./quick_search.php"

if [ "$1" = "--interactive" ]; then
  if [ ! -f "$QUICK_SEARCH_PHP" ]; then
    echo "$QUICK_SEARCH_PHP not found."
    exit 0
  fi
  echo
  echo "Press a letter (a–z) to start a search. Press 'q' to quit."
  stty -echo -icanon time 0 min 1 2>/dev/null
  while true; do
    key=$(dd bs=1 count=1 2>/dev/null)
    case "$key" in
      [a-zA-Z] )
        echo
        echo "➡️  Searching for jobs with keyword: '$key'"
        $PHP_PATH "$QUICK_SEARCH_PHP" "$key"
        echo
        echo "Press another letter, or 'q' to quit."
        ;;
      q )
        echo
        echo "👋 Exiting interactive mode."
        break
        ;;
      * )
        ;;
    esac
  done
  stty sane 2>/dev/null
fi

