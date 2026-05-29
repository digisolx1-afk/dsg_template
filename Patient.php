<?php
/**
 * Digital Sehat Ghar - Patient Model
 * ==================================
 * Handles patient profiles and medical information
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

require_once ROOT_PATH . 'app/models/BaseModel.php';

class Patient extends BaseModel
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'patients';

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
        'user_id',
        'date_of_birth',
        'blood_group',
        'gender',
        'address',
        'city',
        'district',
        'province',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'medical_history',
        'allergies',
        'profile_photo',
        'profile_photo_thumb',
        'cnic_front_image',
        'cnic_back_image'
    ];

    /**
     * Timestamps
     *
     * @var bool
     */
    protected $timestamps = false;

    /**
     * Casts
     *
     * @var array
     */
    protected $casts = [
        'medical_history' => 'json',
        'allergies' => 'json'
    ];

    /**
     * Find patient by user ID
     *
     * @param int $userId User ID
     *
     * @return static|null
     */
    public static function findByUserId($userId)
    {
        $patients = static::where('user_id', $userId);
        return !empty($patients) ? $patients[0] : null;
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
     * Get patient's full name from user
     *
     * @return string|null
     */
    public function getFullName()
    {
        $user = $this->user();
        return $user ? $user->getAttribute('full_name') : null;
    }

    /**
     * Get patient's email from user
     *
     * @return string|null
     */
    public function getEmail()
    {
        $user = $this->user();
        return $user ? $user->getAttribute('email') : null;
    }

    /**
     * Get patient's phone from user
     *
     * @return string|null
     */
    public function getPhone()
    {
        $user = $this->user();
        return $user ? $user->getAttribute('phone') : null;
    }

    /**
     * Calculate age from date of birth
     *
     * @return int
     */
    public function getAge()
    {
        $dob = $this->getAttribute('date_of_birth');
        if (!$dob) {
            return 0;
        }

        $birthDate = new DateTime($dob);
        $today = new DateTime();
        return $today->diff($birthDate)->y;
    }

    /**
     * Get blood group
     *
     * @return string
     */
    public function getBloodGroup()
    {
        return $this->getAttribute('blood_group') ?? 'Unknown';
    }

    /**
     * Get gender
     *
     * @return string
     */
    public function getGender()
    {
        return $this->getAttribute('gender') ?? 'Other';
    }

    /**
     * Get medical history
     *
     * @return array|null
     */
    public function getMedicalHistory()
    {
        $history = $this->getAttribute('medical_history');
        return is_array($history) ? $history : [];
    }

    /**
     * Get allergies
     *
     * @return array|null
     */
    public function getAllergies()
    {
        $allergies = $this->getAttribute('allergies');
        return is_array($allergies) ? $allergies : [];
    }

    /**
     * Get location
     *
     * @return array
     */
    public function getLocation()
    {
        return [
            'address' => $this->getAttribute('address'),
            'city' => $this->getAttribute('city'),
            'district' => $this->getAttribute('district'),
            'province' => $this->getAttribute('province'),
            'postal_code' => $this->getAttribute('postal_code')
        ];
    }

    /**
     * Get emergency contact
     *
     * @return array
     */
    public function getEmergencyContact()
    {
        return [
            'name' => $this->getAttribute('emergency_contact_name'),
            'phone' => $this->getAttribute('emergency_contact_phone')
        ];
    }

    /**
     * Check if patient profile is complete
     *
     * @return bool
     */
    public function isProfileComplete()
    {
        return !empty($this->getAttribute('date_of_birth')) &&
            !empty($this->getAttribute('blood_group')) &&
            !empty($this->getAttribute('gender')) &&
            !empty($this->getAttribute('address')) &&
            !empty($this->getAttribute('city'));
    }

    /**
     * Validation rules
     *
     * @return array
     */
    public static function validationRules()
    {
        return [
            'user_id' => 'required|integer',
            'date_of_birth' => 'required|date',
            'blood_group' => 'required|in:' . implode(',', array_keys(BLOOD_GROUPS)),
            'gender' => 'required|in:' . implode(',', array_keys(GENDERS)),
            'city' => 'required'
        ];
    }
}

?>
