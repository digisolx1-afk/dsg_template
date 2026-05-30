<?php

namespace App\Controllers;

/**
 * AuthMiddleware - Ensures authenticated users can access routes
 * Redirects unauthenticated users to login
 */
class AuthMiddleware extends Middleware
{
    private $next;

    public function __construct($next = null)
    {
        $this->next = $next;
    }

    /**
     * Handle the request - redirect if not authenticated
     */
    public function handle()
    {
        if (!$this->isAuthenticated()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }

        return $this->next();
    }

    /**
     * Get next middleware/controller
     */
    public function next()
    {
        if (is_callable($this->next)) {
            return call_user_func($this->next);
        }
        return $this->next;
    }
}
