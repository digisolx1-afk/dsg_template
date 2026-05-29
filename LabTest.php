<?php
/**
 * Digital Sehat Ghar - Lab Test Model
 * ==================================
 * Handles laboratory tests and services
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

require_once ROOT_PATH . 'app/models/BaseModel.php';

class LabTest extends BaseModel
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'lab_tests';

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
        'test_name',
        'category',
        'description',
        'preparation_instructions',
        'price',
        'home_collection_available',
        'report_time_hours',
        'image',
        'is_active'
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
        'home_collection_available' => 'bool',
        'is_active' => 'bool',
        'price' => 'float',
        'report_time_hours' => 'int'
    ];

    /**
     * Get active tests
     *
     * @return array
     */
    public static function getActive()
    {
        return static::where('is_active', 1);
    }

    /**
     * Get tests by category
     *
     * @param string $category Test category
     *
     * @return array
     */
    public static function getByCategory($category)
    {
        $tests = static::where('category', $category);
        return array_filter($tests, function ($test) {
            return $test->getAttribute('is_active');
        });
    }

    /**
     * Get tests with home collection available
     *
     * @return array
     */
    public static function getWithHomeCollection()
    {
        $query = 'SELECT * FROM ' . (new self())->getTable()
            . ' WHERE home_collection_available = 1 AND is_active = 1';

        $results = Database::select($query);

        $tests = [];
        foreach ($results as $result) {
            $test = new static($result);
            $test->exists = true;
            $tests[] = $test;
        }

        return $tests;
    }

    /**
     * Search tests
     *
     * @param string $keyword Search keyword
     *
     * @return array
     */
    public static function search($keyword)
    {
        $keyword = '%' . $keyword . '%';
        $query = 'SELECT * FROM ' . (new self())->getTable()
            . ' WHERE (test_name LIKE ? OR category LIKE ? OR description LIKE ?) 
                  AND is_active = 1';

        $results = Database::select($query, [$keyword, $keyword, $keyword]);

        $tests = [];
        foreach ($results as $result) {
            $test = new static($result);
            $test->exists = true;
            $tests[] = $test;
        }

        return $tests;
    }

    /**
     * Paginate tests per category
     *
     * @param string $category Test category
     * @param int $page Page number
     * @param int $perPage Items per page (20 for tests)
     *
     * @return array
     */
    public static function paginateByCategory($category, $page = 1, $perPage = 20)
    {
        $table = (new self())->getTable();
        $offset = ($page - 1) * $perPage;

        // Get total count
        $total = Database::count($table, ['category' => $category, 'is_active' => 1]);

        // Get results
        $query = "SELECT * FROM $table WHERE category = ? AND is_active = 1 ORDER BY test_name ASC LIMIT $perPage OFFSET $offset";
        $results = Database::select($query, [$category]);

        $tests = [];
        foreach ($results as $result) {
            $test = new static($result);
            $test->exists = true;
            $tests[] = $test;
        }

        return [
            'items' => $tests,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage) ?: 1
        ];
    }

    /**
     * Paginate search results (20 per page)
     *
     * @param string $keyword Search keyword
     * @param int $page Page number
     * @param int $perPage Items per page
     *
     * @return array
     */
    public static function paginateSearch($keyword, $page = 1, $perPage = 20)
    {
        $keyword = '%' . $keyword . '%';
        $offset = ($page - 1) * $perPage;
        $table = (new self())->getTable();

        // Get total count
        $countQuery = "SELECT COUNT(*) as count FROM $table 
                       WHERE (test_name LIKE ? OR category LIKE ? OR description LIKE ?) 
                       AND is_active = 1";
        $countResult = Database::select($countQuery, [$keyword, $keyword, $keyword], 'row');
        $total = $countResult['count'] ?? 0;

        // Get results
        $query = "SELECT * FROM $table 
                  WHERE (test_name LIKE ? OR category LIKE ? OR description LIKE ?) 
                  AND is_active = 1 
                  ORDER BY test_name ASC 
                  LIMIT $perPage OFFSET $offset";
        $results = Database::select($query, [$keyword, $keyword, $keyword]);

        $tests = [];
        foreach ($results as $result) {
            $test = new static($result);
            $test->exists = true;
            $tests[] = $test;
        }

        return [
            'items' => $tests,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage) ?: 1
        ];
    }

    /**
     * Get all test categories
     *
     * @return array
     */
    public static function getCategories()
    {
        $query = 'SELECT DISTINCT category FROM ' . (new self())->getTable()
            . ' WHERE is_active = 1 ORDER BY category';

        $results = Database::select($query);
        $categories = [];

        foreach ($results as $result) {
            $categories[] = $result['category'];
        }

        return $categories;
    }

    /**
     * Check if home collection is available
     *
     * @return bool
     */
    public function hasHomeCollection()
    {
        return $this->getAttribute('home_collection_available');
    }

    /**
     * Get price with currency
     *
     * @return string
     */
    public function getPriceFormatted()
    {
        $price = $this->getAttribute('price');
        return CURRENCY_SYMBOL . number_format($price, CURRENCY_DECIMALS);
    }

    /**
     * Get report time in hours
     *
     * @return int
     */
    public function getReportTimeHours()
    {
        return $this->getAttribute('report_time_hours') ?? 24;
    }

    /**
     * Get report time formatted
     *
     * @return string
     */
    public function getReportTimeFormatted()
    {
        $hours = $this->getReportTimeHours();

        if ($hours < 24) {
            return $hours . ' hours';
        } else {
            $days = ceil($hours / 24);
            return $days . ' day' . ($days > 1 ? 's' : '');
        }
    }

    /**
     * Get preparation instructions as array
     *
     * @return array
     */
    public function getPreparationInstructions()
    {
        $instructions = $this->getAttribute('preparation_instructions');

        if (is_array($instructions)) {
            return $instructions;
        }

        // Parse as simple text if not array
        if ($instructions) {
            return explode("\n", $instructions);
        }

        return [];
    }

    /**
     * Get discount price
     *
     * @param float $discountPercent Discount percentage
     *
     * @return float
     */
    public function getDiscountedPrice($discountPercent)
    {
        $price = $this->getAttribute('price');
        return $price - ($price * $discountPercent / 100);
    }

    /**
     * Validation rules
     *
     * @return array
     */
    public static function validationRules()
    {
        return [
            'test_name' => 'required|min:3|max:255',
            'category' => 'required',
            'price' => 'required|numeric|min:0',
            'report_time_hours' => 'required|integer|min:1'
        ];
    }
}

?>
