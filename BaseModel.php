<?php
/**
 * Digital Sehat Ghar - Base Model Class
 * ====================================
 * Abstract base class for all models providing common ORM functionality
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

abstract class BaseModel
{
    /**
     * Table name (must be defined in child classes)
     *
     * @var string
     */
    protected $table = '';

    /**
     * Primary key column name
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Model attributes/data
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Model original attributes (for detecting changes)
     *
     * @var array
     */
    protected $originalAttributes = [];

    /**
     * Timestamps enabled
     *
     * @var bool
     */
    protected $timestamps = true;

    /**
     * Timestamp columns
     *
     * @var array
     */
    protected $timestamps_columns = ['created_at', 'updated_at'];

    /**
     * Hidden attributes (not included in toArray)
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Fillable attributes (allowed for mass assignment)
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * Guarded attributes (not allowed for mass assignment)
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Casts for attributes
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Model exists in database
     *
     * @var bool
     */
    protected $exists = false;

    /**
     * Constructor
     *
     * @param array $attributes Model attributes
     */
    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill model with attributes
     *
     * @param array $attributes Attributes to fill
     *
     * @return $this
     */
    public function fill($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        $this->originalAttributes = $this->attributes;
        return $this;
    }

    /**
     * Set attribute value
     *
     * @param string $key Attribute name
     * @param mixed $value Attribute value
     *
     * @return void
     */
    public function setAttribute($key, $value)
    {
        // Check if attribute is fillable
        if (!empty($this->fillable) && !in_array($key, $this->fillable)) {
            return;
        }

        // Check if attribute is guarded
        if (in_array($key, $this->guarded)) {
            return;
        }

        // Apply casting
        if (isset($this->casts[$key])) {
            $value = $this->castValue($value, $this->casts[$key]);
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Get attribute value
     *
     * @param string $key Attribute name
     * @param mixed $default Default value
     *
     * @return mixed
     */
    public function getAttribute($key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Magic getter
     */
    public function __get($name)
    {
        return $this->getAttribute($name);
    }

    /**
     * Magic setter
     */
    public function __set($name, $value)
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Check if attribute exists
     */
    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Get all attributes
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Get all original attributes
     *
     * @return array
     */
    public function getOriginalAttributes()
    {
        return $this->originalAttributes;
    }

    /**
     * Check if model has been changed
     *
     * @param string $key Specific key to check (optional)
     *
     * @return bool
     */
    public function isDirty($key = null)
    {
        if ($key) {
            return $this->getAttribute($key) !== $this->originalAttributes[$key] ?? null;
        }

        return $this->attributes !== $this->originalAttributes;
    }

    /**
     * Get changed attributes
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if ($value !== ($this->originalAttributes[$key] ?? null)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Cast value to specified type
     *
     * @param mixed $value Value to cast
     * @param string $type Cast type
     *
     * @return mixed
     */
    private function castValue($value, $type)
    {
        switch ($type) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'float':
            case 'double':
                return (float)$value;
            case 'string':
                return (string)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'array':
                return is_array($value) ? $value : json_decode($value, true);
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
            case 'date':
                return new DateTime($value);
            case 'timestamp':
                return strtotime($value);
            default:
                return $value;
        }
    }

    /**
     * Get the table name
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get primary key column name
     *
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Get primary key value
     *
     * @return mixed
     */
    public function getPrimaryKeyValue()
    {
        return $this->getAttribute($this->primaryKey);
    }

    /**
     * Check if model exists in database
     *
     * @return bool
     */
    public function exists()
    {
        return $this->exists;
    }

    /**
     * Create a new record
     *
     * @param array $attributes Attributes to create
     *
     * @return static|false
     */
    public static function create($attributes = [])
    {
        $model = new static($attributes);
        return $model->save() ? $model : false;
    }

    /**
     * Save model to database (insert or update)
     *
     * @return bool
     */
    public function save()
    {
        try {
            if ($this->exists) {
                return $this->update();
            } else {
                return $this->insert();
            }
        } catch (Exception $e) {
            Logger::error('Model save failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert new record
     *
     * @return bool
     */
    protected function insert()
    {
        try {
            // Add timestamps if enabled
            if ($this->timestamps) {
                $this->setAttribute('created_at', date('Y-m-d H:i:s'));
                $this->setAttribute('updated_at', date('Y-m-d H:i:s'));
            }

            // Get attributes to insert
            $data = $this->getAttributesToSave();

            if (empty($data)) {
                throw new Exception('No data to insert');
            }

            // Insert record
            $id = Database::insert($this->table, $data);

            if ($id) {
                $this->setAttribute($this->primaryKey, $id);
                $this->exists = true;
                $this->originalAttributes = $this->attributes;
                return true;
            }

            return false;

        } catch (Exception $e) {
            Logger::error('Insert failed in ' . $this->table . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update existing record
     *
     * @return bool
     */
    protected function update()
    {
        try {
            // Check if primary key exists
            if (!$this->getPrimaryKeyValue()) {
                throw new Exception('Cannot update model without primary key');
            }

            // Update timestamps if enabled
            if ($this->timestamps) {
                $this->setAttribute('updated_at', date('Y-m-d H:i:s'));
            }

            // Get dirty attributes
            $dirty = $this->getDirty();

            if (empty($dirty)) {
                return true; // No changes to update
            }

            // Update record
            $result = Database::update(
                $this->table,
                $dirty,
                [$this->primaryKey => $this->getPrimaryKeyValue()]
            );

            if ($result) {
                $this->originalAttributes = $this->attributes;
                return true;
            }

            return false;

        } catch (Exception $e) {
            Logger::error('Update failed in ' . $this->table . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete record from database
     *
     * @return bool
     */
    public function delete()
    {
        try {
            if (!$this->getPrimaryKeyValue()) {
                throw new Exception('Cannot delete model without primary key');
            }

            $result = Database::delete(
                $this->table,
                [$this->primaryKey => $this->getPrimaryKeyValue()]
            );

            if ($result) {
                $this->exists = false;
                return true;
            }

            return false;

        } catch (Exception $e) {
            Logger::error('Delete failed in ' . $this->table . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Find a record by primary key
     *
     * @param mixed $id Primary key value
     *
     * @return static|null
     */
    public static function find($id)
    {
        $model = new static();
        $result = Database::select(
            'SELECT * FROM ' . $model->getTable() . ' WHERE ' . $model->getPrimaryKey() . ' = ?',
            [$id],
            'row'
        );

        if ($result) {
            $model->fill($result);
            $model->exists = true;
            return $model;
        }

        return null;
    }

    /**
     * Find or fail
     *
     * @param mixed $id Primary key value
     *
     * @return static
     * @throws Exception
     */
    public static function findOrFail($id)
    {
        $model = static::find($id);

        if (!$model) {
            throw new Exception(static::class . ' not found with ID: ' . $id);
        }

        return $model;
    }

    /**
     * Get all records
     *
     * @return array
     */
    public static function all()
    {
        $model = new static();
        $results = Database::select('SELECT * FROM ' . $model->getTable());

        $models = [];
        foreach ($results as $result) {
            $instance = new static($result);
            $instance->exists = true;
            $models[] = $instance;
        }

        return $models;
    }

    /**
     * Get records where condition matches
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     *
     * @return array
     */
    public static function where($column, $operator = '=', $value = null)
    {
        // Handle shorthand syntax: where('column', 'value')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $model = new static();
        $query = 'SELECT * FROM ' . $model->getTable() . ' WHERE ' . $column . ' ' . $operator . ' ?';

        $results = Database::select($query, [$value]);

        $models = [];
        foreach ($results as $result) {
            $instance = new static($result);
            $instance->exists = true;
            $models[] = $instance;
        }

        return $models;
    }

    /**
     * Get first record
     *
     * @return static|null
     */
    public static function first()
    {
        $model = new static();
        $result = Database::select(
            'SELECT * FROM ' . $model->getTable() . ' LIMIT 1',
            [],
            'row'
        );

        if ($result) {
            $instance = new static($result);
            $instance->exists = true;
            return $instance;
        }

        return null;
    }

    /**
     * Get last record
     *
     * @return static|null
     */
    public static function last()
    {
        $model = new static();
        $result = Database::select(
            'SELECT * FROM ' . $model->getTable() . ' ORDER BY ' . $model->getPrimaryKey() . ' DESC LIMIT 1',
            [],
            'row'
        );

        if ($result) {
            $instance = new static($result);
            $instance->exists = true;
            return $instance;
        }

        return null;
    }

    /**
     * Count records
     *
     * @param array $where Where conditions (optional)
     *
     * @return int
     */
    public static function count($where = [])
    {
        $model = new static();
        return Database::count($model->getTable(), $where);
    }

    /**
     * Check if record exists
     *
     * @param array $where Where conditions
     *
     * @return bool
     */
    public static function exists_record($where = [])
    {
        $model = new static();
        return Database::exists($model->getTable(), $where);
    }

    /**
     * Get paginated results
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param string $orderBy Order by column
     * @param string $direction Sort direction (ASC/DESC)
     *
     * @return array ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => 10]
     */
    public static function paginate($page = 1, $perPage = 10, $orderBy = 'id', $direction = 'ASC')
    {
        $model = new static();
        $table = $model->getTable();

        // Get total count
        $total = Database::count($table);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Get results
        $query = "SELECT * FROM $table ORDER BY $orderBy $direction LIMIT $perPage OFFSET $offset";
        $results = Database::select($query);

        $models = [];
        foreach ($results as $result) {
            $instance = new static($result);
            $instance->exists = true;
            $models[] = $instance;
        }

        return [
            'items' => $models,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage) ?: 1
        ];
    }

    /**
     * Get attributes to save (excluding guarded columns)
     *
     * @return array
     */
    protected function getAttributesToSave()
    {
        $data = [];

        foreach ($this->attributes as $key => $value) {
            // Skip guarded columns
            if (in_array($key, $this->guarded)) {
                continue;
            }

            // Skip empty primary key for new records
            if ($key === $this->primaryKey && !$value) {
                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Convert model to array
     *
     * @return array
     */
    public function toArray()
    {
        $array = $this->attributes;

        // Remove hidden attributes
        foreach ($this->hidden as $attr) {
            unset($array[$attr]);
        }

        return $array;
    }

    /**
     * Convert model to JSON
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Magic toString
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Validate model data
     *
     * @param array $rules Validation rules
     *
     * @return bool
     */
    public function validate($rules = [])
    {
        $validator = Validator::make($this->attributes, $rules);
        return $validator->validate();
    }

    /**
     * Get validation errors
     *
     * @param array $rules Validation rules
     *
     * @return array
     */
    public function getValidationErrors($rules = [])
    {
        $validator = Validator::make($this->attributes, $rules);
        $validator->validate();
        return $validator->errors();
    }
}

?>
