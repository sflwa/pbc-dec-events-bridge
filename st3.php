<?php
/**
 * Solidarity Tech API - Single Event Detail
 *
 * Standalone PHP script to parse and display a single specific event.
 *
 * @package    SFLWA_Lab
 * @author     South Florida Web Advisors
 * @version    1.0.6
 */

// --- CONFIGURATION ---
$bearer_token = 'c830032f31e81b652b661480e58de3cbe010a4cbb7b9c8a16f2bef92b3da04a3bca8e52df976f33599557ccf05cc60f290181c62a61c2385339b761c0f477898';
$event_id     = '15492';
$endpoint     = "https://api.solidarity.tech/v1/events/{$event_id}";

// --- EXECUTION ---
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer {$bearer_token}",
        "Content-Type: application/json"
    ],
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$results = json_decode($response, true);
$event   = $results['data'] ?? null;

// --- STYLING & OUTPUT ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Detail: <?php echo htmlspecialchars($event['title'] ?? 'Not Found'); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 900px; margin: 40px auto; padding: 0 20px; background-color: #f8fafc; }
        .hero-card { background: #fff; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e2e8f0; }
        .hero-image { width: 100%; height: 350px; object-fit: cover; background: #0a3161; }
        .content { padding: 40px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 20px; }
        .badge-virtual { background: #dbeafe; color: #1e40af; }
        .description { background: #f1f5f9; padding: 25px; border-radius: 8px; font-size: 16px; margin: 25px 0; white-space: pre-line; border-left: 5px solid #0a3161; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .meta-box { background: #fff; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; }
        .automation-item { display: flex; align-items: center; gap: 10px; font-size: 13px; margin-bottom: 8px; }
        .check-icon { color: #10b981; font-weight: bold; }
        .x-icon { color: #ef4444; font-weight: bold; }
    </style>
</head>
<body>

    <?php if ($event) : ?>
        <div class="hero-card">
            <?php if ($event['image_url']) : ?>
                <img src="<?php echo $event['image_url']; ?>" class="hero-image">
            <?php endif; ?>

            <div class="content">
                <span class="badge badge-virtual"><?php echo $event['event_type']; ?></span>
                <h1 style="margin: 0 0 10px 0; font-size: 32px; color: #0f172a;"><?php echo htmlspecialchars($event['title']); ?></h1>
                
                <div style="color: #64748b; font-size: 14px;">
                    Event ID: <strong><?php echo $event['id']; ?></strong> | 
                    Org Scope: <strong><?php echo $event['scope_id']; ?></strong> | 
                    RSVP Count: <strong><?php echo $event['event_sessions'][0]['rsvp_count'] ?? 0; ?></strong>
                </div>

                <div class="description"><?php echo htmlspecialchars($event['description']); ?></div>

                <div class="meta-grid">
                    <div class="meta-box">
                        <h4 style="margin: 0 0 15px 0;">Next Session</h4>
                        <?php 
                        $session = $event['event_sessions'][0] ?? null;
                        if ($session) : 
                            $dt = new DateTime($session['start_time']);
                        ?>
                            <strong>Date:</strong> <?php echo $dt->format('l, F j, Y'); ?><br>
                            <strong>Time:</strong> <?php echo $dt->format('g:i A'); ?> EST<br>
                            <strong>Link:</strong> <a href="<?php echo $session['location_address']; ?>" target="_blank" style="color: #2563eb; font-size: 13px; word-break: break-all;"><?php echo $session['location_address']; ?></a>
                        <?php endif; ?>
                    </div>

                    <div class="meta-box">
                        <h4 style="margin: 0 0 15px 0;">Automation Status</h4>
                        <?php foreach ($event['automation_status'] as $key => $enabled) : ?>
                            <div class="automation-item">
                                <span class="<?php echo $enabled ? 'check-icon' : 'x-icon'; ?>">
                                    <?php echo $enabled ? '✔' : '✘'; ?>
                                </span>
                                <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top: 40px; text-align: center;">
                    <a href="<?php echo $event['event_page_url']; ?>" target="_blank" style="background: #0a3161; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600;">View Live Solidarity Page</a>
                </div>
            </div>
        </div>
    <?php else : ?>
        <div style="padding: 40px; text-align: center; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;">
            <h2 style="color: #ef4444;">Event Not Found</h2>
            <p>Check the ID or Bearer Token. HTTP Status: <?php echo $http_code; ?></p>
        </div>
    <?php endif; ?>

</body>
</html>
