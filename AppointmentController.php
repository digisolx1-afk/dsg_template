<?php

namespace App\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\TimeSlot;
use App\Helpers\Response;
use App\Helpers\Validator;

/**
 * AppointmentController - Handles appointment booking and management
 * Features: Book appointments, manage bookings, payment handling, Google Meet integration
 */
class AppointmentController
{
    private $appointmentModel;
    private $doctorModel;
    private $paymentModel;
    private $timeSlotModel;

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
        $this->doctorModel = new Doctor();
        $this->paymentModel = new Payment();
        $this->timeSlotModel = new TimeSlot();
    }

    /**
     * Display appointment booking page
     * GET /appointment/book/{doctor_id}
     */
    public function bookingPage($doctorId)
    {
        try {
            $doctor = $this->doctorModel->getDoctorById($doctorId);

            if (!$doctor) {
                return view('error', [
                    'message' => 'Doctor not found',
                    'code' => 404
                ]);
            }

            return view('pages/appointments/book', [
                'doctor' => $doctor,
                'title' => 'Book Appointment with ' . $doctor['full_name']
            ]);
        } catch (\Exception $e) {
            logger()->error('Booking page error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load booking page',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get available time slots for a doctor
     * GET /api/appointment/slots
     * Parameters: doctor_id, date, location_type (clinic/video)
     */
    public function getTimeSlots()
    {
        try {
            $doctorId = (int)($_GET['doctor_id'] ?? 0);
            $date = $_GET['date'] ?? null;
            $locationType = $_GET['location_type'] ?? 'both'; // clinic, video, both

            // Validation
            if (!$doctorId) {
                return Response::error('Doctor ID required', 422);
            }

            if (!$date || !$this->isValidDate($date)) {
                return Response::error('Valid date required (Y-m-d)', 422);
            }

            // Check if date is not in the past
            if (strtotime($date) < strtotime(date('Y-m-d'))) {
                return Response::error('Cannot book for past dates', 422);
            }

            // Check if date is within 30 days
            if (strtotime($date) > strtotime(date('Y-m-d', strtotime('+30 days')))) {
                return Response::error('Can only book appointments within 30 days', 422);
            }

            $doctor = $this->doctorModel->getDoctorById($doctorId);
            if (!$doctor) {
                return Response::error('Doctor not found', 404);
            }

            // Get available slots
            $slots = $this->timeSlotModel->getAvailableSlots(
                $doctorId,
                $date,
                $locationType
            );

            return Response::success('Available slots retrieved', 200, [
                'slots' => $slots,
                'date' => $date,
                'doctor' => [
                    'id' => $doctor['id'],
                    'name' => $doctor['full_name'],
                    'fees' => [
                        'clinic' => $doctor['consultation_fee'],
                        'video' => $doctor['video_consultation_fee']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            logger()->error('Get time slots error: ' . $e->getMessage());
            return Response::error('Unable to get available slots', 500);
        }
    }

    /**
     * Create a new appointment (requires authentication for registered users)
     * POST /api/appointment/create
     */
    public function create()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return Response::error('Invalid request method', 405);
            }

            // Get input
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            // Validation
            $validator = new Validator();
            $validated = $validator->validate($input, [
                'doctor_id' => 'required|integer',
                'appointment_date' => 'required|date_format:Y-m-d',
                'time_slot' => 'required|string',
                'location_type' => 'required|in:clinic,video',
                'consultation_type' => 'string',
                'patient_name' => 'required|string|min:3',
                'patient_email' => 'required|email',
                'patient_phone' => 'required|phone',
                'symptoms' => 'string'
            ]);

            if (!$validated['valid']) {
                return Response::error('Validation failed', 422, $validated['errors']);
            }

            $data = $validated['data'];

            // Verify doctor
            $doctor = $this->doctorModel->getDoctorById($data['doctor_id']);
            if (!$doctor) {
                return Response::error('Doctor not found', 404);
            }

            // Check location availability
            if ($data['location_type'] === 'video' && !$doctor['is_video_available']) {
                return Response::error('Video consultation not available for this doctor', 422);
            }

            if ($data['location_type'] === 'clinic' && !$doctor['is_clinic_available']) {
                return Response::error('Clinic consultation not available for this doctor', 422);
            }

            // Verify time slot availability
            $isSlotAvailable = $this->timeSlotModel->isSlotAvailable(
                $data['doctor_id'],
                $data['appointment_date'],
                $data['time_slot'],
                $data['location_type']
            );

            if (!$isSlotAvailable) {
                return Response::error('Selected time slot is not available', 422);
            }

            // Generate payment reference code
            $refCode = $this->generatePaymentRefCode();

            // Determine fees
            $fees = $data['location_type'] === 'video'
                ? $doctor['video_consultation_fee']
                : $doctor['consultation_fee'];

            // Create appointment
            $appointmentData = [
                'patient_name' => $data['patient_name'],
                'patient_email' => $data['patient_email'],
                'patient_phone' => $data['patient_phone'],
                'doctor_id' => $data['doctor_id'],
                'appointment_date' => $data['appointment_date'],
                'time_slot' => $data['time_slot'],
                'location_type' => $data['location_type'],
                'consultation_type' => $data['consultation_type'] ?? 'General',
                'fees_amount' => $fees,
                'payment_ref_code' => $refCode,
                'patient_symptoms' => $data['symptoms'] ?? null,
                'status' => 'pending_payment'
            ];

            $appointmentId = $this->appointmentModel->create($appointmentData);

            if (!$appointmentId) {
                return Response::error('Failed to create appointment', 500);
            }

            // Create payment record
            $paymentData = [
                'ref_code' => $refCode,
                'order_type' => 'appointment',
                'order_id' => $appointmentId,
                'user_id' => 0, // Guest user for now
                'amount' => $fees,
                'payment_method' => 'manual',
                'status' => 'pending'
            ];

            $this->paymentModel->create($paymentData);

            logger()->info('Appointment created', [
                'appointment_id' => $appointmentId,
                'ref_code' => $refCode
            ]);

            return Response::success('Appointment created successfully', 201, [
                'appointment_id' => $appointmentId,
                'ref_code' => $refCode,
                'amount' => $fees,
                'payment_methods' => [
                    'jazzcash' => '03120922709',
                    'easypaisa' => '03120922709',
                    'upaisa' => '03120922709',
                    'account_title' => 'Rizwanullah'
                ]
            ]);
        } catch (\Exception $e) {
            logger()->error('Create appointment error: ' . $e->getMessage());
            return Response::error('Unable to create appointment', 500);
        }
    }

    /**
     * Upload payment proof and transaction details
     * POST /api/appointment/verify-payment
     */
    public function verifyPayment()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return Response::error('Invalid request method', 405);
            }

            $refCode = $_POST['ref_code'] ?? null;
            $transactionId = $_POST['transaction_id'] ?? null;
            $paymentMethod = $_POST['payment_method'] ?? null;
            $transactionDate = $_POST['transaction_date'] ?? null;

            // Validation
            if (!$refCode || !$transactionId || !$paymentMethod) {
                return Response::error('Missing required fields', 422);
            }

            if (!in_array($paymentMethod, ['jazzcash', 'easypaisa', 'upaisa'])) {
                return Response::error('Invalid payment method', 422);
            }

            // Get payment
            $payment = $this->paymentModel->getByRefCode($refCode);
            if (!$payment) {
                return Response::error('Payment record not found', 404);
            }

            if ($payment['status'] !== 'pending') {
                return Response::error('Payment already verified or rejected', 422);
            }

            // Handle file upload if present
            $filePath = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $filePath = $this->handlePaymentProofUpload($_FILES['proof_file']);
                if (!$filePath) {
                    return Response::error('Invalid file upload', 422);
                }
            }

            // Update payment with verification details
            $updateData = [
                'transaction_id_user' => $transactionId,
                'payment_method' => $paymentMethod,
                'proof_file_path' => $filePath,
                'proof_uploaded_at' => date('Y-m-d H:i:s'),
                'status' => 'pending' // Stays pending until admin verifies
            ];

            $updated = $this->paymentModel->update($payment['id'], $updateData);

            if (!$updated) {
                return Response::error('Failed to update payment', 500);
            }

            logger()->info('Payment proof uploaded', [
                'ref_code' => $refCode,
                'transaction_id' => $transactionId
            ]);

            return Response::success('Payment details received. Admin will verify within 2 hours.', 200, [
                'ref_code' => $refCode,
                'status' => 'pending_verification'
            ]);
        } catch (\Exception $e) {
            logger()->error('Verify payment error: ' . $e->getMessage());
            return Response::error('Unable to upload payment proof', 500);
        }
    }

    /**
     * Get appointment details (for registered users or with ref code)
     * GET /api/appointment/{id}
     */
    public function getDetail($appointmentId)
    {
        try {
            $appointment = $this->appointmentModel->getById($appointmentId);

            if (!$appointment) {
                return Response::error('Appointment not found', 404);
            }

            // Get doctor details
            $doctor = $this->doctorModel->getDoctorById($appointment['doctor_id']);

            return Response::success('Appointment retrieved', 200, [
                'appointment' => $appointment,
                'doctor' => $doctor,
                'googleMeetLink' => $appointment['google_meet_link'] ?? null
            ]);
        } catch (\Exception $e) {
            logger()->error('Get appointment detail error: ' . $e->getMessage());
            return Response::error('Unable to get appointment details', 500);
        }
    }

    /**
     * Cancel appointment
     * POST /api/appointment/{id}/cancel
     */
    public function cancel($appointmentId)
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return Response::error('Invalid request method', 405);
            }

            $appointment = $this->appointmentModel->getById($appointmentId);

            if (!$appointment) {
                return Response::error('Appointment not found', 404);
            }

            // Check if can be cancelled
            if (!in_array($appointment['status'], ['pending_payment', 'payment_verified', 'confirmed'])) {
                return Response::error('This appointment cannot be cancelled', 422);
            }

            $reason = $_POST['cancellation_reason'] ?? 'No reason provided';

            $updated = $this->appointmentModel->update($appointmentId, [
                'status' => 'cancelled',
                'cancelled_by' => 'patient',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $reason
            ]);

            if (!$updated) {
                return Response::error('Failed to cancel appointment', 500);
            }

            logger()->info('Appointment cancelled', [
                'appointment_id' => $appointmentId
            ]);

            return Response::success('Appointment cancelled successfully', 200);
        } catch (\Exception $e) {
            logger()->error('Cancel appointment error: ' . $e->getMessage());
            return Response::error('Unable to cancel appointment', 500);
        }
    }

    /**
     * Get appointment status
     * GET /api/appointment/{ref_code}/status
     */
    public function getStatus($refCode)
    {
        try {
            $payment = $this->paymentModel->getByRefCode($refCode);

            if (!$payment) {
                return Response::error('Payment not found', 404);
            }

            $appointment = $this->appointmentModel->getById($payment['order_id']);

            if (!$appointment) {
                return Response::error('Appointment not found', 404);
            }

            return Response::success('Status retrieved', 200, [
                'appointment_id' => $appointment['id'],
                'ref_code' => $refCode,
                'status' => $appointment['status'],
                'payment_status' => $payment['status'],
                'google_meet_link' => $appointment['google_meet_link'] ?? null
            ]);
        } catch (\Exception $e) {
            logger()->error('Get status error: ' . $e->getMessage());
            return Response::error('Unable to get appointment status', 500);
        }
    }

    /**
     * Helper: Generate unique payment reference code
     */
    private function generatePaymentRefCode()
    {
        do {
            $refCode = 'DSG-' . date('Ymd') . '-' . strtoupper(substr(md5(rand()), 0, 6));
        } while ($this->paymentModel->getByRefCode($refCode)); // Ensure unique

        return $refCode;
    }

    /**
     * Helper: Handle payment proof file upload
     */
    private function handlePaymentProofUpload($file)
    {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Validate file
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            return false;
        }

        if ($file['size'] > $maxSize) {
            return false;
        }

        // Generate unique filename
        $filename = 'payment_' . uniqid() . '.' . $ext;
        $uploadDir = BASE_PATH . '/public/assets/uploads/payment-proofs/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filePath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return false;
        }

        return '/assets/uploads/payment-proofs/' . $filename;
    }

    /**
     * Helper: Validate date format
     */
    private function isValidDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
