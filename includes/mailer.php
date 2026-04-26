<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

/**
 * Send settlement statement email to a member
 * 
 * @param array $member Member data (name, email)
 * @param array $auction Auction data (name, date)
 * @param string $htmlBody Full HTML email body
 * @return array ['success' => bool, 'message' => string]
 */
function sendSettlementEmail(
    array $member, 
    array $auction, 
    string $htmlBody
): array {

    if (!MAIL_ENABLED) {
        return [
            'success' => false, 
            'message' => 'Email sending is not configured. Set MAIL_ENABLED to true in config.php'
        ];
    }

    if (empty($member['email'])) {
        return [
            'success' => false, 
            'message' => 'Member has no email address'
        ];
    }

    try {
        $mail = new PHPMailer(true);

        // SMTP Settings
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8';

        // From / To
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($member['email'], $member['name']);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        // Subject
        $mail->Subject = '精算書 / Settlement Statement — ' 
            . $auction['name'] 
            . ' (' . $auction['date'] . ')';

        // HTML Body
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();

        return [
            'success' => true, 
            'message' => 'Email sent to ' . $member['email']
        ];

    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Email failed: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Build HTML email body for settlement statement
 */
function buildEmailBody(
    array $member, 
    array $auction, 
    array $s
): string {
    $rows = '';
    foreach ($s['mv'] as $v) {
        $rows .= "
        <tr>
          <td style='padding:8px 10px;border-bottom:1px solid #f0f0f0'>{$v['lot']}</td>
          <td style='padding:8px 10px;border-bottom:1px solid #f0f0f0'>
            {$v['make']} {$v['model']}</td>
          <td style='padding:8px 10px;border-bottom:1px solid #f0f0f0;
            text-align:right;font-family:monospace'>
            ¥" . number_format($v['sold_price']) . "
          </td>
        </tr>";
    }

    return "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='UTF-8'>
      <meta name='viewport' content='width=device-width'>
    </head>
    <body style='font-family:\"Noto Sans JP\",sans-serif;
      background:#f4f4f4;padding:20px;
      color:#111;font-size:13px'>
      <div style='max-width:600px;margin:0 auto;
        background:#fff;border-radius:12px;
        overflow:hidden;
        box-shadow:0 4px 20px rgba(0,0,0,.1)'>

        <!-- Header -->
        <div style='background:#0A1420;padding:24px 32px'>
          <div style='font-size:22px;font-weight:700;
            color:#D4A84B'>
            ⚡ AuctionKai 精算書
          </div>
          <div style='font-size:12px;color:#6A88A0;
            margin-top:4px'>
            Settlement Statement · {$auction['name']}
          </div>
        </div>

        <!-- Greeting -->
        <div style='padding:24px 32px 0'>
          <p style='font-size:15px;font-weight:600;
            margin-bottom:6px'>
            Dear {$member['name']},
          </p>
          <p style='color:#555;line-height:1.6'>
            Please find your settlement statement for 
            <strong>{$auction['name']}</strong> 
            held on {$auction['date']} below.
          </p>
        </div>

        <!-- Vehicles table -->
        <div style='padding:20px 32px'>
          <div style='font-size:10px;font-weight:700;
            letter-spacing:2px;color:#999;
            margin-bottom:8px;
            text-transform:uppercase'>
            Sold Vehicles
          </div>
          <table style='width:100%;border-collapse:collapse'>
            <thead>
              <tr style='background:#f5f5f5'>
                <th style='padding:8px 10px;text-align:left;
                  font-size:10px;font-weight:700;
                  letter-spacing:1px;
                  text-transform:uppercase;
                  color:#555'>Lot</th>
                <th style='padding:8px 10px;text-align:left;
                  font-size:10px;font-weight:700;
                  letter-spacing:1px;
                  text-transform:uppercase;
                  color:#555'>Vehicle</th>
                <th style='padding:8px 10px;text-align:right;
                  font-size:10px;font-weight:700;
                  letter-spacing:1px;
                  text-transform:uppercase;
                  color:#555'>Sold Price</th>
              </tr>
            </thead>
            <tbody>{$rows}</tbody>
          </table>
        </div>

        <!-- Fee breakdown -->
        <div style='margin:0 32px 20px;background:#f9f9f9;
          border:1px solid #e8e8e8;border-radius:8px;
          padding:16px'>
          <table style='width:100%;border-collapse:collapse;
            font-size:13px'>
            <tr>
              <td style='padding:5px 0'>Gross Sales</td>
              <td style='text-align:right;
                font-family:monospace'>
                ¥" . number_format($s['grossSales']) . "
              </td>
            </tr>
            <tr style='border-top:1px dashed #ddd'>
              <td style='padding:8px 0 5px;color:#777'>
                Listing Fee ×{$s['count']}</td>
              <td style='text-align:right;
                font-family:monospace;color:#CC7777;
                padding-top:8px'>
                −¥" . number_format($s['listingFeeTotal']) . "
              </td>
            </tr>
            <tr>
              <td style='padding:5px 0;color:#777'>
                Sold Fee ×{$s['count']}</td>
              <td style='text-align:right;
                font-family:monospace;
                color:#CC7777'>
                −¥" . number_format($s['soldFeeTotal']) . "
              </td>
            </tr>
            <tr>
              <td style='padding:5px 0;color:#777'>
                Nagare Fee ×{$s['unsoldCount']}</td>
              <td style='text-align:right;
                font-family:monospace;
                color:#CC7777'>
                −¥" . number_format($s['nagareFeeTotal']) . "
              </td>
            </tr>
            <tr>
              <td style='padding:5px 0;color:#777'>
                Commission ¥" . number_format($s['commissionFee']) . "/member</td>
              <td style='text-align:right;
                font-family:monospace;
                color:#CC7777'>
                −¥" . number_format($s['commissionTotal']) . "
              </td>
            </tr>
            <tr style='border-top:2px solid #ccc;
              font-weight:700'>
              <td style='padding:8px 0'>
                Total Deductions</td>
              <td style='text-align:right;
                font-family:monospace;
                color:#CC7777'>
                −¥" . number_format($s['totalDed']) . "
              </td>
            </tr>
          </table>
        </div>

        <!-- Net payout -->
        <div style='margin:0 32px 28px;background:#0A1420;
          border-radius:10px;padding:16px 20px;
          display:flex;justify-content:space-between;
          align-items:center'>
          <span style='color:#E8DCC8;font-weight:600'>
            NET PAYOUT / お支払い額
          </span>
          <span style='color:#D4A84B;font-size:22px;
            font-weight:700;font-family:monospace'>
            ¥" . number_format($s['netPayout']) . "
          </span>
        </div>

        <!-- Footer -->
        <div style='background:#f5f5f5;padding:16px 32px;
          text-align:center;font-size:11px;
          color:#999;border-top:1px solid #eee'>
          {$auction['name']} · {$auction['date']} · 
          Auto-generated by AuctionKai<br>
          Designed &amp; Developed by 
          Mirai Global Solutions
        </div>

      </div>
    </body>
    </html>";
}
