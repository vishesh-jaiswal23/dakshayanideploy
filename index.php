<?php
// --- Configuration ---
define('DATA_DIR', __DIR__ . '/server/data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('SITE_SETTINGS_FILE', DATA_DIR . '/site-settings.json');
define('SEARCH_INDEX_FILE', DATA_DIR . '/search-index.json');
define('KNOWLEDGE_FILE', DATA_DIR . '/knowledge-articles.json');
define('TESTIMONIALS_FILE', DATA_DIR . '/testimonials.json');
define('CASE_STUDIES_FILE', DATA_DIR . '/case-studies.json');
define('TICKETS_FILE', DATA_DIR . '/tickets.json');
define('BLOG_POSTS_FILE', DATA_DIR . '/blog-posts.json');
define('LEADS_FILE', DATA_DIR . '/leads.json');

// --- Helper Functions ---

/**
 * Ensures the data directory exists.
 */
function ensureDataDirectory() {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
}

/**
 * Reads and decodes a JSON file.
 * @param string $filePath The path to the JSON file.
 * @param mixed $fallback The fallback value to return if the file doesn't exist or is invalid.
 * @return mixed The decoded JSON data or the fallback value.
 */
function readJsonFile($filePath, $fallback = null) {
    ensureDataDirectory();
    if (!file_exists($filePath)) {
        if ($fallback !== null) {
            file_put_contents($filePath, json_encode($fallback, JSON_PRETTY_PRINT));
        }
        return $fallback;
    }
    $raw = file_get_contents($filePath);
    $decoded = json_decode($raw, true);
    return $decoded !== null ? $decoded : $fallback;
}

/**
 * Encodes and writes data to a JSON file.
 * @param string $filePath The path to the JSON file.
 * @param mixed $data The data to write.
 */
function writeJsonFile($filePath, $data) {
    ensureDataDirectory();
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Sends a JSON response.
 * @param int $statusCode The HTTP status code.
 * @param mixed $payload The payload to encode as JSON.
 */
function sendJson($statusCode, $payload) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

/**
 * Sends a 404 Not Found response.
 */
function sendNotFound() {
    http_response_code(404);
    echo 'Not found';
    exit;
}

/**
 * Gets the current authenticated user from the session.
 * @return array|null The authenticated user or null.
 */
function getAuthenticatedUser() {
    session_start();
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Determines the MIME type of a file based on its extension.
 */
function getContentType($filePath) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $map = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'php'  => 'text/html; charset=utf-8', // PHP files are served as HTML
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
    ];
    return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}

/**
 * Sets the security headers for the response.
 */
function setSecurityHeaders() {
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://www.google.com https://www.gstatic.com https://apis.google.com https://maps.googleapis.com https://www.youtube.com https://www.google-analytics.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "img-src 'self' data: https://www.gstatic.com https://maps.gstatic.com https://maps.googleapis.com https://i.ytimg.com",
        "font-src 'self' https://fonts.gstatic.com",
        "connect-src 'self' https://solar.googleapis.com https://www.google.com https://www.gstatic.com https://oauth2.googleapis.com https://www.googleapis.com https://www.youtube.com",
        "frame-src 'self' https://www.youtube.com https://www.google.com",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self' https://www.google.com",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), microphone=()');
}


// --- API Routing ---

/**
 * Handles all API requests.
 * @param string $request_path The path of the API request.
 */
function handleApiRequest($request_path) {
    $user = getAuthenticatedUser();

    // Public API endpoints
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $request_path === '/api/public/site-settings') {
        $settings = readJsonFile(SITE_SETTINGS_FILE, []);
        sendJson(200, ['settings' => $settings]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $request_path === '/api/public/search') {
        $index = readJsonFile(SEARCH_INDEX_FILE, []);
        sendJson(200, ['results' => $index, 'total' => count($index), 'query' => '']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $request_path === '/api/public/knowledge') {
        $data = readJsonFile(KNOWLEDGE_FILE, ['categories' => [], 'articles' => []]);
        sendJson(200, $data);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $request_path === '/api/public/testimonials') {
        $testimonials = readJsonFile(TESTIMONIALS_FILE, []);
        sendJson(200, ['testimonials' => $testimonials]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $request_path === '/api/public/case-studies') {
        $caseStudies = readJsonFile(CASE_STUDIES_FILE, []);
        sendJson(200, ['caseStudies' => $caseStudies]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $request_path === '/api/public/blog-posts') {
        $posts = readJsonFile(BLOG_POSTS_FILE, []);
        sendJson(200, ['posts' => $posts]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/^\/api\/public\/blog-posts\/(?<slug>[^\/]+)$/', $request_path, $matches)) {
        $slug = $matches['slug'];
        $posts = readJsonFile(BLOG_POSTS_FILE, []);
        $post = null;
        foreach ($posts as $p) {
            if (isset($p['slug']) && $p['slug'] === $slug) {
                $post = $p;
                break;
            }
        }
        if ($post) {
            sendJson(200, ['post' => $post]);
        } else {
            sendNotFound();
        }
    }
    // Admin API endpoints
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $request_path === '/api/admin/users') {
        if (!$user || $user['role'] !== 'admin') {
            sendJson(403, ['error' => 'Forbidden']);
        }
        $users = readJsonFile(USERS_FILE, []);
        sendJson(200, ['users' => $users]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $request_path === '/api/admin/site-settings') {
        if (!$user || $user['role'] !== 'admin') {
            sendJson(403, ['error' => 'Forbidden']);
        }
        $settings = readJsonFile(SITE_SETTINGS_FILE, []);
        sendJson(200, ['settings' => $settings]);
    }
    // Support tickets
    elseif ($request_path === '/api/support/tickets') {
        if (!$user) {
            sendJson(401, ['error' => 'Unauthorized']);
        }
        $tickets = readJsonFile(TICKETS_FILE, []);
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            sendJson(200, ['tickets' => $tickets]);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $request_body = json_decode(file_get_contents('php://input'), true);
            $new_ticket = [
                'id' => uniqid('ticket_', true),
                'subject' => isset($request_body['subject']) ? $request_body['subject'] : '',
                'description' => isset($request_body['description']) ? $request_body['description'] : '',
                'status' => 'open',
                'created_at' => date('c'),
                'user_id' => $user['id'],
            ];
            $tickets[] = $new_ticket;
            writeJsonFile(TICKETS_FILE, $tickets);
            sendJson(201, ['ticket' => $new_ticket]);
        }
    }
    // WhatsApp leads
    elseif ($request_path === '/api/leads/whatsapp') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $request_body = json_decode(file_get_contents('php://input'), true);
            $new_lead = [
                'name' => isset($request_body['name']) ? $request_body['name'] : '',
                'phone' => isset($request_body['phone']) ? $request_body['phone'] : '',
                'city' => isset($request_body['city']) ? $request_body['city'] : '',
                'project_type' => isset($request_body['project_type']) ? $request_body['project_type'] : '',
                'created_at' => date('c'),
            ];
            $leads = readJsonFile(LEADS_FILE, []);
            $leads[] = $new_lead;
            writeJsonFile(LEADS_FILE, $leads);
            sendJson(200, ['message' => 'Lead received.']);
        }
    }
    else {
        sendNotFound();
    }
}


// --- Main Router ---

$request_uri = $_SERVER['REQUEST_URI'];
$request_path = strtok($request_uri, '?');

if (preg_match('/^\/api\//', $request_path)) {
    handleApiRequest($request_path);
    exit;
}

// Route for the admin dashboard
if (preg_match('/^\/admin(\/.*)?$/', $request_path, $matches)) {
    $admin_path = isset($matches[1]) ? $matches[1] : '/';
    $file_path = __DIR__ . '/admin' . ($admin_path === '/' ? '/index.php' : $admin_path);

    if (file_exists($file_path) && is_file($file_path)) {
        setSecurityHeaders();
        header('Content-Type: ' . getContentType($file_path));
        // If it's a PHP file, include it to execute it.
        if (pathinfo($file_path, PATHINFO_EXTENSION) === 'php') {
            include $file_path;
        } else {
            readfile($file_path);
        }
        exit;
    }
}

$file_path = __DIR__ . $request_path;
if ($request_path === '/' || $request_path === '') {
    $file_path = __DIR__ . '/index.html';
}
if (pathinfo($file_path, PATHINFO_EXTENSION) == '') {
    $file_path .= '.html';
}

if (file_exists($file_path) && is_file($file_path)) {
    setSecurityHeaders();
    header('Content-Type: ' . getContentType($file_path));
    readfile($file_path);
    exit;
}

sendNotFound();
