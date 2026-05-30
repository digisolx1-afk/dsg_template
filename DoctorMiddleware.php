<?php

namespace App\Controllers;

/**
 * DoctorMiddleware - Ensures only authenticated doctors can access routes
 * Redirects non-doctors or unauthenticated users
 */
class DoctorMiddleware extends Middleware
{
    private $next;

    public function __construct($next = null)
    {
        $this->next = $next;
    }

    /**
     * Handle the request - check if user is authenticated doctor
     */
    public function handle()
    {
        if (!$this->isAuthenticated()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }

        if (!$this->hasRole('doctor')) {
            $this->abort(403, 'Doctor access only');
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
