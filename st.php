<?php
/**
 * Solidarity Tech API - Data Display
 *
 * Standalone PHP script to parse and display Organization & Assessment data.
 *
 * @package    SFLWA_Lab
 * @author     South Florida Web Advisors
 * @version    1.0.4
 */

// --- CONFIGURATION ---
$bearer_token = 'c830032f31e81b652b661480e58de3cbe010a4cbb7b9c8a16f2bef92b3da04a3bca8e52df976f33599557ccf05cc60f290181c62a61c2385339b761c0f477898';
$endpoint     = 'https://api.solidarity.tech/v1/organizations?_limit=20&_offset=0&_since=0';

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
    <title>Solidarity Tech API Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 1100px; margin: 40px auto; padding: 0 20px; background-color: #f9fafb; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .org-header { display: flex; align-items: center; gap: 20px; border-bottom: 2px solid #f3f4f6; padding-bottom: 20px; margin-bottom: 20px; }
        .org-logo { max-width: 120px; height: auto; background: #0a3161; padding: 8px; border-radius: 6px; }
        .status-pill { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 0.025em; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; }
        th { text-align: left; padding: 12px 15px; border-bottom: 2px solid #edf2f7; background-color: #f8fafc; color: #64748b; font-size: 12px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #ef4444; font-size: 13px; }
        .error-box { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 20px; border-radius: 8px; }
    </style>
</head>
<body>

    <h2>Solidarity Tech Interface Test</h2>

    <?php if (isset($results['data']) && is_array($results['data'])) : ?>
        <?php foreach ($results['data'] as $org) : ?>
            <div class="card">
                <div class="org-header">
                    <?php if (!empty($org['image_url'])) : ?>
                        <img src="<?php echo $org['image_url']; ?>" class="org-logo" alt="Logo">
                    <?php endif; ?>
                    <div>
                        <h3 style="margin:0; font-size: 24px; color: #1e293b;"><?php echo htmlspecialchars($org['name']); ?></h3>
                        <p style="margin: 5px 0 0; color: #64748b;">
                            <strong>ID:</strong> <?php echo $org['id']; ?> | 
                            <strong>Default Language:</strong> <?php echo strtoupper($org['default_language']); ?>
                        </p>
                    </div>
                </div>

                <h4 style="color: #475569; margin-bottom: 10px;">Mapped Assessment Statuses</h4>
                <p style="font-size: 14px; color: #64748b;">These keys are available for contact tagging and filtering within the bridge.</p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Status Label</th>
                            <th>API Key / Slug</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($org['assessment_statuses'])) : ?>
                            <?php foreach ($org['assessment_statuses'] as $status) : ?>
                                <tr>
                                    <td>
                                        <span class="status-pill" style="background-color: <?php echo !empty($status['color']) ? $status['color'] : '#64748b'; ?>;">
                                            <?php echo htmlspecialchars($status['label']); ?>
                                        </span>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($status['key'] ?: 'n/a'); ?></code></td>
                                    <td style="font-size: 13px; color: #4b5563;">
                                        <?php echo htmlspecialchars($status['description'] ?: '--'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="3">No assessment statuses found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <div class="error-box">
            <strong>API Error:</strong> Unable to retrieve data.<br>
            HTTP Code: <?php echo $http_code; ?><br>
            Response: <?php echo htmlspecialchars($response); ?>
        </div>
    <?php endif; ?>

</body>
</html>
