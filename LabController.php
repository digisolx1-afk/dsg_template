<?php

namespace App\Controllers;

use App\Models\LabTest;
use App\Models\LabBooking;
use App\Models\Payment;
use App\Helpers\Response;
use App\Helpers\Validator;

/**
 * LabController - Handles lab test booking and management
 * Features: Search tests, book tests, payment handling, home collection
 */
class LabController
{
    private $labTestModel;
    private $labBookingModel;
    private $paymentModel;

    public function __construct()
    {
        $this->labTestModel = new LabTest();
        $this->labBookingModel = new LabBooking();
        $this->paymentModel = new Payment();
    }

    /**
     * Display lab tests listing page with search
     * GET /labs or /lab/tests
     */
    public function index()
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? null;
            $category = $_GET['category'] ?? null;
            $perPage = 20; // 20 tests per page as per requirement

            $filters = [
                'search' => $search,
                'category' => $category,
                'is_active' => 1
            ];

            // Get total count
            $total = $this->labTestModel->countTests($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            // Get tests
            $tests = $this->labTestModel->searchTests($filters, limit: $perPage, offset: $offset);

            // Get categories
            $categories = $this->labTestModel->getCategories();

            return view('pages/labs/index', [
                'tests' => $tests,
                'categories' => $categories,
                'currentCategory' => $category,
                'searchQuery' => $search,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => 'Lab Tests | Digital Sehat Ghar'
            ]);
        } catch (\Exception $e) {
            logger()->error('Lab index error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load lab tests',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get lab tests by category
     * GET /lab/category/{category}
     */
    public function byCategory($category)
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? null;
            $perPage = 20;

            // Verify category exists
            $categoryData = $this->labTestModel->getCategoryByName($category);
            if (!$categoryData) {
                return view('error', [
                    'message' => 'Category not found',
                    'code' => 404
                ]);
            }

            $filters = [
                'category' => $category,
                'search' => $search,
                'is_active' => 1
            ];

            $total = $this->labTestModel->countTests($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            $tests = $this->labTestModel->searchTests($filters, limit: $perPage, offset: $offset);
            $categories = $this->labTestModel->getCategories();

            return view('pages/labs/category', [
                'category' => $categoryData,
                'tests' => $tests,
                'categories' => $categories,
                'searchQuery' => $search,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => $category . ' Tests'
            ]);
        } catch (\Exception $e) {
            logger()->error('Lab category error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load tests',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * View test detail
     * GET /lab/test/{id}
     */
    public function testDetail($testId)
    {
        try {
            $test = $this->labTestModel->getById($testId);

            if (!$test) {
                return view('error', [
                    'message' => 'Test not found',
                    'code' => 404
                ]);
            }

            // Get related tests
            $related = $this->labTestModel->getRelatedTests($test['category'], exclude_id: $testId, limit: 4);

            return view('pages/labs/detail', [
                'test' => $test,
                'related' => $related,
                'title' => $test['test_name'] . ' Test'
            ]);
        } catch (\Exception $e) {
            logger()->error('Lab test detail error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load test details',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Search lab tests with advanced filters
     * GET /api/lab/tests/search
     */
    public function search()
    {
        try {
            $search = $_GET['q'] ?? null;
            $category = $_GET['category'] ?? null;
            $maxPrice = (float)($_GET['max_price'] ?? null);
            $homeCollection = $_GET['home_collection'] ?? null;
            $page = (int)($_GET['page'] ?? 1);
            $perPage = 20;

            $filters = [
                'search' => $search,
                'category' => $category,
                'max_price' => $maxPrice,
                'home_collection_available' => $homeCollection === 'yes' ? 1 : null,
                'is_active' => 1
            ];

            $total = $this->labTestModel->countTests($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            $tests = $this->labTestModel->searchTests($filters, limit: $perPage, offset: $offset);

            return Response::success('Tests retrieved', 200, [
                'tests' => $tests,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            logger()->error('Lab search error: ' . $e->getMessage());
            return Response::error('Unable to search tests', 500);
        }
    }

    /**
     * Create lab booking
     * POST /api/lab/booking
     */
    public function createBooking()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return Response::error('Invalid request method', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            // Validation
            $validator = new Validator();
            $validated = $validator->validate($input, [
                'test_ids' => 'required|array',
                'patient_name' => 'required|string|min:3',
                'patient_email' => 'required|email',
                'patient_phone' => 'required|phone',
                'collection_address' => 'required|string|min:10',
                'collection_city' => 'required|string',
                'preferred_date' => 'required|date_format:Y-m-d',
                'preferred_time_slot' => 'string',
                'special_instructions' => 'string'
            ]);

            if (!$validated['valid']) {
                return Response::error('Validation failed', 422, $validated['errors']);
            }

            $data = $validated['data'];

            // Verify test IDs and calculate total
            $tests = $this->labTestModel->getByIds($data['test_ids']);
            if (count($tests) !== count($data['test_ids'])) {
                return Response::error('One or more tests not found', 404);
            }

            $totalAmount = array_sum(array_column($tests, 'price'));

            // Check if date is valid (at least 1 day from now)
            $preferredDate = strtotime($data['preferred_date']);
            $tomorrow = strtotime(date('Y-m-d', strtotime('+1 day')));

            if ($preferredDate < $tomorrow) {
                return Response::error('Please select a date at least 1 day from now', 422);
            }

            // Generate booking number and ref code
            $bookingNumber = $this->generateBookingNumber();
            $refCode = $this->generatePaymentRefCode();

            // Create booking
            $bookingData = [
                'booking_number' => $bookingNumber,
                'patient_name' => $data['patient_name'],
                'patient_email' => $data['patient_email'],
                'patient_phone' => $data['patient_phone'],
                'test_ids_json' => json_encode($tests),
                'total_amount' => $totalAmount,
                'collection_address' => $data['collection_address'],
                'collection_city' => $data['collection_city'],
                'preferred_date' => $data['preferred_date'],
                'preferred_time_slot' => $data['preferred_time_slot'] ?? null,
                'special_instructions' => $data['special_instructions'] ?? null,
                'payment_ref_code' => $refCode,
                'status' => 'pending_payment'
            ];

            $bookingId = $this->labBookingModel->create($bookingData);

            if (!$bookingId) {
                return Response::error('Failed to create booking', 500);
            }

            // Create payment record
            $paymentData = [
                'ref_code' => $refCode,
                'order_type' => 'lab_test',
                'order_id' => $bookingId,
                'user_id' => 0, // Guest user
                'amount' => $totalAmount,
                'payment_method' => 'manual',
                'status' => 'pending'
            ];

            $this->paymentModel->create($paymentData);

            logger()->info('Lab booking created', [
                'booking_id' => $bookingId,
                'ref_code' => $refCode
            ]);

            return Response::success('Lab booking created successfully', 201, [
                'booking_id' => $bookingId,
                'booking_number' => $bookingNumber,
                'ref_code' => $refCode,
                'amount' => $totalAmount,
                'tests' => $tests,
                'payment_methods' => [
                    'jazzcash' => '03120922709',
                    'easypaisa' => '03120922709',
                    'upaisa' => '03120922709',
                    'account_title' => 'Rizwanullah'
                ]
            ]);
        } catch (\Exception $e) {
            logger()->error('Create lab booking error: ' . $e->getMessage());
            return Response::error('Unable to create booking', 500);
        }
    }

    /**
     * Verify lab booking payment
     * POST /api/lab/booking/verify-payment
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

            if (!$refCode || !$transactionId || !$paymentMethod) {
                return Response::error('Missing required fields', 422);
            }

            if (!in_array($paymentMethod, ['jazzcash', 'easypaisa', 'upaisa'])) {
                return Response::error('Invalid payment method', 422);
            }

            $payment = $this->paymentModel->getByRefCode($refCode);
            if (!$payment) {
                return Response::error('Payment record not found', 404);
            }

            if ($payment['status'] !== 'pending') {
                return Response::error('Payment already verified or rejected', 422);
            }

            // Handle file upload
            $filePath = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $filePath = $this->handlePaymentProofUpload($_FILES['proof_file']);
                if (!$filePath) {
                    return Response::error('Invalid file upload', 422);
                }
            }

            // Update payment
            $updateData = [
                'transaction_id_user' => $transactionId,
                'payment_method' => $paymentMethod,
                'proof_file_path' => $filePath,
                'proof_uploaded_at' => date('Y-m-d H:i:s'),
                'status' => 'pending'
            ];

            $this->paymentModel->update($payment['id'], $updateData);

            logger()->info('Lab payment proof uploaded', [
                'ref_code' => $refCode
            ]);

            return Response::success('Payment proof received. Admin will verify within 2 hours.', 200, [
                'ref_code' => $refCode,
                'status' => 'pending_verification'
            ]);
        } catch (\Exception $e) {
            logger()->error('Lab verify payment error: ' . $e->getMessage());
            return Response::error('Unable to upload payment proof', 500);
        }
    }

    /**
     * Get lab booking details
     * GET /api/lab/booking/{ref_code}
     */
    public function getBooking($refCode)
    {
        try {
            $payment = $this->paymentModel->getByRefCode($refCode);

            if (!$payment) {
                return Response::error('Booking not found', 404);
            }

            $booking = $this->labBookingModel->getById($payment['order_id']);

            if (!$booking) {
                return Response::error('Booking not found', 404);
            }

            return Response::success('Booking retrieved', 200, [
                'booking' => $booking,
                'payment_status' => $payment['status']
            ]);
        } catch (\Exception $e) {
            logger()->error('Get lab booking error: ' . $e->getMessage());
            return Response::error('Unable to get booking details', 500);
        }
    }

    /**
     * Cancel lab booking
     * POST /api/lab/booking/{ref_code}/cancel
     */
    public function cancelBooking($refCode)
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return Response::error('Invalid request method', 405);
            }

            $payment = $this->paymentModel->getByRefCode($refCode);
            if (!$payment) {
                return Response::error('Booking not found', 404);
            }

            $booking = $this->labBookingModel->getById($payment['order_id']);
            if (!$booking) {
                return Response::error('Booking not found', 404);
            }

            // Only allow cancellation if payment not verified
            if ($payment['status'] === 'verified') {
                return Response::error('Cannot cancel verified bookings. Contact admin.', 422);
            }

            $reason = $_POST['reason'] ?? 'No reason provided';

            $this->labBookingModel->update($booking['id'], [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s')
            ]);

            logger()->info('Lab booking cancelled', [
                'booking_id' => $booking['id']
            ]);

            return Response::success('Booking cancelled successfully', 200);
        } catch (\Exception $e) {
            logger()->error('Cancel lab booking error: ' . $e->getMessage());
            return Response::error('Unable to cancel booking', 500);
        }
    }

    /**
     * Helper: Generate unique booking number
     */
    private function generateBookingNumber()
    {
        return 'LAB-' . date('Ymd') . '-' . strtoupper(substr(md5(rand()), 0, 6));
    }

    /**
     * Helper: Generate unique payment reference code
     */
    private function generatePaymentRefCode()
    {
        do {
            $refCode = 'DSG-' . date('Ymd') . '-' . strtoupper(substr(md5(rand()), 0, 6));
        } while ($this->paymentModel->getByRefCode($refCode));

        return $refCode;
    }

    /**
     * Helper: Handle payment proof file upload
     */
    private function handlePaymentProofUpload($file)
    {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxSize = 5 * 1024 * 1024;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed) || $file['size'] > $maxSize) {
            return false;
        }

        $filename = 'lab_payment_' . uniqid() . '.' . $ext;
        $uploadDir = BASE_PATH . '/public/assets/uploads/payment-proofs/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return false;
        }

        return '/assets/uploads/payment-proofs/' . $filename;
    }
}
