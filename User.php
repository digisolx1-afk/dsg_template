<?php
/**
 * Digital Sehat Ghar - User Model
 * ==============================
 * Handles user data and authentication
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

require_once ROOT_PATH . 'app/models/BaseModel.php';

class User extends BaseModel
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'users';

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
        'email',
        'password_hash',
        'full_name',
        'phone',
        'cnic',
        'role',
        'profile_photo',
        'is_active',
        'email_verified',
        'phone_verified',
        'last_login'
    ];

    /**
     * Hidden attributes
     *
     * @var array
     */
    protected $hidden = ['password_hash'];

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
        'is_active' => 'bool',
        'email_verified' => 'bool',
        'phone_verified' => 'bool'
    ];

    /**
     * Find user by email
     *
     * @param string $email User email
     *
     * @return static|null
     */
    public static function findByEmail($email)
    {
        $users = static::where('email', $email);
        return !empty($users) ? $users[0] : null;
    }

    /**
     * Find user by phone
     *
     * @param string $phone User phone
     *
     * @return static|null
     */
    public static function findByPhone($phone)
    {
        $users = static::where('phone', $phone);
        return !empty($users) ? $users[0] : null;
    }

    /**
     * Find user by CNIC
     *
     * @param string $cnic User CNIC
     *
     * @return static|null
     */
    public static function findByCnic($cnic)
    {
        $users = static::where('cnic', $cnic);
        return !empty($users) ? $users[0] : null;
    }

    /**
     * Hash password
     *
     * @param string $password Plain password
     *
     * @return string
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password
     *
     * @param string $password Plain password
     * @param string $hash Password hash
     *
     * @return bool
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Set password
     *
     * @param string $password Plain password
     *
     * @return void
     */
    public function setPassword($password)
    {
        $this->setAttribute('password_hash', self::hashPassword($password));
    }

    /**
     * Verify user password
     *
     * @param string $password Plain password
     *
     * @return bool
     */
    public function verifyUserPassword($password)
    {
        return self::verifyPassword($password, $this->getAttribute('password_hash'));
    }

    /**
     * Get users by role
     *
     * @param string $role User role
     *
     * @return array
     */
    public static function getByRole($role)
    {
        return static::where('role', $role);
    }

    /**
     * Get active users
     *
     * @return array
     */
    public static function getActive()
    {
        return static::where('is_active', 1);
    }

    /**
     * Verify email
     *
     * @return bool
     */
    public function verifyEmail()
    {
        $this->setAttribute('email_verified', true);
        return $this->save();
    }

    /**
     * Verify phone
     *
     * @return bool
     */
    public function verifyPhone()
    {
        $this->setAttribute('phone_verified', true);
        return $this->save();
    }

    /**
     * Update last login
     *
     * @return bool
     */
    public function updateLastLogin()
    {
        $this->setAttribute('last_login', date('Y-m-d H:i:s'));
        return $this->save();
    }

    /**
     * Deactivate user
     *
     * @return bool
     */
    public function deactivate()
    {
        $this->setAttribute('is_active', false);
        return $this->save();
    }

    /**
     * Activate user
     *
     * @return bool
     */
    public function activate()
    {
        $this->setAttribute('is_active', true);
        return $this->save();
    }

    /**
     * Check if user is verified (email & phone)
     *
     * @return bool
     */
    public function isVerified()
    {
        return $this->getAttribute('email_verified') && $this->getAttribute('phone_verified');
    }

    /**
     * Check if user is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->getAttribute('is_active');
    }

    /**
     * Check if user is doctor
     *
     * @return bool
     */
    public function isDoctor()
    {
        return $this->getAttribute('role') === 'doctor';
    }

    /**
     * Check if user is patient
     *
     * @return bool
     */
    public function isPatient()
    {
        return $this->getAttribute('role') === 'patient';
    }

    /**
     * Check if user is admin
     *
     * @return bool
     */
    public function isAdmin()
    {
        return in_array($this->getAttribute('role'), ['super_admin', 'sub_admin']);
    }

    /**
     * Get user's role label
     *
     * @return string
     */
    public function getRoleLabel()
    {
        return USER_ROLES[$this->getAttribute('role')] ?? 'Unknown';
    }

    /**
     * Validation rules
     *
     * @return array
     */
    public static function validationRules()
    {
        return [
            'email' => 'required|email',
            'full_name' => 'required|min:3|max:100',
            'phone' => 'required|phone',
            'password_hash' => 'required|min:8',
            'role' => 'required|in:patient,doctor,super_admin,sub_admin,support_staff'
        ];
    }
}

?>
