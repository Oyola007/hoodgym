<?php
/**
 * AI Chat Widget Configuration
 *
 * Get your API key from https://console.anthropic.com  (Settings -> API Keys)
 *
 * DO NOT commit this file with a real key inside it to a public repo.
 * Preferred: set it as an actual environment variable in XAMPP/Apache
 * (e.g. via SetEnv in httpd.conf or a .env loader). As a fallback for
 * local development, you can paste the key directly into the constant
 * below.
 */

// Try to read from environment first, fall back to the literal string.
$envKey = getenv('ANTHROPIC_API_KEY');
define('ANTHROPIC_API_KEY', $envKey !== false ? $envKey : 'PASTE_YOUR_ANTHROPIC_API_KEY_HERE');

// Model used for the chat widget. Haiku is fast/cheap and plenty for FAQ-style
// gym questions. Swap to 'claude-sonnet-5' if you want smarter, richer answers.
define('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001');

// DB connection (reuse your existing credentials)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hoodgym');
