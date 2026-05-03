<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only require PHPMailer if vendor exists
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/settings.php';

function sendEmail(
    PDO $db,
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody
): array {
    require_once __DIR__ . '/branding.php';
    $brand = loadBranding($db);
    $s = loadSettings($db);

    if (empty($s['mail_enabled']) || $s['mail_enabled'] !== '1') {
        return ['success' => false, 'message' => 'Email sending is disabled.'];
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return ['success' => false, 'message' => 'PHPMailer is not installed.'];
    }

    $provider = $s['mail_provider'] ?? 'smtp';

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        if ($provider === 'servermail') {
            $mail->isMail();
        } else {
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->Username = $s['mail_username'] ?? '';
            $mail->Password = $s['mail_password'] ?? '';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            switch ($provider) {
                case 'gmail':
                    $mail->Host = 'smtp.gmail.com';
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
                case 'xserver':
                case 'sakura':
                    $mail->Host = $s['mail_host'] ?? '';
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
                case 'conoha':
                    $mail->Host = 'smtp.conoha.ne.jp';
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
                case 'smtp':
                default:
                    $mail->Host = $s['mail_host'] ?? '';
                    $mail->Port = (int)($s['mail_port'] ?? 587);
                    $enc = $s['mail_encryption'] ?? 'tls';
                    $mail->SMTPSecure = $enc === 'ssl'
                        ? PHPMailer::ENCRYPTION_SMTPS
                        : PHPMailer::ENCRYPTION_STARTTLS;
                    break;
            }

            if (empty($mail->Host)) {
                return ['success' => false, 'message' => 'SMTP host is not configured'];
            }
        }

        $fromEmail = $s['mail_from_email'] ?? $s['mail_username'] ?? '';
        $fromName = $s['mail_from_name'] ?? ($brand['brand_name'] ?? 'AuctionKai');

        if (empty($fromEmail)) {
            return ['success' => false, 'message' => 'From email address is not set'];
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();

        return ['success' => true, 'message' => 'Email sent to ' . $toEmail];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Send failed: ' . $mail->ErrorInfo];
    }
}

function sendSettlementEmail(
    array $member,
    array $auction,
    string $htmlBody,
    PDO $db
): array {
    require_once __DIR__ . '/branding.php';
    $brand = loadBranding($db);

    $s = loadSettings($db);

    if (empty($s['mail_enabled']) || $s['mail_enabled'] !== '1') {
        return [
            'success' => false,
            'message' => 'Email sending is disabled. Configure in Admin → Email Settings.'
        ];
    }

    if (empty($member['email'])) {
        return [
            'success' => false,
            'message' => 'This member has no email address'
        ];
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return [
            'success' => false,
            'message' => 'PHPMailer is not installed. See README → Email Setup.'
        ];
    }

    $provider = $s['mail_provider'] ?? 'smtp';

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        if ($provider === 'servermail') {
            $mail->isMail();
        } else {
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->Username = $s['mail_username'] ?? '';
            $mail->Password = $s['mail_password'] ?? '';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            switch ($provider) {
                case 'gmail':
                    $mail->Host = 'smtp.gmail.com';
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
                case 'xserver':
                    $mail->Host = $s['mail_host'] ?? '';
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
                case 'sakura':
                    $mail->Host = $s['mail_host'] ?? '';
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
                case 'conoha':
                    $mail->Host = 'smtp.conoha.ne.jp';
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
                case 'smtp':
                default:
                    $mail->Host = $s['mail_host'] ?? '';
                    $mail->Port = (int)($s['mail_port'] ?? 587);
                    $enc = $s['mail_encryption'] ?? 'tls';
                    $mail->SMTPSecure = $enc === 'ssl'
                        ? PHPMailer::ENCRYPTION_SMTPS
                        : PHPMailer::ENCRYPTION_STARTTLS;
                    break;
            }

            if (empty($mail->Host)) {
                return ['success' => false, 'message' => 'SMTP host is not configured'];
            }
        }

        $fromEmail = $s['mail_from_email'] ?? $s['mail_username'] ?? '';
        $fromName = $s['mail_from_name'] ?? ($brand['brand_name'] ?? 'AuctionKai');

        if (empty($fromEmail)) {
            return ['success' => false, 'message' => 'From email address is not set'];
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($member['email'], $member['name']);

        $mail->Subject = '精算書 / Settlement Statement — ' . ($brand['brand_name'] ?? 'AuctionKai') . ' · ' . $auction['name'] . ' (' . $auction['date'] . ')';
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();

        return ['success' => true, 'message' => 'Email sent to ' . $member['email']];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Send failed: ' . $mail->ErrorInfo];
    }
}

function buildEmailBody(
    array $member,
    array $auction,
    array $s,
    array $fees
): string {
    global $db;
    require_once __DIR__ . '/branding.php';
    $brand = isset($db) ? loadBranding($db) : [];
    $brandName = $brand['brand_name'] ?? 'AuctionKai';
    $accentColor = sanitizeColor($brand['brand_accent_color'] ?? '#D4A84B');
    $footerText = $brand['brand_footer_text'] ?? 'Designed & Developed by Mirai Global Solutions';
    $rows = '';
    foreach ($s['mv'] as $v) {
        $rows .= "
        <tr>
          <td style='padding:8px 10px;border-bottom:1px solid #f0f0f0'>" . htmlspecialchars($v['lot'] ?? '') . "</td>
          <td style='padding:8px 10px;border-bottom:1px solid #f0f0f0'>" . htmlspecialchars($v['make'] . ' ' . $v['model']) . "</td>
          <td style='padding:8px 10px;border-bottom:1px solid #f0f0f0;text-align:right;font-family:monospace'>¥" . number_format($v['sold_price']) . "</td>
        </tr>";
    }

    return "
    <!DOCTYPE html><html><head><meta charset='UTF-8'></head>
    <body style='font-family:sans-serif;background:#f4f4f4;padding:20px;color:#111;font-size:13px'>
      <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)'>
        <div style='background:#0A1420;padding:24px 32px'>
          <div style='font-size:22px;font-weight:700;color:' . $accentColor . ''>⚡ ' . h($brandName) . ' 精算書</div>
          <div style='font-size:12px;color:#6A88A0;margin-top:4px'>Settlement Statement · " . htmlspecialchars($auction['name']) . "</div>
        </div>
        <div style='padding:24px 32px 0'>
          <p style='font-size:15px;font-weight:600;margin-bottom:8px'>Dear " . htmlspecialchars($member['name']) . ",</p>
          <p style='color:#555;line-height:1.6'>Please find your settlement statement for <strong>" . htmlspecialchars($auction['name']) . "</strong> held on " . htmlspecialchars($auction['date']) . ".</p>
        </div>
        <div style='padding:20px 32px'>
          <table style='width:100%;border-collapse:collapse'>
            <thead><tr style='background:#f5f5f5'>
              <th style='padding:8px 10px;text-align:left;font-size:10px;color:#555'>LOT</th>
              <th style='padding:8px 10px;text-align:left;font-size:10px;color:#555'>VEHICLE</th>
              <th style='padding:8px 10px;text-align:right;font-size:10px;color:#555'>PRICE</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
          </table>
        </div>
        <div style='margin:0 32px 20px;background:#f9f9f9;border:1px solid #e8e8e8;border-radius:8px;padding:16px'>
          <table style='width:100%;font-size:13px'>
            <tr><td style='padding:5px 0'>Gross Sales</td><td style='text-align:right;font-family:monospace'>¥" . number_format($s['grossSales']) . "</td></tr>
            <tr style='border-top:1px dashed #ddd'><td style='padding:8px 0 5px;color:#777'>Total Deductions</td><td style='text-align:right;font-family:monospace;color:#CC7777;padding-top:8px'>−¥" . number_format($s['totalDed']) . "</td></tr>
            <tr style='border-top:2px solid #ccc;font-weight:700'><td style='padding:8px 0'>NET PAYOUT</td><td style='text-align:right;font-family:monospace;color:' . $accentColor . ''>¥" . number_format($s['netPayout']) . "</td></tr>
          </table>
        </div>
        <div style='background:#0A1420;padding:16px 32px;text-align:center;font-size:11px;color:#6A88A0'>" . htmlspecialchars($auction['name']) . " · " . htmlspecialchars($auction['date']) . "<br>Designed &amp; Developed by ' . h($footerText) . '</div>
      </div>
    </body></html>";
}
