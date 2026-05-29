<?php
/**
 * Digital Sehat Ghar - Form Validator & Sanitizer
 * ================================================
 * Handles form validation, sanitization, and error management
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

class Validator
{
    /**
     * Store validation errors
     *
     * @var array
     */
    private $errors = [];

    /**
     * Store validated data
     *
     * @var array
     */
    private $validated = [];

    /**
     * Store original data
     *
     * @var array
     */
    private $data = [];

    /**
     * Validation rules
     *
     * @var array
     */
    private $rules = [];

    /**
     * Constructor
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     */
    public function __construct($data = [], $rules = [])
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Validate the data against rules
     *
     * @return bool True if validation passes
     */
    public function validate()
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $fieldRules) {
            $rules = explode('|', $fieldRules);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply a validation rule
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     *
     * @return void
     */
    private function applyRule($field, $value, $rule)
    {
        $rule = trim($rule);

        // Parse rule with parameters
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        switch ($ruleName) {
            case 'required':
                $this->validateRequired($field, $value);
                break;

            case 'email':
                $this->validateEmail($field, $value);
                break;

            case 'phone':
                $this->validatePhone($field, $value);
                break;

            case 'cnic':
                $this->validateCnic($field, $value);
                break;

            case 'url':
                $this->validateUrl($field, $value);
                break;

            case 'min':
                $this->validateMin($field, $value, $params[0] ?? 0);
                break;

            case 'max':
                $this->validateMax($field, $value, $params[0] ?? 255);
                break;

            case 'length':
                $this->validateLength($field, $value, $params[0] ?? 0);
                break;

            case 'numeric':
                $this->validateNumeric($field, $value);
                break;

            case 'integer':
                $this->validateInteger($field, $value);
                break;

            case 'decimal':
                $this->validateDecimal($field, $value, $params[0] ?? 2);
                break;

            case 'alpha':
                $this->validateAlpha($field, $value);
                break;

            case 'alphanumeric':
                $this->validateAlphanumeric($field, $value);
                break;

            case 'password':
                $this->validatePassword($field, $value);
                break;

            case 'confirmed':
                $this->validateConfirmed($field, $value, $params[0] ?? null);
                break;

            case 'unique':
                $this->validateUnique($field, $value, $params[0] ?? null, $params[1] ?? null);
                break;

            case 'in':
                $this->validateIn($field, $value, $params);
                break;

            case 'date':
                $this->validateDate($field, $value);
                break;

            case 'date_before':
                $this->validateDateBefore($field, $value, $params[0] ?? null);
                break;

            case 'date_after':
                $this->validateDateAfter($field, $value, $params[0] ?? null);
                break;

            case 'image':
                $this->validateImage($field, $value, $params);
                break;

            case 'file':
                $this->validateFile($field, $value, $params);
                break;

            case 'regex':
                $this->validateRegex($field, $value, $params[0] ?? '');
                break;
        }
    }

    /**
     * Required field validation
     */
    private function validateRequired($field, $value)
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }

    /**
     * Email validation
     */
    private function validateEmail($field, $value)
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a valid email');
        }
    }

    /**
     * Phone validation (Pakistan format)
     */
    private function validatePhone($field, $value)
    {
        if ($value && !preg_match(REGEX_PATTERNS['phone_pakistan'], $value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a valid phone number');
        }
    }

    /**
     * CNIC validation (Pakistan format)
     */
    private function validateCnic($field, $value)
    {
        if ($value && !preg_match(REGEX_PATTERNS['cnic'], $value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be in format: 12345-1234567-1');
        }
    }

    /**
     * URL validation
     */
    private function validateUrl($field, $value)
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a valid URL');
        }
    }

    /**
     * Minimum length validation
     */
    private function validateMin($field, $value, $min)
    {
        if ($value && strlen($value) < $min) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be at least $min characters");
        }
    }

    /**
     * Maximum length validation
     */
    private function validateMax($field, $value, $max)
    {
        if ($value && strlen($value) > $max) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " cannot exceed $max characters");
        }
    }

    /**
     * Exact length validation
     */
    private function validateLength($field, $value, $length)
    {
        if ($value && strlen($value) !== (int)$length) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be exactly $length characters");
        }
    }

    /**
     * Numeric validation
     */
    private function validateNumeric($field, $value)
    {
        if ($value && !is_numeric($value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be numeric');
        }
    }

    /**
     * Integer validation
     */
    private function validateInteger($field, $value)
    {
        if ($value && !is_int($value) && !ctype_digit((string)$value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be an integer');
        }
    }

    /**
     * Decimal validation
     */
    private function validateDecimal($field, $value, $decimals)
    {
        if ($value && !preg_match("/^\d+(\.\d{1," . $decimals . "})?$/", $value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must have up to $decimals decimal places");
        }
    }

    /**
     * Alpha characters only validation
     */
    private function validateAlpha($field, $value)
    {
        if ($value && !preg_match('/^[a-zA-Z\s]+$/', $value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must contain only letters');
        }
    }

    /**
     * Alphanumeric validation
     */
    private function validateAlphanumeric($field, $value)
    {
        if ($value && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be alphanumeric');
        }
    }

    /**
     * Strong password validation
     */
    private function validatePassword($field, $value)
    {
        if (!$value) {
            return;
        }

        $errors = [];

        if (strlen($value) < PASSWORD_MIN_LENGTH) {
            $errors[] = "at least " . PASSWORD_MIN_LENGTH . " characters";
        }

        if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $value)) {
            $errors[] = "one uppercase letter";
        }

        if (PASSWORD_REQUIRE_NUMBERS && !preg_match('/[0-9]/', $value)) {
            $errors[] = "one number";
        }

        if (PASSWORD_REQUIRE_SPECIAL_CHARS && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value)) {
            $errors[] = "one special character";
        }

        if ($errors) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must contain ' . implode(', ', $errors));
        }
    }

    /**
     * Field confirmation validation (e.g., password confirmation)
     */
    private function validateConfirmed($field, $value, $confirmField)
    {
        $confirmField = $confirmField ?: $field . '_confirmation';
        $confirmValue = $this->data[$confirmField] ?? null;

        if ($value && $value !== $confirmValue) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must match');
        }
    }

    /**
     * Unique value validation (check in database)
     */
    private function validateUnique($field, $value, $table, $column)
    {
        if (!$value || !$table) {
            return;
        }

        $column = $column ?: $field;

        try {
            if (Database::exists($table, [$column => $value])) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' already exists');
            }
        } catch (Exception $e) {
            Logger::error('Unique validation error: ' . $e->getMessage());
        }
    }

    /**
     * In array validation
     */
    private function validateIn($field, $value, $options)
    {
        if ($value && !in_array($value, $options)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is invalid');
        }
    }

    /**
     * Date validation
     */
    private function validateDate($field, $value)
    {
        if ($value && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a valid date (YYYY-MM-DD)');
        }
    }

    /**
     * Date before validation
     */
    private function validateDateBefore($field, $value, $beforeDate)
    {
        if ($value && $beforeDate && strtotime($value) >= strtotime($beforeDate)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be before $beforeDate");
        }
    }

    /**
     * Date after validation
     */
    private function validateDateAfter($field, $value, $afterDate)
    {
        if ($value && $afterDate && strtotime($value) <= strtotime($afterDate)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be after $afterDate");
        }
    }

    /**
     * Image file validation
     */
    private function validateImage($field, $value, $params)
    {
        if (!$value || !is_array($value) || !isset($value['tmp_name'])) {
            return;
        }

        $extension = pathinfo($value['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), ALLOWED_IMAGE_TYPES)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be an image (JPG, PNG, WebP, GIF)');
        }

        if ($value['size'] > MAX_UPLOAD_SIZE_BYTES) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' exceeds maximum file size');
        }
    }

    /**
     * File validation
     */
    private function validateFile($field, $value, $params)
    {
        if (!$value || !is_array($value) || !isset($value['tmp_name'])) {
            return;
        }

        $extension = pathinfo($value['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), ALLOWED_DOCUMENT_TYPES)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' file type not allowed');
        }

        if ($value['size'] > MAX_UPLOAD_SIZE_BYTES) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' exceeds maximum file size');
        }
    }

    /**
     * Regex validation
     */
    private function validateRegex($field, $value, $pattern)
    {
        if ($value && !preg_match($pattern, $value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' format is invalid');
        }
    }

    /**
     * Add error message
     *
     * @param string $field Field name
     * @param string $message Error message
     *
     * @return void
     */
    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get all errors
     *
     * @return array Validation errors
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     *
     * @param string $field Field name
     *
     * @return array Field errors
     */
    public function getFieldErrors($field)
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if field has errors
     *
     * @param string $field Field name
     *
     * @return bool
     */
    public function hasError($field)
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get validated data
     *
     * @return array
     */
    public function validated()
    {
        return $this->validated;
    }

    /**
     * Get value from validated data
     *
     * @param string $key Key name
     * @param mixed $default Default value
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->validated[$key] ?? $default;
    }

    /**
     * Sanitize input data
     *
     * @param string $type Sanitization type
     *
     * @return array Sanitized data
     */
    public function sanitize($type = 'string')
    {
        $sanitized = [];

        foreach ($this->data as $key => $value) {
            $sanitized[$key] = self::sanitizeValue($value, $type);
        }

        return $sanitized;
    }

    /**
     * Sanitize a single value
     *
     * @param mixed $value Value to sanitize
     * @param string $type Sanitization type
     *
     * @return mixed Sanitized value
     */
    public static function sanitizeValue($value, $type = 'string')
    {
        if (is_array($value)) {
            return array_map(function ($v) use ($type) {
                return self::sanitizeValue($v, $type);
            }, $value);
        }

        switch ($type) {
            case 'string':
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            case 'email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($value, FILTER_SANITIZE_URL);
            case 'number':
                return is_numeric($value) ? $value : 0;
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
                return (bool)$value;
            case 'strip_tags':
                return strip_tags($value);
            default:
                return $value;
        }
    }

    /**
     * Static validation shortcut
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     *
     * @return Validator
     */
    public static function make($data, $rules)
    {
        return new self($data, $rules);
    }
}

?>
