<?php
/**
 * Solidarity Tech API - Events & Sessions Display
 *
 * Standalone PHP script to parse and display complex Event data.
 *
 * @package    SFLWA_Lab
 * @author     South Florida Web Advisors
 * @version    1.0.5
 */

// --- CONFIGURATION ---
$bearer_token = 'c830032f31e81b652b661480e58de3cbe010a4cbb7b9c8a16f2bef92b3da04a3bca8e52df976f33599557ccf05cc60f290181c62a61c2385339b761c0f477898';
$endpoint     = 'https://api.solidarity.tech/v1/events?_limit=20&_offset=0&_since=0';

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

// --- STYLING & OUTPUT ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Solidarity Events Discovery</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 1200px; margin: 40px auto; padding: 0 20px; background-color: #f1f5f9; }
        .event-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 30px; display: flex; flex-direction: column; }
        .event-main { display: flex; padding: 25px; gap: 25px; border-bottom: 1px solid #e2e8f0; }
        .event-image { width: 200px; height: 150px; object-fit: cover; border-radius: 8px; background: #0a3161; }
        .event-details { flex: 1; }
        .type-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; margin-bottom: 10px; background: #e2e8f0; color: #475569; }
        .type-virtual { background: #dbeafe; color: #1e40af; }
        .type-in_person { background: #dcfce7; color: #166534; }
        
        .sessions-area { background: #f8fafc; padding: 20px; }
        .session-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
        .session-item { background: #fff; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; font-size: 13px; }
        .rsvp-count { float: right; background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
        .btn-link { display: inline-block; margin-top: 10px; padding: 5px 10px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px; font-size: 12px; }
        h2, h3 { margin-top: 0; }
    </style>
</head>
<body>

    <header style="margin-bottom: 30px;">
        <h2>Solidarity Tech: Event Sync Preview</h2>
        <p>Total Events Found: <strong><?php echo $results['meta']['total_count'] ?? 0; ?></strong></p>
    </header>

    <?php if (isset($results['data']) && is_array($results['data'])) : ?>
        <?php foreach ($results['data'] as $event) : ?>
            <div class="event-card">
                <div class="event-main">
                    <?php if (!empty($event['image_url'])) : ?>
                        <img src="<?php echo $event['image_url']; ?>" class="event-image">
                    <?php else : ?>
                        <div class="event-image" style="display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px;">NO IMAGE</div>
                    <?php endif; ?>

                    <div class="event-details">
                        <span class="type-badge type-<?php echo $event['event_type']; ?>">
                            <?php echo str_replace('_', ' ', $event['event_type']); ?>
                        </span>
                        <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                        <p style="font-size: 14px; color: #64748b; margin-bottom: 15px;">
                            <?php echo substr(strip_tags($event['description']), 0, 180); ?>...
                        </p>
                        <small>Scope ID: <code><?php echo $event['scope_id']; ?></code> (<?php echo $event['scope_type']; ?>)</small>
                    </div>
                </div>

                <div class="sessions-area">
                    <h5 style="margin: 0 0 15px 0; text-transform: uppercase; color: #94a3b8; font-size: 11px; letter-spacing: 1px;">Scheduled Sessions</h5>
                    <div class="session-grid">
                        <?php if (!empty($event['event_sessions'])) : ?>
                            <?php foreach ($event['event_sessions'] as $session) : 
                                $start = new DateTime($session['start_time']);
                                ?>
                                <div class="session-item">
                                    <span class="rsvp-count"><?php echo $session['rsvp_count']; ?> RSVPs</span>
                                    <strong><?php echo $start->format('M j, Y'); ?></strong><br>
                                    <?php echo $start->format('g:i A'); ?> (EST)<br>
                                    
                                    <div style="margin-top: 8px; border-top: 1px solid #f1f5f9; padding-top: 8px; color: #64748b;">
                                        <?php if ($event['event_type'] === 'virtual') : ?>
                                            <?php if (filter_var($session['location_address'], FILTER_VALIDATE_URL)) : ?>
                                                <a href="<?php echo $session['location_address']; ?>" target="_blank" class="btn-link">Join Virtual Meeting</a>
                                            <?php else : ?>
                                                <em>Virtual (Link Sent on RSVP)</em>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            📍 <?php echo htmlspecialchars($session['location_name'] ?: 'Location TBA'); ?><br>
                                            <small><?php echo htmlspecialchars($session['location_address']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p>No active sessions found for this event.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <div style="background:#fff; padding:20px; border-radius:8px;">No event data found. Check token or endpoint.</div>
    <?php endif; ?>

</body>
</html>
