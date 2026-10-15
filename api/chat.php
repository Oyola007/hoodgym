<?php
/**
 * HoodGym AI Chat Widget - backend endpoint
 *
 * Receives: POST { "message": "..." }
 * Returns:  { "reply": "..." }  or  { "error": "..." }
 *
 * Keeps conversation history in the PHP session so the bot has context
 * across turns without trusting anything the client sends about past
 * messages.
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

// ---- Basic request validation ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = isset($input['message']) ? trim($input['message']) : '';

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

if (mb_strlen($userMessage) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long']);
    exit;
}

if (!defined('ANTHROPIC_API_KEY') || ANTHROPIC_API_KEY === 'PASTE_YOUR_ANTHROPIC_API_KEY_HERE') {
    http_response_code(500);
    echo json_encode(['error' => 'AI chat is not configured yet. Add your API key in api/config.php.']);
    exit;
}

// ---- Pull live context from the database -------------------------------
$scheduleText = "No classes are currently scheduled.";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn->connect_error) {
        $result = $conn->query("SELECT day, class_name, time FROM classes ORDER BY id ASC");
        if ($result && $result->num_rows > 0) {
            $lines = [];
            while ($row = $result->fetch_assoc()) {
                $lines[] = "- {$row['day']}: {$row['class_name']} at {$row['time']}";
            }
            $scheduleText = implode("\n", $lines);
        }

        // Optional: include packages if that table exists in your schema.
        // Uncomment and adjust column names once your packages table is finalized.
        /*
        $pkgResult = $conn->query("SELECT name, price, description FROM packages ORDER BY price ASC");
        if ($pkgResult && $pkgResult->num_rows > 0) {
            $pkgLines = [];
            while ($row = $pkgResult->fetch_assoc()) {
                $pkgLines[] = "- {$row['name']}: KES {$row['price']} — {$row['description']}";
            }
            $packagesText = implode("\n", $pkgLines);
        }
        */

        $conn->close();
    }
} catch (Exception $e) {
    // Fail quietly - the bot can still answer general questions without live data.
}

// ---- Build the system prompt -------------------------------------------
$systemPrompt = <<<PROMPT
You are the friendly front-desk AI assistant for HoodGym, a gym in Machakos, Kenya.

Facts about HoodGym you can rely on:
- Location: Machakos, Kenya
- Phone: +254 790 322 423
- Email: evansomondi392@gmail.com
- Open 7 days a week
- Facilities: Strength & Cardio zone, Yoga & Wellness studio, Personal Training, Showers & Lockers, Online class booking
- Members can register an account and log in to book classes

Current class schedule (live from the database):
{$scheduleText}

Guidelines:
- Be warm, concise, and motivating - like a helpful gym front-desk staffer, not a generic chatbot.
- If asked about pricing/packages and you don't have exact numbers, tell the user to check the "Join Now" page or contact the gym directly rather than guessing.
- If asked about the schedule, use the live data above - don't invent classes.
- Encourage people to register or log in when relevant, but don't be pushy.
- Keep answers short (2-4 sentences) unless the user asks for detail.
- If you don't know something, say so plainly and point them to the phone/email above.
PROMPT;

// ---- Maintain short conversation history in session ---------------------
if (!isset($_SESSION['hoodgym_chat_history']) || !is_array($_SESSION['hoodgym_chat_history'])) {
    $_SESSION['hoodgym_chat_history'] = [];
}

$_SESSION['hoodgym_chat_history'][] = ['role' => 'user', 'content' => $userMessage];

// Cap history length so requests don't grow unbounded
$maxTurns = 12;
if (count($_SESSION['hoodgym_chat_history']) > $maxTurns) {
    $_SESSION['hoodgym_chat_history'] = array_slice($_SESSION['hoodgym_chat_history'], -$maxTurns);
}

// ---- Call the Anthropic API ---------------------------------------------
$payload = [
    'model' => ANTHROPIC_MODEL,
    'max_tokens' => 400,
    'system' => $systemPrompt,
    'messages' => $_SESSION['hoodgym_chat_history'],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Could not reach the AI service: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || !isset($data['content'][0]['text'])) {
    http_response_code(502);
    $msg = $data['error']['message'] ?? 'Unexpected response from AI service';
    echo json_encode(['error' => $msg]);
    exit;
}

$replyText = $data['content'][0]['text'];

// Save assistant reply into history for context on the next turn
$_SESSION['hoodgym_chat_history'][] = ['role' => 'assistant', 'content' => $replyText];

echo json_encode(['reply' => $replyText]);
