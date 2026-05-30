<?php

namespace App\Controllers;

use App\Models\Payment;
use App\Models\Appointment;
use App\Models\LabBooking;
use App\Models\PharmacyOrder;
use App\Helpers\Response;

/**
 * PaymentController - Handles payment verification and processing
 * Features: Payment status checks, manual payment verification, payment tracking
 */
class PaymentController
{
    private $paymentModel;
    private $appointmentModel;
    private $labBookingModel;
    private $pharmacyOrderModel;

    public function __construct()
    {
        $this->paymentModel = new Payment();
        $this->appointmentModel = new Appointment();
        $this->labBookingModel = new LabBooking();
        $this->pharmacyOrderModel = new PharmacyOrder();
    }

    /**
     * Display payment status/tracking page
     * GET /payment/status/{ref_code}
     */
    public function status($refCode)
    {
        try {
            $payment = $this->paymentModel->getByRefCode($refCode);

            if (!$payment) {
                return view('error', [
                    'message' => 'Payment not found',
                    'code' => 404
                ]);
            }

            // Get related order details
            $orderDetails = $this->getOrderDetails($payment['order_type'], $payment['order_id']);

            return view('pages/payment/status', [
                'payment' => $payment,
                'orderType' => $payment['order_type'],
                'orderDetails' => $orderDetails,
                'title' => 'Payment Status'
            ]);
        } catch (\Exception $e) {
            logger()->error('Payment status page error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load payment status',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get payment status via API
     * GET /api/payment/{ref_code}/status
     */
    public function getStatus($refCode)
    {
        try {
            $payment = $this->paymentModel->getByRefCode($refCode);

            if (!$payment) {
                return Response::error('Payment not found', 404);
            }

            // Get order details
            $orderDetails = $this->getOrderDetails($payment['order_type'], $payment['order_id']);

            return Response::success('Payment status retrieved', 200, [
                'ref_code' => $refCode,
                'status' => $payment['status'],
                'amount' => $payment['amount'],
                'payment_method' => $payment['payment_method'],
                'order_type' => $payment['order_type'],
                'created_at' => $payment['created_at'],
                'verified_at' => $payment['verified_at'],
                'order_details' => $orderDetails
            ]);
        } catch (\Exception $e) {
            logger()->error('Get payment status error: ' . $e->getMessage());
            return Response::error('Unable to get payment status', 500);
        }
    }

    /**
     * List payment methods and instructions
     * GET /payment/methods
     */
    public function methods()
    {
        return view('pages/payment/methods', [
            'paymentMethods' => [
                'jazzcash' => [
                    'name' => 'JazzCash',
                    'number' => '03120922709',
                    'instructions' => [
                        'Dial *141# or download JazzCash app',
                        'Select "Send to JazzCash Account"',
                        'Enter account name: Rizwanullah',
                        'Enter amount and confirm',
                        'Note down the confirmation code',
                        'Provide the code during payment verification'
                    ]
                ],
                'easypaisa' => [
                    'name' => 'EasyPaisa',
                    'number' => '03120922709',
                    'instructions' => [
                        'Visit nearest EasyPaisa agent or visit easypaisaapp.com',
                        'Select "Send to EasyPaisa Account"',
                        'Enter account name: Rizwanullah',
                        'Enter amount',
                        'Collect receipt with transaction ID',
                        'Provide transaction ID during verification'
                    ]
                ],
                'upaisa' => [
                    'name' => 'U Paisa',
                    'number' => '03120922709',
                    'instructions' => [
                        'Dial *7878# or download U Paisa app',
                        'Select "Send to Merchant"',
                        'Enter account: Rizwanullah',
                        'Enter amount and confirm',
                        'Note the confirmation number',
                        'Provide confirmation during verification'
                    ]
                ]
            ],
            'title' => 'Payment Methods | Digital Sehat Ghar'
        ]);
    }

    /**
     * Get payment instructions for a specific method
     * GET /api/payment/methods/{method}
     */
    public function getMethodInstructions($method)
    {
        try {
            $validMethods = ['jazzcash', 'easypaisa', 'upaisa'];

            if (!in_array($method, $validMethods)) {
                return Response::error('Invalid payment method', 422);
            }

            $instructions = [
                'jazzcash' => [
                    'name' => 'JazzCash',
                    'account_number' => '03120922709',
                    'account_title' => 'Rizwanullah',
                    'steps' => [
                        'Dial *141# from any Jazz number',
                        'Select "Send to JazzCash Account"',
                        'Enter account title: Rizwanullah',
                        'Enter the payment amount',
                        'Confirm the transaction',
                        'Save the confirmation code'
                    ],
                    'note' => 'You will receive a confirmation code via SMS. Keep it safe for verification.'
                ],
                'easypaisa' => [
                    'name' => 'EasyPaisa',
                    'account_number' => '03120922709',
                    'account_title' => 'Rizwanullah',
                    'steps' => [
                        'Visit the nearest EasyPaisa agent',
                        'Or use EasyPaisa app/website',
                        'Select "Send to EasyPaisa Account"',
                        'Enter account title: Rizwanullah',
                        'Enter amount to send',
                        'Collect receipt'
                    ],
                    'note' => 'Keep the receipt. Transaction ID will be on it.'
                ],
                'upaisa' => [
                    'name' => 'U Paisa',
                    'account_number' => '03120922709',
                    'account_title' => 'Rizwanullah',
                    'steps' => [
                        'Dial *7878# from any number',
                        'Or download U Paisa app',
                        'Select "Send to Merchant"',
                        'Enter account: Rizwanullah',
                        'Enter amount',
                        'Confirm and note the confirmation number'
                    ],
                    'note' => 'The confirmation number will appear after successful transaction.'
                ]
            ];

            return Response::success('Payment instructions retrieved', 200, $instructions[$method]);
        } catch (\Exception $e) {
            logger()->error('Get payment instructions error: ' . $e->getMessage());
            return Response::error('Unable to get payment instructions', 500);
        }
    }

    /**
     * Verify manual payment details
     * POST /api/payment/verify
     * Parameters: ref_code, transaction_id, payment_method, proof_file (optional)
     */
    public function verify()
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
                return Response::error('Payment not found', 404);
            }

            if ($payment['status'] !== 'pending') {
                return Response::error('Payment already processed', 422);
            }

            // Handle file upload
            $filePath = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $filePath = $this->handleFileUpload($_FILES['proof_file'], 'payment-proofs');
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
                'status' => 'pending' // Stays pending for admin verification
            ];

            $this->paymentModel->update($payment['id'], $updateData);

            logger()->info('Payment verification submitted', [
                'ref_code' => $refCode,
                'method' => $paymentMethod
            ]);

            return Response::success('Payment details received. Admin will verify within 2 hours.', 200, [
                'ref_code' => $refCode,
                'status' => 'pending_verification',
                'estimated_verification_time' => '2 hours'
            ]);
        } catch (\Exception $e) {
            logger()->error('Payment verify error: ' . $e->getMessage());
            return Response::error('Unable to verify payment', 500);
        }
    }

    /**
     * Check payment verification status
     * GET /api/payment/{ref_code}/check
     */
    public function checkVerification($refCode)
    {
        try {
            $payment = $this->paymentModel->getByRefCode($refCode);

            if (!$payment) {
                return Response::error('Payment not found', 404);
            }

            $status = $payment['status'];
            $isVerified = $status === 'verified';

            $response = [
                'ref_code' => $refCode,
                'status' => $status,
                'is_verified' => $isVerified,
                'amount' => $payment['amount'],
                'created_at' => $payment['created_at'],
                'verified_at' => $payment['verified_at']
            ];

            // If verified, get order details and relevant links
            if ($isVerified) {
                $orderDetails = $this->getOrderDetails($payment['order_type'], $payment['order_id']);
                $response['order_details'] = $orderDetails;

                // Add specific details based on order type
                if ($payment['order_type'] === 'appointment') {
                    $appointment = $this->appointmentModel->getById($payment['order_id']);
                    if ($appointment && $appointment['google_meet_link']) {
                        $response['google_meet_link'] = $appointment['google_meet_link'];
                        $response['message'] = 'Your appointment is confirmed! Google Meet link has been shared.';
                    }
                }
            }

            return Response::success('Payment status checked', 200, $response);
        } catch (\Exception $e) {
            logger()->error('Check verification error: ' . $e->getMessage());
            return Response::error('Unable to check payment', 500);
        }
    }

    /**
     * Get payment receipt/details
     * GET /api/payment/{ref_code}/receipt
     */
    public function getReceipt($refCode)
    {
        try {
            $payment = $this->paymentModel->getByRefCode($refCode);

            if (!$payment) {
                return Response::error('Payment not found', 404);
            }

            $orderDetails = $this->getOrderDetails($payment['order_type'], $payment['order_id']);

            $receipt = [
                'ref_code' => $refCode,
                'date' => $payment['created_at'],
                'amount' => $payment['amount'],
                'payment_method' => $payment['payment_method'],
                'status' => $payment['status'],
                'order_type' => $payment['order_type'],
                'order_details' => $orderDetails
            ];

            return Response::success('Receipt retrieved', 200, $receipt);
        } catch (\Exception $e) {
            logger()->error('Get receipt error: ' . $e->getMessage());
            return Response::error('Unable to get receipt', 500);
        }
    }

    /**
     * Helper: Get order details based on type
     */
    private function getOrderDetails($orderType, $orderId)
    {
        try {
            switch ($orderType) {
                case 'appointment':
                    return $this->appointmentModel->getById($orderId);

                case 'lab_test':
                    return $this->labBookingModel->getById($orderId);

                case 'pharmacy':
                    return $this->pharmacyOrderModel->getById($orderId);

                default:
                    return null;
            }
        } catch (\Exception $e) {
            logger()->error('Get order details error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper: Handle file upload
     */
    private function handleFileUpload($file, $type = 'payment-proofs')
    {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxSize = 5 * 1024 * 1024;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed) || $file['size'] > $maxSize) {
            return false;
        }

        $filename = $type . '_' . uniqid() . '.' . $ext;
        $uploadDir = BASE_PATH . '/public/assets/uploads/' . $type . '/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return false;
        }

        return '/assets/uploads/' . $type . '/' . $filename;
    }
}
