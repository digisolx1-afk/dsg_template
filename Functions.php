<?php
/**
 * Global Helper Functions
 */

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Get database connection
function db() {
    return Database::connect();
}

// Redirect
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// JSON response
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Format currency
function format_price($price) {
    return 'Rs. ' . number_format($price, 2);
}

// Get logged-in user
function get_user() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user']);
}

?>
