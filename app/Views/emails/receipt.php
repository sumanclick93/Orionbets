<?php
$customer = e($customerName ?? ($user['first_name'] ?? 'Member'));
$orderNumber = e((string) ($orderId ?? 'N/A'));
$plan = e((string) ($planName ?? 'Subscription Plan'));
$formattedAmount = is_numeric($amount ?? null) ? number_format((float) $amount, 2) : (string) ($amount ?? '0.00');
$curr = e(strtoupper((string) ($currency ?? 'USD')));
$orderDate = e((string) ($date ?? date('F j, Y')));
$method = e(ucwords((string) ($paymentMethod ?? 'Credit Card / Online')));
$interval = e(ucfirst((string) ($billingInterval ?? 'monthly')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt — Orion Bets</title>
</head>
<body style="margin:0;padding:0;background-color:#0b0e14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;line-height:1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#0b0e14;padding:30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background-color:#131822;border:1px solid #1e2638;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.5);">
                    <!-- Header -->
                    <tr>
                        <td style="padding:28px 32px;background-color:#182030;border-bottom:1px solid #232d42;text-align:left;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td>
                                        <span style="font-size:22px;font-weight:700;letter-spacing:-0.5px;color:#ffffff;text-decoration:none;">
                                            ORION<span style="color:#38bdf8;">BETS</span>
                                        </span>
                                    </td>
                                    <td align="right">
                                        <span style="display:inline-block;padding:4px 12px;border-radius:999px;background-color:rgba(56,189,248,0.1);color:#38bdf8;font-size:12px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;">
                                            Receipt Confirmed
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 12px 0;font-size:20px;font-weight:600;color:#ffffff;">
                                Payment Receipt
                            </h1>
                            <p style="margin:0 0 24px 0;font-size:14px;color:#94a3b8;">
                                Hello <?= $customer ?>, thank you for your purchase. Here is the receipt for your subscription payment.
                            </p>

                            <!-- Order Summary Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#0f141d;border:1px solid #1e2638;border-radius:12px;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:16px 20px;border-bottom:1px solid #1e2638;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="font-size:13px;color:#64748b;font-weight:500;">Order Reference</td>
                                                <td align="right" style="font-size:13px;color:#e2e8f0;font-family:monospace;font-weight:600;"><?= $orderNumber ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;border-bottom:1px solid #1e2638;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="font-size:13px;color:#64748b;font-weight:500;">Date</td>
                                                <td align="right" style="font-size:13px;color:#e2e8f0;"><?= $orderDate ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="font-size:13px;color:#64748b;font-weight:500;">Payment Method</td>
                                                <td align="right" style="font-size:13px;color:#e2e8f0;"><?= $method ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Line Items Table -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:24px;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th align="left" style="padding:10px 0;border-bottom:1px solid #232d42;font-size:12px;text-transform:uppercase;color:#64748b;font-weight:600;">Description</th>
                                        <th align="right" style="padding:10px 0;border-bottom:1px solid #232d42;font-size:12px;text-transform:uppercase;color:#64748b;font-weight:600;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:16px 0;border-bottom:1px solid #1a2233;vertical-align:top;">
                                            <div style="font-size:15px;font-weight:600;color:#ffffff;"><?= $plan ?></div>
                                            <div style="font-size:13px;color:#64748b;margin-top:2px;">Billing cycle: <?= $interval ?></div>
                                        </td>
                                        <td align="right" style="padding:16px 0;border-bottom:1px solid #1a2233;vertical-align:top;font-size:15px;font-weight:600;color:#ffffff;">
                                            $<?= $formattedAmount ?> <?= $curr ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:16px 0;font-size:16px;font-weight:700;color:#ffffff;">Total Paid</td>
                                        <td align="right" style="padding:16px 0;font-size:18px;font-weight:700;color:#38bdf8;">
                                            $<?= $formattedAmount ?> <?= $curr ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding:10px 0 20px 0;">
                                        <a href="<?= e(url('/dashboard')) ?>" style="display:inline-block;padding:14px 32px;background-color:#38bdf8;color:#0b0e14;font-size:14px;font-weight:700;text-decoration:none;border-radius:10px;text-align:center;">
                                            Access Your Member Desk
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:16px 0 0 0;font-size:13px;color:#64748b;text-align:center;">
                                Need assistance with your subscription? Reach out to our team at
                                <a href="mailto:support@orionbets.co" style="color:#38bdf8;text-decoration:none;">support@orionbets.co</a>.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:20px 32px;background-color:#0f141d;border-top:1px solid #1e2638;text-align:center;font-size:12px;color:#475569;">
                            <p style="margin:0 0 4px 0;">Orion Bets — Informational analytics product. Not a sportsbook.</p>
                            <p style="margin:0;">&copy; <?= date('Y') ?> Orion Bets. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
