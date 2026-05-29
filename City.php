<?php
/**
 * Digital Sehat Ghar - City Model
 * ==============================
 * Handles cities and locations
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

require_once ROOT_PATH . 'app/models/BaseModel.php';

class City extends BaseModel
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'cities';

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
        'name',
        'district',
        'province',
        'is_active'
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
        'is_active' => 'bool'
    ];

    /**
     * Get active cities
     *
     * @return array
     */
    public static function getActive()
    {
        return static::where('is_active', 1);
    }

    /**
     * Get cities by province
     *
     * @param string $province Province name
     *
     * @return array
     */
    public static function getByProvince($province)
    {
        $cities = static::where('province', $province);
        return array_filter($cities, function ($city) {
            return $city->getAttribute('is_active');
        });
    }

    /**
     * Get city by name
     *
     * @param string $name City name
     *
     * @return static|null
     */
    public static function findByName($name)
    {
        $cities = static::where('name', $name);
        return !empty($cities) ? $cities[0] : null;
    }

    /**
     * Get all provinces
     *
     * @return array
     */
    public static function getProvinces()
    {
        $query = 'SELECT DISTINCT province FROM ' . (new self())->getTable()
            . ' WHERE is_active = 1 ORDER BY province';

        $results = Database::select($query);
        $provinces = [];

        foreach ($results as $result) {
            $provinces[] = $result['province'];
        }

        return $provinces;
    }

    /**
     * Get all districts
     *
     * @return array
     */
    public static function getDistricts()
    {
        $query = 'SELECT DISTINCT district FROM ' . (new self())->getTable()
            . ' WHERE is_active = 1 ORDER BY district';

        $results = Database::select($query);
        $districts = [];

        foreach ($results as $result) {
            if ($result['district']) {
                $districts[] = $result['district'];
            }
        }

        return $districts;
    }

    /**
     * Get city count
     *
     * @return int
     */
    public static function getTotalActive()
    {
        return Database::count((new self())->getTable(), ['is_active' => 1]);
    }

    /**
     * Activate city
     *
     * @return bool
     */
    public function activate()
    {
        $this->setAttribute('is_active', true);
        return $this->save();
    }

    /**
     * Deactivate city
     *
     * @return bool
     */
    public function deactivate()
    {
        $this->setAttribute('is_active', false);
        return $this->save();
    }

    /**
     * Validation rules
     *
     * @return array
     */
    public static function validationRules()
    {
        return [
            'name' => 'required|min:2|max:100',
            'province' => 'required'
        ];
    }
}

?>
