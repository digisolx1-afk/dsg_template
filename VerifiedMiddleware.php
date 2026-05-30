<?php

namespace App\Controllers;

use App\Models\User;

/**
 * VerifiedMiddleware - Ensures verified access based on user verification status
 * Checks: email verification, phone verification, doctor profile verification
 */
class VerifiedMiddleware extends Middleware
{
    private $next;
    private $userModel;

    public function __construct($next = null)
    {
        $this->next = $next;
        $this->userModel = new User();
    }

    /**
     * Handle the request - verify user status
     */
    public function handle()
    {
        // Must be authenticated first
        if (!$this->isAuthenticated()) {
            redirect('/login');
        }

        $user = $this->getCurrentUser();
        $role = $user['role'] ?? null;

        // Get fresh user data from database
        $dbUser = $this->userModel->getById($user['id'] ?? 0);

        if (!$dbUser) {
            session_destroy();
            redirect('/login');
        }

        // Check email verification
        if (!$dbUser['email_verified']) {
            logger()->warning('User accessed verified route with unverified email', [
                'user_id' => $dbUser['id'],
                'email' => $dbUser['email']
            ]);
            redirect('/verify-email?message=' . urlencode('Please verify your email first'));
        }

        // Check phone verification
        if (!$dbUser['phone_verified']) {
            logger()->warning('User accessed verified route with unverified phone', [
                'user_id' => $dbUser['id'],
                'phone' => $dbUser['phone']
            ]);
            redirect('/verify-phone?message=' . urlencode('Please verify your phone number first'));
        }

        // For doctors, check doctor profile verification
        if ($role === 'doctor') {
            // Get doctor profile
            $doctorModel = app('doctor');
            $doctor = $doctorModel->getByUserId($dbUser['id']);

            if (!$doctor) {
                logger()->warning('Doctor profile not found', [
                    'user_id' => $dbUser['id']
                ]);
                redirect('/doctor/setup?message=' . urlencode('Please complete your doctor profile'));
            }

            if (!$doctor['is_verified']) {
                logger()->warning('Doctor attempted to access verified route before profile verification', [
                    'user_id' => $dbUser['id'],
                    'doctor_id' => $doctor['id']
                ]);
                redirect('/doctor/verification-pending?message=' . urlencode('Your doctor profile is pending admin verification'));
            }

            // Update session with doctor info
            $_SESSION['doctor'] = [
                'id' => $doctor['id'],
                'pmdc_number' => $doctor['pmdc_number'],
                'specialty' => $doctor['specialty'],
                'verified_at' => $doctor['verified_at']
            ];
        }

        // For patients, ensure patient profile exists
        if ($role === 'patient') {
            $patientModel = app('patient');
            $patient = $patientModel->getByUserId($dbUser['id']);

            if (!$patient) {
                // Create basic patient profile if not exists
                $patientModel->create([
                    'user_id' => $dbUser['id'],
                    'gender' => null,
                    'date_of_birth' => null
                ]);
            }

            $_SESSION['patient'] = [
                'id' => $patient['id'] ?? null
            ];
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

    /**
     * Check if specific verification is complete
     */
    public static function isEmailVerified()
    {
        return isset($_SESSION['user']['email_verified']) && $_SESSION['user']['email_verified'];
    }

    /**
     * Check if phone verification is complete
     */
    public static function isPhoneVerified()
    {
        return isset($_SESSION['user']['phone_verified']) && $_SESSION['user']['phone_verified'];
    }

    /**
     * Check if doctor profile is verified (for doctors only)
     */
    public static function isDoctorVerified()
    {
        if (($_SESSION['user']['role'] ?? null) !== 'doctor') {
            return false;
        }

        return isset($_SESSION['doctor']['verified_at']);
    }

    /**
     * Check if user account is active
     */
    public static function isAccountActive()
    {
        return isset($_SESSION['user']['is_active']) && $_SESSION['user']['is_active'];
    }
}
