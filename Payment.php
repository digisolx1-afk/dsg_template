<?php
/**
 * Digital Sehat Ghar - Payment Model
 * =================================
 * Handles payment tracking and verification
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

require_once ROOT_PATH . 'app/models/BaseModel.php';

class Payment extends BaseModel
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'payments';

    /**
     * Primary key
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Fillable attributes
     *
     * @var array
     */
    protected $fillable = [
        'ref_code',
        'order_type',
        'order_id',
        'user_id',
        'amount',
        'payment_method',
        'proof_file_path',
        'proof_uploaded_at',
        'transaction_id_user',
        'sender_name',
        'admin_notes',
        'status',
        'verified_by',
        'verified_at',
        'rejected_reason'
    ];

    /**
     * Timestamps
     *
     * @var bool
     */
    protected $timestamps = true;

    /**
     * Casts
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'float'
    ];

    /**
     * Get payment by reference code
     *
     * @param string $refCode Reference code
     *
     * @return static|null
     */
    public static function findByRefCode($refCode)
    {
        $payments = static::where('ref_code', $refCode);
        return !empty($payments) ? $payments[0] : null;
    }

    /**
     * Get payments by user
     *
     * @param int $userId User ID
     *
     * @return array
     */
    public static function getByUser($userId)
    {
        return static::where('user_id', $userId);
    }

    /**
     * Get payments by order type
     *
     * @param string $orderType Order type (appointment/pharmacy/lab_test)
     *
     * @return array
     */
    public static function getByOrderType($orderType)
    {
        return static::where('order_type', $orderType);
    }

    /**
     * Get payments by status
     *
     * @param string $status Payment status
     *
     * @return array
     */
    public static function getByStatus($status)
    {
        return static::where('status', $status);
    }

    /**
     * Get pending payments
     *
     * @return array
     */
    public static function getPending()
    {
        return static::where('status', PAYMENT_STATUS_PENDING);
    }

    /**
     * Get verified payments
     *
     * @return array
     */
    public static function getVerified()
    {
        return static::where('status', PAYMENT_STATUS_VERIFIED);
    }

    /**
     * Get rejected payments
     *
     * @return array
     */
    public static function getRejected()
    {
        return static::where('status', PAYMENT_STATUS_REJECTED);
    }

    /**
     * Get expired payments
     *
     * @return array
     */
    public static function getExpired()
    {
        return static::where('status', PAYMENT_STATUS_EXPIRED);
    }

    /**
     * Verify payment
     *
     * @param int $verifiedBy Admin ID
     * @param string $adminNotes Admin notes
     *
     * @return bool
     */
    public function verify($verifiedBy, $adminNotes = '')
    {
        $this->setAttribute('status', PAYMENT_STATUS_VERIFIED);
        $this->setAttribute('verified_by', $verifiedBy);
        $this->setAttribute('verified_at', date('Y-m-d H:i:s'));
        $this->setAttribute('admin_notes', $adminNotes);
        return $this->save();
    }

    /**
     * Reject payment
     *
     * @param int $verifiedBy Admin ID
     * @param string $rejectionReason Rejection reason
     *
     * @return bool
     */
    public function reject($verifiedBy, $rejectionReason)
    {
        $this->setAttribute('status', PAYMENT_STATUS_REJECTED);
        $this->setAttribute('verified_by', $verifiedBy);
        $this->setAttribute('verified_at', date('Y-m-d H:i:s'));
        $this->setAttribute('rejected_reason', $rejectionReason);
        return $this->save();
    }

    /**
     * Mark as expired
     *
     * @return bool
     */
    public function expire()
    {
        $this->setAttribute('status', PAYMENT_STATUS_EXPIRED);
        return $this->save();
    }

    /**
     * Check if payment is verified
     *
     * @return bool
     */
    public function isVerified()
    {
        return $this->getAttribute('status') === PAYMENT_STATUS_VERIFIED;
    }

    /**
     * Check if payment is pending
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->getAttribute('status') === PAYMENT_STATUS_PENDING;
    }

    /**
     * Check if payment is rejected
     *
     * @return bool
     */
    public function isRejected()
    {
        return $this->getAttribute('status') === PAYMENT_STATUS_REJECTED;
    }

    /**
     * Check if payment is expired
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->getAttribute('status') === PAYMENT_STATUS_EXPIRED;
    }

    /**
     * Check if payment has expired (auto-expiry)
     *
     * @return bool
     */
    public function shouldAutoExpire()
    {
        if (!$this->isPending()) {
            return false;
        }

        $createdAt = new DateTime($this->getAttribute('created_at'));
        $now = new DateTime();
        $hours = $now->diff($createdAt)->h;

        return $hours >= PAYMENT_AUTO_CANCEL_HOURS;
    }

    /**
     * Get amount formatted with currency
     *
     * @return string
     */
    public function getAmountFormatted()
    {
        $amount = $this->getAttribute('amount');
        return CURRENCY_SYMBOL . number_format($amount, CURRENCY_DECIMALS);
    }

    /**
     * Get payment method label
     *
     * @return string
     */
    public function getMethodLabel()
    {
        return PAYMENT_METHODS_LIST[$this->getAttribute('payment_method')] ?? 'Unknown';
    }

    /**
     * Get status label
     *
     * @return string
     */
    public function getStatusLabel()
    {
        $status = $this->getAttribute('status');
        return PAYMENT_STATUSES[$status]['label'] ?? 'Unknown';
    }

    /**
     * Get status color
     *
     * @return string
     */
    public function getStatusColor()
    {
        $status = $this->getAttribute('status');
        return PAYMENT_STATUSES[$status]['color'] ?? 'secondary';
    }

    /**
     * Generate unique reference code
     *
     * @return string
     */
    public static function generateRefCode()
    {
        do {
            $refCode = 'DSG-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        } while (static::findByRefCode($refCode)); // Ensure uniqueness

        return $refCode;
    }

    /**
     * Get related order (appointment, lab booking, or pharmacy order)
     *
     * @return mixed Order object or null
     */
    public function getOrder()
    {
        $orderType = $this->getAttribute('order_type');
        $orderId = $this->getAttribute('order_id');

        switch ($orderType) {
            case 'appointment':
                return Appointment::find($orderId);
            case 'pharmacy':
                return PharmacyOrder::find($orderId);
            case 'lab_test':
                return LabBooking::find($orderId);
            default:
                return null;
        }
    }

    /**
     * Get user details
     *
     * @return User|null
     */
    public function user()
    {
        return User::find($this->getAttribute('user_id'));
    }

    /**
     * Validation rules
     *
     * @return array
     */
    public static function validationRules()
    {
        return [
            'amount' => 'required|numeric|min:0',
            'order_type' => 'required|in:appointment,pharmacy,lab_test',
            'order_id' => 'required|integer',
            'payment_method' => 'required|in:jazzcash,easypaisa,upaisa,bank_transfer,manual'
        ];
    }
}

?>
