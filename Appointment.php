<?php
/**
 * Digital Sehat Ghar - Appointment Model
 * =====================================
 * Handles appointment bookings for doctors
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

require_once ROOT_PATH . 'app/models/BaseModel.php';

class Appointment extends BaseModel
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'appointments';

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
        'patient_id',
        'doctor_id',
        'appointment_date',
        'time_slot',
        'location_type',
        'consultation_type',
        'fees_amount',
        'status',
        'payment_ref_code',
        'payment_verified_at',
        'google_meet_link',
        'patient_symptoms',
        'clinical_notes',
        'prescription_id',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason'
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
        'fees_amount' => 'float'
    ];

    /**
     * Get appointments by patient
     *
     * @param int $patientId Patient ID
     *
     * @return array
     */
    public static function getByPatient($patientId)
    {
        return static::where('patient_id', $patientId);
    }

    /**
     * Get appointments by doctor
     *
     * @param int $doctorId Doctor ID
     *
     * @return array
     */
    public static function getByDoctor($doctorId)
    {
        return static::where('doctor_id', $doctorId);
    }

    /**
     * Get appointments by status
     *
     * @param string $status Appointment status
     *
     * @return array
     */
    public static function getByStatus($status)
    {
        return static::where('status', $status);
    }

    /**
     * Get upcoming appointments for patient
     *
     * @param int $patientId Patient ID
     *
     * @return array
     */
    public static function getUpcomingForPatient($patientId)
    {
        $query = 'SELECT * FROM ' . (new self())->getTable()
            . ' WHERE patient_id = ? AND appointment_date >= CURDATE() 
                  AND status IN (?, ?) 
                  ORDER BY appointment_date ASC, time_slot ASC';

        $results = Database::select($query, [
            $patientId,
            APPOINTMENT_STATUS_PAYMENT_VERIFIED,
            APPOINTMENT_STATUS_CONFIRMED
        ]);

        $appointments = [];
        foreach ($results as $result) {
            $appointment = new static($result);
            $appointment->exists = true;
            $appointments[] = $appointment;
        }

        return $appointments;
    }

    /**
     * Get upcoming appointments for doctor
     *
     * @param int $doctorId Doctor ID
     *
     * @return array
     */
    public static function getUpcomingForDoctor($doctorId)
    {
        $query = 'SELECT * FROM ' . (new self())->getTable()
            . ' WHERE doctor_id = ? AND appointment_date >= CURDATE() 
                  AND status IN (?, ?) 
                  ORDER BY appointment_date ASC, time_slot ASC';

        $results = Database::select($query, [
            $doctorId,
            APPOINTMENT_STATUS_PAYMENT_VERIFIED,
            APPOINTMENT_STATUS_CONFIRMED
        ]);

        $appointments = [];
        foreach ($results as $result) {
            $appointment = new static($result);
            $appointment->exists = true;
            $appointments[] = $appointment;
        }

        return $appointments;
    }

    /**
     * Get pending payment appointments
     *
     * @return array
     */
    public static function getPendingPayment()
    {
        return static::where('status', APPOINTMENT_STATUS_PENDING_PAYMENT);
    }

    /**
     * Find by payment reference code
     *
     * @param string $refCode Payment reference code
     *
     * @return static|null
     */
    public static function findByPaymentRefCode($refCode)
    {
        $appointments = static::where('payment_ref_code', $refCode);
        return !empty($appointments) ? $appointments[0] : null;
    }

    /**
     * Check if slot is available
     *
     * @param int $doctorId Doctor ID
     * @param string $date Appointment date
     * @param string $timeSlot Time slot
     * @param string $locationType Location type
     *
     * @return bool
     */
    public static function isSlotAvailable($doctorId, $date, $timeSlot, $locationType)
    {
        $query = 'SELECT COUNT(*) as count FROM ' . (new self())->getTable()
            . ' WHERE doctor_id = ? AND appointment_date = ? 
                  AND time_slot = ? AND location_type = ? 
                  AND status NOT IN (?, ?)';

        $result = Database::select($query, [
            $doctorId,
            $date,
            $timeSlot,
            $locationType,
            APPOINTMENT_STATUS_CANCELLED,
            APPOINTMENT_STATUS_NO_SHOW
        ], 'row');

        return ($result['count'] ?? 0) === 0;
    }

    /**
     * Verify payment
     *
     * @return bool
     */
    public function verifyPayment()
    {
        $this->setAttribute('status', APPOINTMENT_STATUS_PAYMENT_VERIFIED);
        $this->setAttribute('payment_verified_at', date('Y-m-d H:i:s'));
        return $this->save();
    }

    /**
     * Confirm appointment
     *
     * @return bool
     */
    public function confirm()
    {
        $this->setAttribute('status', APPOINTMENT_STATUS_CONFIRMED);
        return $this->save();
    }

    /**
     * Complete appointment
     *
     * @return bool
     */
    public function complete()
    {
        $this->setAttribute('status', APPOINTMENT_STATUS_COMPLETED);
        return $this->save();
    }

    /**
     * Cancel appointment
     *
     * @param string $cancelledBy Who cancelled (patient/doctor/admin)
     * @param string $reason Cancellation reason
     *
     * @return bool
     */
    public function cancel($cancelledBy, $reason = '')
    {
        $this->setAttribute('status', APPOINTMENT_STATUS_CANCELLED);
        $this->setAttribute('cancelled_by', $cancelledBy);
        $this->setAttribute('cancelled_at', date('Y-m-d H:i:s'));
        $this->setAttribute('cancellation_reason', $reason);
        return $this->save();
    }

    /**
     * Mark as no-show
     *
     * @return bool
     */
    public function markNoShow()
    {
        $this->setAttribute('status', APPOINTMENT_STATUS_NO_SHOW);
        return $this->save();
    }

    /**
     * Set Google Meet link for video consultation
     *
     * @param string $meetLink Google Meet link
     *
     * @return bool
     */
    public function setGoogleMeetLink($meetLink)
    {
        $this->setAttribute('google_meet_link', $meetLink);
        return $this->save();
    }

    /**
     * Add clinical notes
     *
     * @param string $notes Clinical notes from doctor
     *
     * @return bool
     */
    public function addClinicalNotes($notes)
    {
        $this->setAttribute('clinical_notes', $notes);
        return $this->save();
    }

    /**
     * Check if appointment is today
     *
     * @return bool
     */
    public function isToday()
    {
        return $this->getAttribute('appointment_date') === date('Y-m-d');
    }

    /**
     * Check if appointment is upcoming
     *
     * @return bool
     */
    public function isUpcoming()
    {
        $date = new DateTime($this->getAttribute('appointment_date'));
        return $date > new DateTime();
    }

    /**
     * Check if appointment is past
     *
     * @return bool
     */
    public function isPast()
    {
        $date = new DateTime($this->getAttribute('appointment_date'));
        return $date < new DateTime();
    }

    /**
     * Get appointment status color
     *
     * @return string
     */
    public function getStatusColor()
    {
        $status = $this->getAttribute('status');
        return APPOINTMENT_STATUSES[$status]['color'] ?? 'secondary';
    }

    /**
     * Get appointment status label
     *
     * @return string
     */
    public function getStatusLabel()
    {
        $status = $this->getAttribute('status');
        return APPOINTMENT_STATUSES[$status]['label'] ?? 'Unknown';
    }

    /**
     * Get appointment type label
     *
     * @return string
     */
    public function getTypeLabel()
    {
        return APPOINTMENT_TYPES[$this->getAttribute('location_type')] ?? 'Unknown';
    }

    /**
     * Get doctor details
     *
     * @return Doctor|null
     */
    public function doctor()
    {
        return Doctor::find($this->getAttribute('doctor_id'));
    }

    /**
     * Get patient details
     *
     * @return Patient|null
     */
    public function patient()
    {
        return Patient::find($this->getAttribute('patient_id'));
    }

    /**
     * Get payment fee formatted
     *
     * @return string
     */
    public function getFeeFormatted()
    {
        $fee = $this->getAttribute('fees_amount');
        return CURRENCY_SYMBOL . number_format($fee, CURRENCY_DECIMALS);
    }

    /**
     * Validation rules
     *
     * @return array
     */
    public static function validationRules()
    {
        return [
            'patient_id' => 'required|integer',
            'doctor_id' => 'required|integer',
            'appointment_date' => 'required|date|date_after:' . date('Y-m-d'),
            'time_slot' => 'required',
            'location_type' => 'required|in:clinic,video',
            'fees_amount' => 'required|numeric|min:0'
        ];
    }
}

?>
