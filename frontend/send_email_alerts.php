<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$SMTP_HOST       = 'smtp.gmail.com';      // e.g. smtp.sendgrid.net, smtp.gmail.com
$SMTP_PORT       = 587;                   // 587 (TLS) or 465 (SSL)
$SMTP_SECURE     = PHPMailer::ENCRYPTION_STARTTLS; // or PHPMailer::ENCRYPTION_SMTPS for 465
$SMTP_USERNAME   = 'your_smtp_username';  // e.g. your@gmail.com or apikey (SendGrid)
$SMTP_PASSWORD   = 'your_smtp_password_or_app_password';
$SMTP_FROM       = 'no-reply@yourdomain.com';
$SMTP_FROM_NAME  = 'Job Alerts';

$API_HOST   = "https://data.usajobs.gov/api/Search";
$API_KEY    = "JARdgfQahwqDDdgixRjy/i7LyfIoEhmnJhwt9duouWM=";
$USER_AGENT = "teambamboclaat@gmail.com";

$prefsFile = __DIR__ . '/data/alert_prefs.json';
if (!file_exists($prefsFile)) exit;
$prefs = json_decode(file_get_contents($prefsFile), true);
if (!is_array($prefs) || !$prefs) exit;

$sinceFile = __DIR__ . '/data/last_email_alerts.json';
$lastMap   = file_exists($sinceFile) ? (json_decode(file_get_contents($sinceFile), true) ?: []) : [];

function fetch_jobs_since_all($sinceIso) {
    global $API_HOST, $API_KEY, $USER_AGENT;
    $results = [];
    $page = 1;
    $per = 500;
    while (true) {
        $url = $API_HOST . "?ResultsPerPage=$per&Page=$page";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: data.usajobs.gov",
            "User-Agent: $USER_AGENT",
            "Authorization-Key: $API_KEY"
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$resp) break;

        $data  = json_decode($resp, true);
        $items = $data['SearchResult']['SearchResultItems'] ?? [];
        if (!$items) break;

        foreach ($items as $it) {
            $d = $it['MatchedObjectDescriptor'] ?? [];
            $posted = $d['PublicationStartDate'] ?? null;
            if (!$posted) continue;
            if ($sinceIso && strtotime($posted) <= strtotime($sinceIso)) continue;

            $results[] = [
                'title' => $d['PositionTitle'] ?? 'N/A',
                'org'   => $d['OrganizationName'] ?? 'N/A',
                'loc'   => implode(', ', array_column($d['PositionLocations'] ?? [], 'LocationName')) ?: 'N/A',
                'date'  => $posted,
                'url'   => $d['PositionURI'] ?? ''
            ];
        }

        if (count($items) < $per) break;
        $page++;
        if ($page > 5) break; // safety cap
    }
    usort($results, fn($a,$b) => strtotime($b['date']) <=> strtotime($a['date']));
    return $results;
}

function smtp_mail($to, $subject, $body) {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_SECURE, $SMTP_USERNAME, $SMTP_PASSWORD, $SMTP_FROM, $SMTP_FROM_NAME;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->Port       = $SMTP_PORT;
        $mail->SMTPSecure = $SMTP_SECURE;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USERNAME;
        $mail->Password   = $SMTP_PASSWORD;

        $mail->setFrom($SMTP_FROM, $SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $body;

        return $mail->send();
    } catch (\Throwable $e) {
        error_log("SMTP error to $to: " . $e->getMessage());
        return false;
    }
}

$nowIso  = date('c');
$changed = false;

foreach ($prefs as $username => $cfg) {
    $enabled = (int)($cfg['email_enabled'] ?? 0);
    $toEmail = trim($cfg['email'] ?? '');
    if (!$enabled || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) continue;

    $since = $lastMap[$username]['last_sent_at'] ?? null;
    $jobs  = fetch_jobs_since_all($since);

    if (!$jobs) {
        $lastMap[$username]['last_checked_at'] = $nowIso;
        $changed = true;
        continue;
    }

    $lines = [];
    $lines[] = "New USAJOBS postings since " . ($since ?: 'last check') . ":";
    $max = 25;
    $i = 0;
    foreach ($jobs as $j) {
        $line = "- {$j['title']} — {$j['org']} — {$j['loc']} (Posted: {$j['date']})";
        if (!empty($j['url'])) $line .= "  {$j['url']}";
        $lines[] = $line;
        $i++; if ($i >= $max) break;
    }
    if (count($jobs) > $max) {
        $lines[] = "";
        $lines[] = "(" . (count($jobs) - $max) . " more not shown)";
    }
    $lines[] = "";
    $lines[] = "Manage alerts: https://yourdomain/home.php";

    $ok = smtp_mail($toEmail, "New USAJOBS alerts", implode("\n", $lines));
    if ($ok) {
        $lastMap[$username]['last_sent_at'] = $nowIso;
        $changed = true;
    }
}

if ($changed) {
    if (!is_dir(__DIR__ . '/data')) @mkdir(__DIR__ . '/data', 0775, true);
    file_put_contents($sinceFile, json_encode($lastMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

