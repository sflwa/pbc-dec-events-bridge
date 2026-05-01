<?php
/**
 * Solidarity Tech - Self-Agent Registration Bridge
 * * v1.2.1 - Maps agent_user_id to the user_id for self-registration.
 * @package SFLWA_Lab
 * @author  South Florida Web Advisors
 */

// --- CONFIGURATION ---
$bearer_token    = 'c830032f31e81b652b661480e58de3cbe010a4cbb7b9c8a16f2bef92b3da04a3bca8e52df976f33599557ccf05cc60f290181c62a61c2385339b761c0f477898';
$target_event_id = 15492; 
$log             = [];

/**
 * Universal API Caller
 */
function solidarity_request($method, $path, $token, $data = null) {
    $url = "https://api.solidarity.tech/v1/" . ltrim($path, '/');
    $ch  = curl_init($url);
    
    $headers = [
        "Authorization: Bearer $token",
        "Content-Type: application/json",
        "Accept: application/json"
    ];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30
    ];

    if ($data) {
        $opts[CURLOPT_POSTFIELDS] = json_encode(['data' => $data]);
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'data'   => json_decode($response, true)['data'] ?? null,
        'full'   => json_decode($response, true)
    ];
}

// --- LOGIC EXECUTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone_number'])) {
    
    // Normalize phone to E.164
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone_number']);
    if (strlen($phone) === 10) $phone = "+1" . $phone;

    $log[] = "Searching for profile: $phone";

    // 1. DEDUPLICATION CHECK
    $search = solidarity_request('GET', "users?phone_number=" . urlencode($phone), $bearer_token);
    $user_id = $search['data'][0]['id'] ?? null;

    if (!$user_id) {
        $log[] = "No existing profile. Creating new user...";
        $create = solidarity_request('POST', 'users', $bearer_token, [
            'first_name'   => $_POST['first_name'],
            'last_name'    => $_POST['last_name'],
            'email'        => $_POST['email'],
            'phone_number' => $phone
        ]);
        $user_id = $create['data']['id'] ?? null;
    }

    if ($user_id) {
        $log[] = "Handshake Successful. User ID: $user_id";

        // 2. FETCH SESSION
        $event = solidarity_request('GET', "events/$target_event_id", $bearer_token);
        $session_id = $event['data']['event_sessions'][0]['id'] ?? null;

        if ($session_id) {
            $log[] = "Mapping RSVP to Session: $session_id";
            
            // 3. POST RSVP (Mapping Agent to User)
            $rsvp_data = [
                'event_id'                 => (int)$target_event_id,
                'event_session_id'         => (int)$session_id,
                'user_id'                  => (int)$user_id,
                'is_attending'             => $_POST['is_attending'],
                'is_confirmed'             => true,
                'agent_user_id'            => (int)$user_id, // SELF AS AGENT
                'source'                   => 'WordPress Bridge',
                'skip_email_confirmation'  => false
            ];

            $rsvp = solidarity_request('POST', 'event_rsvps', $bearer_token, $rsvp_data);

            if ($rsvp['status'] === 200 || $rsvp['status'] === 201) {
                $log[] = "SUCCESS: User $user_id successfully RSVP'd as their own agent.";
            } else {
                $log[] = "FAILED: " . ($rsvp['full']['errors'][0] ?? 'Unknown API Error');
            }
        }
    } else {
        $log[] = "FAILED: Could not retrieve or create a User ID.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Solidarity Bridge v1.2.1</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f4f7f9; display: flex; justify-content: center; padding-top: 50px; }
        .form-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        input, select { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        button { width: 100%; padding: 14px; background: #0a3161; color: #fff; border: none; border-radius: 4px; font-weight: 700; cursor: pointer; margin-top: 10px; }
        .terminal { background: #1a202c; color: #48bb78; padding: 15px; border-radius: 6px; margin-top: 20px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4; }
    </style>
</head>
<body>

<div class="form-container">
    <h2 style="margin-top: 0; color: #1e293b;">Event Registration</h2>
    <form method="POST">
        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="tel" name="phone_number" placeholder="Phone Number" required>
        <select name="is_attending">
            <option value="yes">I am attending</option>
            <option value="maybe">I might attend</option>
            <option value="no">I cannot attend</option>
        </select>
        <button type="submit">Register & RSVP</button>
    </form>

    <?php if ($log): ?>
        <div class="terminal">
            <?php foreach ($log as $line) echo "> $line<br>"; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
