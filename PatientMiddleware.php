<?php

namespace App\Controllers;

/**
 * PatientMiddleware - Ensures only authenticated patients can access routes
 * Redirects non-patients or unauthenticated users
 */
class PatientMiddleware extends Middleware
{
    private $next;

    public function __construct($next = null)
    {
        $this->next = $next;
    }

    /**
     * Handle the request - check if user is authenticated patient
     */
    public function handle()
    {
        if (!$this->isAuthenticated()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }

        if (!$this->hasRole('patient')) {
            $this->abort(403, 'Patient access only');
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
