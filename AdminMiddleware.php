<?php

namespace App\Controllers;

/**
 * AdminMiddleware - Ensures only authenticated admins can access routes
 * Supports both super_admin and sub_admin roles
 */
class AdminMiddleware extends Middleware
{
    private $next;

    public function __construct($next = null)
    {
        $this->next = $next;
    }

    /**
     * Handle the request - check if user is authenticated admin
     */
    public function handle()
    {
        if (!$this->isAuthenticated()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            redirect('/admin/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }

        $role = $this->getUserRole();
        if (!in_array($role, ['super_admin', 'sub_admin'])) {
            $this->abort(403, 'Admin access only');
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
