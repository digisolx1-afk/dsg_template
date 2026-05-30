<?php

namespace App\Controllers;

/**
 * GuestMiddleware - Ensures only non-authenticated users can access routes
 * Redirects authenticated users away
 */
class GuestMiddleware extends Middleware
{
    private $next;

    public function __construct($next = null)
    {
        $this->next = $next;
    }

    /**
     * Handle the request - redirect if already authenticated
     */
    public function handle()
    {
        if ($this->isAuthenticated()) {
            // Redirect based on user role
            $role = $this->getUserRole();

            switch ($role) {
                case 'patient':
                    redirect('/patient/dashboard');
                    break;
                case 'doctor':
                    redirect('/doctor/dashboard');
                    break;
                case 'super_admin':
                case 'sub_admin':
                    redirect('/admin/dashboard');
                    break;
                default:
                    redirect('/');
            }
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
