<?php

namespace App\Controllers;

use App\Models\Medicine;
use App\Models\PharmacyOrder;
use App\Models\Payment;
use App\Helpers\Response;
use App\Helpers\Validator;

/**
 * PharmacyController - Handles pharmacy operations, shopping cart, and orders
 * Features: Browse medicines, shopping cart, checkout, payment
 */
class PharmacyController
{
    private $medicineModel;
    private $orderModel;
    private $paymentModel;

    public function __construct()
    {
        $this->medicineModel = new Medicine();
        $this->orderModel = new PharmacyOrder();
        $this->paymentModel = new Payment();
    }

    /**
     * Display pharmacy page with medicines
     * GET /pharmacy
     */
    public function index()
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $category = $_GET['category'] ?? null;
            $search = $_GET['search'] ?? null;
            $sortBy = $_GET['sort'] ?? 'newest'; // newest, price_asc, price_desc, popular
            $perPage = 12;

            $filters = [
                'category' => $category,
                'search' => $search,
                'is_active' => 1
            ];

            $total = $this->medicineModel->countMedicines($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            $medicines = $this->medicineModel->searchMedicines(
                $filters,
                sortBy: $sortBy,
                limit: $perPage,
                offset: $offset
            );

            $categories = $this->medicineModel->getCategories();

            return view('pages/pharmacy/index', [
                'medicines' => $medicines,
                'categories' => $categories,
                'currentCategory' => $category,
                'searchQuery' => $search,
                'currentSort' => $sortBy,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => 'Online Pharmacy | Digital Sehat Ghar'
            ]);
        } catch (\Exception $e) {
            logger()->error('Pharmacy index error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load pharmacy',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * View medicine detail
     * GET /pharmacy/medicine/{id}
     */
    public function medicineDetail($medicineId)
    {
        try {
            $medicine = $this->medicineModel->getById($medicineId);

            if (!$medicine || !$medicine['is_active']) {
                return view('error', [
                    'message' => 'Medicine not found',
                    'code' => 404
                ]);
            }

            // Get related medicines
            $related = $this->medicineModel->getRelatedMedicines(
                $medicine['category'],
                exclude_id: $medicineId,
                limit: 4
            );

            return view('pages/pharmacy/detail', [
                'medicine' => $medicine,
                'related' => $related,
                'title' => $medicine['name']
            ]);
        } catch (\Exception $e) {
            logger()->error('Medicine detail error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load medicine',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Search medicines with filters
     * GET /api/pharmacy/search
     */
    public function search()
    {
        try {
            $search = $_GET['q'] ?? null;
            $category = $_GET['category'] ?? null;
            $requiresPrescription = $_GET['prescription'] ?? null;
            $maxPrice = (float)($_GET['max_price'] ?? null);
            $page = (int)($_GET['page'] ?? 1);
            $perPage = 12;

            $filters = [
                'search' => $search,
                'category' => $category,
                'requires_prescription' => $requiresPrescription === 'yes' ? 1 : null,
                'max_price' => $maxPrice,
                'is_active' => 1
            ];

            $total = $this->medicineModel->countMedicines($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            $medicines = $this->medicineModel->searchMedicines($filters, limit: $perPage, offset: $offset);

            return Response::success('Medicines retrieved', 200, [
                'medicines' => $medicines,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            logger()->error('Pharmacy search error: ' . $e->getMessage());
            return Response::error('Unable to search medicines', 500);
        }
    }

    /**
     * Get medicine by ID for AJAX
     * GET /api/pharmacy/medicine/{id}
     */
    public function getMedicine($medicineId)
    {
        try {
            $medicine = $this->medicineModel->getById($medicineId);

            if (!$medicine) {
                return Response::error('Medicine not found', 404);
            }

            return Response::success('Medicine retrieved', 200, [
                'medicine' => $medicine
            ]);
        } catch (\Exception $e) {
            logger()->error('Get medicine error: ' . $e->getMessage());
            return Response::error('Unable to get medicine', 500);
        }
    }

    /**
     * Create pharmacy order from cart
     * POST /api/pharmacy/order
     */
    public function createOrder()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return Response::error('Invalid request method', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            // Validation
            $validator = new Validator();
            $validated = $validator->validate($input, [
                'items' => 'required|array',
                'patient_name' => 'required|string|min:3',
                'patient_email' => 'required|email',
                'patient_phone' => 'required|phone',
                'delivery_address' => 'required|string|min:10',
                'delivery_city' => 'required|string',
                'patient_instructions' => 'string'
            ]);

            if (!$validated['valid']) {
                return Response::error('Validation failed', 422, $validated['errors']);
            }

            $data = $validated['data'];

            // Validate items
            if (empty($data['items']) || !is_array($data['items'])) {
                return Response::error('No items in order', 422);
            }

            // Verify medicines and calculate total
            $subtotal = 0;
            $orderItems = [];

            foreach ($data['items'] as $item) {
                if (!isset($item['medicine_id'], $item['quantity'])) {
                    return Response::error('Invalid item format', 422);
                }

                $medicine = $this->medicineModel->getById($item['medicine_id']);
                if (!$medicine) {
                    return Response::error('Medicine not found: ' . $item['medicine_id'], 404);
                }

                if ($medicine['stock_quantity'] < $item['quantity']) {
                    return Response::error('Insufficient stock for: ' . $medicine['name'], 422);
                }

                $subtotal += $medicine['price'] * $item['quantity'];

                $orderItems[] = [
                    'medicine_id' => $medicine['id'],
                    'name' => $medicine['name'],
                    'quantity' => $item['quantity'],
                    'price' => $medicine['price']
                ];
            }

            // Calculate delivery charges
            $deliveryCharges = 0;
            if ($subtotal < 2000) {
                $deliveryCharges = 200; // Fixed delivery charge if below 2000
            }

            $totalAmount = $subtotal + $deliveryCharges;

            // Generate order number and ref code
            $orderNumber = $this->generateOrderNumber();
            $refCode = $this->generatePaymentRefCode();

            // Create order
            $orderData = [
                'order_number' => $orderNumber,
                'patient_name' => $data['patient_name'],
                'patient_email' => $data['patient_email'],
                'patient_phone' => $data['patient_phone'],
                'items_json' => json_encode($orderItems),
                'subtotal' => $subtotal,
                'delivery_charges' => $deliveryCharges,
                'total_amount' => $totalAmount,
                'delivery_address' => $data['delivery_address'],
                'delivery_city' => $data['delivery_city'],
                'patient_instructions' => $data['patient_instructions'] ?? null,
                'payment_ref_code' => $refCode,
                'status' => 'pending_payment'
            ];

            $orderId = $this->orderModel->create($orderData);

            if (!$orderId) {
                return Response::error('Failed to create order', 500);
            }

            // Create payment record
            $paymentData = [
                'ref_code' => $refCode,
                'order_type' => 'pharmacy',
                'order_id' => $orderId,
                'user_id' => 0,
                'amount' => $totalAmount,
                'payment_method' => 'manual',
                'status' => 'pending'
            ];

            $this->paymentModel->create($paymentData);

            logger()->info('Pharmacy order created', [
                'order_id' => $orderId,
                'ref_code' => $refCode
            ]);

            return Response::success('Order created successfully', 201, [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'ref_code' => $refCode,
                'subtotal' => $subtotal,
                'delivery_charges' => $deliveryCharges,
                'total_amount' => $totalAmount,
                'items' => $orderItems,
                'payment_methods' => [
                    'jazzcash' => '03120922709',
                    'easypaisa' => '03120922709',
                    'upaisa' => '03120922709',
                    'account_title' => 'Rizwanullah'
                ]
            ]);
        } catch (\Exception $e) {
            logger()->error('Create pharmacy order error: ' . $e->getMessage());
            return Response::error('Unable to create order', 500);
        }
    }

    /**
     * Verify pharmacy order payment
     * POST /api/pharmacy/order/verify-payment
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
            $prescriptionPath = null;

            if (!$refCode || !$transactionId || !$paymentMethod) {
                return Response::error('Missing required fields', 422);
            }

            if (!in_array($paymentMethod, ['jazzcash', 'easypaisa', 'upaisa'])) {
                return Response::error('Invalid payment method', 422);
            }

            $payment = $this->paymentModel->getByRefCode($refCode);
            if (!$payment) {
                return Response::error('Payment not found', 404);
            }

            if ($payment['status'] !== 'pending') {
                return Response::error('Payment already verified or rejected', 422);
            }

            // Handle prescription upload if present
            if (isset($_FILES['prescription']) && $_FILES['prescription']['error'] === UPLOAD_ERR_OK) {
                $prescriptionPath = $this->handlePrescriptionUpload($_FILES['prescription']);
                if (!$prescriptionPath) {
                    return Response::error('Invalid prescription upload', 422);
                }
            }

            // Handle payment proof upload
            $filePath = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $filePath = $this->handlePaymentProofUpload($_FILES['proof_file']);
                if (!$filePath) {
                    return Response::error('Invalid payment proof upload', 422);
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

            // Update order with prescription if provided
            if ($prescriptionPath) {
                $this->orderModel->update($payment['order_id'], [
                    'prescription_image' => $prescriptionPath
                ]);
            }

            logger()->info('Pharmacy payment verified', [
                'ref_code' => $refCode
            ]);

            return Response::success('Payment received. Admin will verify within 2 hours.', 200, [
                'ref_code' => $refCode,
                'status' => 'pending_verification'
            ]);
        } catch (\Exception $e) {
            logger()->error('Pharmacy verify payment error: ' . $e->getMessage());
            return Response::error('Unable to verify payment', 500);
        }
    }

    /**
     * Get order details
     * GET /api/pharmacy/order/{ref_code}
     */
    public function getOrder($refCode)
    {
        try {
            $payment = $this->paymentModel->getByRefCode($refCode);

            if (!$payment) {
                return Response::error('Order not found', 404);
            }

            $order = $this->orderModel->getById($payment['order_id']);

            if (!$order) {
                return Response::error('Order not found', 404);
            }

            return Response::success('Order retrieved', 200, [
                'order' => $order,
                'payment_status' => $payment['status']
            ]);
        } catch (\Exception $e) {
            logger()->error('Get order error: ' . $e->getMessage());
            return Response::error('Unable to get order', 500);
        }
    }

    /**
     * Cancel order
     * POST /api/pharmacy/order/{ref_code}/cancel
     */
    public function cancelOrder($refCode)
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return Response::error('Invalid request method', 405);
            }

            $payment = $this->paymentModel->getByRefCode($refCode);
            if (!$payment) {
                return Response::error('Order not found', 404);
            }

            $order = $this->orderModel->getById($payment['order_id']);
            if (!$order) {
                return Response::error('Order not found', 404);
            }

            // Can only cancel if payment not verified
            if ($payment['status'] === 'verified') {
                return Response::error('Cannot cancel verified orders. Contact admin.', 422);
            }

            $this->orderModel->update($order['id'], [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s')
            ]);

            logger()->info('Pharmacy order cancelled', [
                'order_id' => $order['id']
            ]);

            return Response::success('Order cancelled successfully', 200);
        } catch (\Exception $e) {
            logger()->error('Cancel order error: ' . $e->getMessage());
            return Response::error('Unable to cancel order', 500);
        }
    }

    /**
     * Track order status
     * GET /api/pharmacy/order/{ref_code}/status
     */
    public function getOrderStatus($refCode)
    {
        try {
            $payment = $this->paymentModel->getByRefCode($refCode);

            if (!$payment) {
                return Response::error('Order not found', 404);
            }

            $order = $this->orderModel->getById($payment['order_id']);

            if (!$order) {
                return Response::error('Order not found', 404);
            }

            return Response::success('Status retrieved', 200, [
                'order_id' => $order['id'],
                'order_number' => $order['order_number'],
                'ref_code' => $refCode,
                'status' => $order['status'],
                'payment_status' => $payment['status'],
                'tracking_id' => $order['tracking_id'] ?? null,
                'estimated_delivery' => $order['estimated_delivery'] ?? null
            ]);
        } catch (\Exception $e) {
            logger()->error('Get order status error: ' . $e->getMessage());
            return Response::error('Unable to get order status', 500);
        }
    }

    /**
     * Helper: Generate unique order number
     */
    private function generateOrderNumber()
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(rand()), 0, 6));
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
     * Helper: Handle payment proof upload
     */
    private function handlePaymentProofUpload($file)
    {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxSize = 5 * 1024 * 1024;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed) || $file['size'] > $maxSize) {
            return false;
        }

        $filename = 'pharmacy_payment_' . uniqid() . '.' . $ext;
        $uploadDir = BASE_PATH . '/public/assets/uploads/payment-proofs/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return false;
        }

        return '/assets/uploads/payment-proofs/' . $filename;
    }

    /**
     * Helper: Handle prescription upload
     */
    private function handlePrescriptionUpload($file)
    {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxSize = 10 * 1024 * 1024; // 10MB for prescriptions

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed) || $file['size'] > $maxSize) {
            return false;
        }

        $filename = 'prescription_' . uniqid() . '.' . $ext;
        $uploadDir = BASE_PATH . '/public/assets/uploads/prescriptions/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return false;
        }

        return '/assets/uploads/prescriptions/' . $filename;
    }
}
