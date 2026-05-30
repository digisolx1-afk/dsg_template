<?php

namespace App\Controllers;

/**
 * Middleware - Base middleware class for request handling
 * Handles common middleware operations
 */
abstract class Middleware
{
    /**
     * Handle the request
     */
    abstract public function handle();

    /**
     * Get next middleware or controller
     */
    abstract public function next();

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user from session
     */
    protected function getCurrentUser()
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Get current user role
     */
    protected function getUserRole()
    {
        return $_SESSION['user']['role'] ?? null;
    }

    /**
     * Check if user has specific role
     */
    protected function hasRole($role)
    {
        if (is_array($role)) {
            return in_array($this->getUserRole(), $role);
        }
        return $this->getUserRole() === $role;
    }

    /**
     * Abort with error
     */
    protected function abort($code = 403, $message = 'Forbidden')
    {
        http_response_code($code);
        die(view('error', [
            'code' => $code,
            'message' => $message
        ]));
    }
}
