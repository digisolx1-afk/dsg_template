<?php
/**
 * Digital Sehat Ghar - Doctor Model
 * ================================
 * Handles doctor profiles, specialties, and availability
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

require_once ROOT_PATH . 'app/models/BaseModel.php';

class Doctor extends BaseModel
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'doctors';

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
        'pmdc_number',
        'specialty',
        'sub_specialty',
        'qualification',
        'experience_years',
        'clinic_name',
        'clinic_address',
        'clinic_city',
        'consultation_fee',
        'video_consultation_fee',
        'is_video_available',
        'is_clinic_available',
        'bio',
        'services',
        'average_rating',
        'total_reviews',
        'profile_photo',
        'profile_photo_thumb',
        'clinic_photo',
        'degree_certificate_image',
        'pmdc_certificate_image',
        'signature_image',
        'is_verified',
        'verification_documents',
        'verification_notes',
        'verified_by',
        'verified_at',
        'profile_views'
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
        'is_video_available' => 'bool',
        'is_clinic_available' => 'bool',
        'is_verified' => 'bool',
        'average_rating' => 'float',
        'total_reviews' => 'int',
        'experience_years' => 'int',
        'profile_views' => 'int',
        'consultation_fee' => 'float',
        'video_consultation_fee' => 'float',
        'services' => 'json'
    ];

    /**
     * Get doctors by specialty
     *
     * @param string $specialty Doctor specialty
     *
     * @return array
     */
    public static function getBySpecialty($specialty)
    {
        return static::where('specialty', $specialty);
    }

    /**
     * Get doctors by city
     *
     * @param string $city Doctor clinic city
     *
     * @return array
     */
    public static function getByCity($city)
    {
        return static::where('clinic_city', $city);
    }

    /**
     * Get doctors by specialty and city
     *
     * @param string $specialty Doctor specialty
     * @param string $city Doctor clinic city
     *
     * @return array
     */
    public static function getBySpecialtyAndCity($specialty, $city)
    {
        $query = 'SELECT * FROM ' . (new self())->getTable()
            . ' WHERE specialty = ? AND clinic_city = ? AND is_verified = 1';

        $results = Database::select($query, [$specialty, $city]);

        $doctors = [];
        foreach ($results as $result) {
            $doctor = new static($result);
            $doctor->exists = true;
            $doctors[] = $doctor;
        }

        return $doctors;
    }

    /**
     * Search doctors
     *
     * @param string $keyword Search keyword (name, specialty, city)
     *
     * @return array
     */
    public static function search($keyword)
    {
        $keyword = '%' . $keyword . '%';
        $query = 'SELECT DISTINCT d.* FROM ' . (new self())->getTable() . ' d 
                  JOIN users u ON d.user_id = u.id 
                  WHERE (u.full_name LIKE ? OR d.specialty LIKE ? OR d.clinic_city LIKE ? OR d.clinic_name LIKE ?) 
                  AND d.is_verified = 1';

        $results = Database::select($query, [$keyword, $keyword, $keyword, $keyword]);

        $doctors = [];
        foreach ($results as $result) {
            $doctor = new static($result);
            $doctor->exists = true;
            $doctors[] = $doctor;
        }

        return $doctors;
    }

    /**
     * Get verified doctors
     *
     * @return array
     */
    public static function getVerified()
    {
        return static::where('is_verified', 1);
    }

    /**
     * Get pending verification doctors
     *
     * @return array
     */
    public static function getPendingVerification()
    {
        return static::where('is_verified', 0);
    }

    /**
     * Get paginated doctors by specialty
     *
     * @param string $specialty Doctor specialty
     * @param int $page Page number
     * @param int $perPage Items per page
     *
     * @return array
     */
    public static function paginateBySpecialty($specialty, $page = 1, $perPage = 12)
    {
        $table = (new self())->getTable();
        $offset = ($page - 1) * $perPage;

        // Get total count
        $total = Database::count($table, ['specialty' => $specialty, 'is_verified' => 1]);

        // Get results
        $query = "SELECT * FROM $table WHERE specialty = ? AND is_verified = 1 
                  ORDER BY average_rating DESC, total_reviews DESC LIMIT $perPage OFFSET $offset";
        $results = Database::select($query, [$specialty]);

        $doctors = [];
        foreach ($results as $result) {
            $doctor = new static($result);
            $doctor->exists = true;
            $doctors[] = $doctor;
        }

        return [
            'items' => $doctors,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage) ?: 1
        ];
    }

    /**
     * Get paginated doctors by city
     *
     * @param string $city Doctor city
     * @param int $page Page number
     * @param int $perPage Items per page
     *
     * @return array
     */
    public static function paginateByCity($city, $page = 1, $perPage = 12)
    {
        $table = (new self())->getTable();
        $offset = ($page - 1) * $perPage;

        // Get total count
        $total = Database::count($table, ['clinic_city' => $city, 'is_verified' => 1]);

        // Get results
        $query = "SELECT * FROM $table WHERE clinic_city = ? AND is_verified = 1 
                  ORDER BY average_rating DESC, total_reviews DESC LIMIT $perPage OFFSET $offset";
        $results = Database::select($query, [$city]);

        $doctors = [];
        foreach ($results as $result) {
            $doctor = new static($result);
            $doctor->exists = true;
            $doctors[] = $doctor;
        }

        return [
            'items' => $doctors,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage) ?: 1
        ];
    }

    /**
     * Paginate search results
     *
     * @param string $keyword Search keyword
     * @param int $page Page number
     * @param int $perPage Items per page
     *
     * @return array
     */
    public static function paginateSearch($keyword, $page = 1, $perPage = 12)
    {
        $keyword = '%' . $keyword . '%';
        $offset = ($page - 1) * $perPage;
        $table = (new self())->getTable();

        // Get total count
        $countQuery = "SELECT COUNT(*) as count FROM $table d 
                       JOIN users u ON d.user_id = u.id 
                       WHERE (u.full_name LIKE ? OR d.specialty LIKE ? OR d.clinic_city LIKE ? OR d.clinic_name LIKE ?) 
                       AND d.is_verified = 1";
        $countResult = Database::select($countQuery, [$keyword, $keyword, $keyword, $keyword], 'row');
        $total = $countResult['count'] ?? 0;

        // Get results
        $query = "SELECT DISTINCT d.* FROM $table d 
                  JOIN users u ON d.user_id = u.id 
                  WHERE (u.full_name LIKE ? OR d.specialty LIKE ? OR d.clinic_city LIKE ? OR d.clinic_name LIKE ?) 
                  AND d.is_verified = 1 
                  ORDER BY d.average_rating DESC, d.total_reviews DESC 
                  LIMIT $perPage OFFSET $offset";
        $results = Database::select($query, [$keyword, $keyword, $keyword, $keyword]);

        $doctors = [];
        foreach ($results as $result) {
            $doctor = new static($result);
            $doctor->exists = true;
            $doctors[] = $doctor;
        }

        return [
            'items' => $doctors,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage) ?: 1
        ];
    }

    /**
     * Verify doctor
     *
     * @param int $verifiedBy Admin ID who verified
     *
     * @return bool
     */
    public function verify($verifiedBy)
    {
        $this->setAttribute('is_verified', true);
        $this->setAttribute('verified_by', $verifiedBy);
        $this->setAttribute('verified_at', date('Y-m-d H:i:s'));
        return $this->save();
    }

    /**
     * Increment profile views
     *
     * @return bool
     */
    public function incrementViews()
    {
        $views = $this->getAttribute('profile_views') ?? 0;
        $this->setAttribute('profile_views', $views + 1);
        return $this->save();
    }

    /**
     * Update rating
     *
     * @param float $rating New average rating
     * @param int $totalReviews Total review count
     *
     * @return bool
     */
    public function updateRating($rating, $totalReviews)
    {
        $this->setAttribute('average_rating', $rating);
        $this->setAttribute('total_reviews', $totalReviews);
        return $this->save();
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
     * Get doctor's full name from user
     *
     * @return string|null
     */
    public function getFullName()
    {
        $user = $this->user();
        return $user ? $user->getAttribute('full_name') : null;
    }

    /**
     * Get doctor's phone from user
     *
     * @return string|null
     */
    public function getPhone()
    {
        $user = $this->user();
        return $user ? $user->getAttribute('phone') : null;
    }

    /**
     * Get doctor's email from user
     *
     * @return string|null
     */
    public function getEmail()
    {
        $user = $this->user();
        return $user ? $user->getAttribute('email') : null;
    }

    /**
     * Check if doctor has clinic consultation available
     *
     * @return bool
     */
    public function hasClinicConsultation()
    {
        return $this->getAttribute('is_clinic_available');
    }

    /**
     * Check if doctor has video consultation available
     *
     * @return bool
     */
    public function hasVideoConsultation()
    {
        return $this->getAttribute('is_video_available');
    }

    /**
     * Get consultation fees
     *
     * @return array
     */
    public function getConsultationFees()
    {
        return [
            'clinic' => $this->getAttribute('consultation_fee'),
            'video' => $this->getAttribute('video_consultation_fee')
        ];
    }

    /**
     * Get rating as stars
     *
     * @return int (1-5)
     */
    public function getRatingStars()
    {
        return round($this->getAttribute('average_rating'));
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
            'pmdc_number' => 'required|unique:doctors,pmdc_number',
            'specialty' => 'required|in:' . implode(',', array_keys(USER_ROLES)),
            'qualification' => 'required|min:5',
            'experience_years' => 'required|integer|min:0',
            'clinic_city' => 'required',
            'consultation_fee' => 'required|numeric|min:0',
            'video_consultation_fee' => 'required|numeric|min:0'
        ];
    }
}

?>
