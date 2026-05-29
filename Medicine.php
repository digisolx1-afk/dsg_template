<?php
/**
 * Digital Sehat Ghar - Medicine Model
 * ==================================
 * Handles pharmaceutical products and medicines
 *
 * @version 1.0.0
 * @author Rizwanullah
 */

require_once ROOT_PATH . 'app/models/BaseModel.php';

class Medicine extends BaseModel
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'medicines';

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
        'generic_name',
        'category',
        'strength',
        'dosage_form',
        'manufacturer',
        'price',
        'requires_prescription',
        'stock_quantity',
        'main_image',
        'thumbnail_image',
        'image',
        'box_image',
        'additional_images',
        'description',
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
        'requires_prescription' => 'bool',
        'is_active' => 'bool',
        'price' => 'float',
        'stock_quantity' => 'int',
        'additional_images' => 'json'
    ];

    /**
     * Get active medicines
     *
     * @return array
     */
    public static function getActive()
    {
        return static::where('is_active', 1);
    }

    /**
     * Get medicines by category
     *
     * @param string $category Medicine category
     *
     * @return array
     */
    public static function getByCategory($category)
    {
        $medicines = static::where('category', $category);
        return array_filter($medicines, function ($medicine) {
            return $medicine->getAttribute('is_active');
        });
    }

    /**
     * Search medicines
     *
     * @param string $keyword Search keyword (name, generic name, category)
     *
     * @return array
     */
    public static function search($keyword)
    {
        $keyword = '%' . $keyword . '%';
        $query = 'SELECT * FROM ' . (new self())->getTable()
            . ' WHERE (name LIKE ? OR generic_name LIKE ? OR category LIKE ? OR manufacturer LIKE ?) 
                  AND is_active = 1';

        $results = Database::select($query, [$keyword, $keyword, $keyword, $keyword]);

        $medicines = [];
        foreach ($results as $result) {
            $medicine = new static($result);
            $medicine->exists = true;
            $medicines[] = $medicine;
        }

        return $medicines;
    }

    /**
     * Get medicines with low stock
     *
     * @param int $threshold Stock threshold
     *
     * @return array
     */
    public static function getLowStock($threshold = 100)
    {
        $query = 'SELECT * FROM ' . (new self())->getTable()
            . ' WHERE stock_quantity < ? AND is_active = 1';

        $results = Database::select($query, [$threshold]);

        $medicines = [];
        foreach ($results as $result) {
            $medicine = new static($result);
            $medicine->exists = true;
            $medicines[] = $medicine;
        }

        return $medicines;
    }

    /**
     * Get out of stock medicines
     *
     * @return array
     */
    public static function getOutOfStock()
    {
        return static::where('stock_quantity', '=', 0);
    }

    /**
     * Paginate medicines
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param string $orderBy Order by column
     * @param string $direction Sort direction
     *
     * @return array
     */
    public static function paginateActive($page = 1, $perPage = 12, $orderBy = 'name', $direction = 'ASC')
    {
        $table = (new self())->getTable();
        $offset = ($page - 1) * $perPage;

        // Get total count
        $total = Database::count($table, ['is_active' => 1]);

        // Get results
        $query = "SELECT * FROM $table WHERE is_active = 1 ORDER BY $orderBy $direction LIMIT $perPage OFFSET $offset";
        $results = Database::select($query);

        $medicines = [];
        foreach ($results as $result) {
            $medicine = new static($result);
            $medicine->exists = true;
            $medicines[] = $medicine;
        }

        return [
            'items' => $medicines,
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
        $countQuery = "SELECT COUNT(*) as count FROM $table 
                       WHERE (name LIKE ? OR generic_name LIKE ? OR category LIKE ? OR manufacturer LIKE ?) 
                       AND is_active = 1";
        $countResult = Database::select($countQuery, [$keyword, $keyword, $keyword, $keyword], 'row');
        $total = $countResult['count'] ?? 0;

        // Get results
        $query = "SELECT * FROM $table 
                  WHERE (name LIKE ? OR generic_name LIKE ? OR category LIKE ? OR manufacturer LIKE ?) 
                  AND is_active = 1 
                  ORDER BY name ASC 
                  LIMIT $perPage OFFSET $offset";
        $results = Database::select($query, [$keyword, $keyword, $keyword, $keyword]);

        $medicines = [];
        foreach ($results as $result) {
            $medicine = new static($result);
            $medicine->exists = true;
            $medicines[] = $medicine;
        }

        return [
            'items' => $medicines,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage) ?: 1
        ];
    }

    /**
     * Paginate by category
     *
     * @param string $category Medicine category
     * @param int $page Page number
     * @param int $perPage Items per page
     *
     * @return array
     */
    public static function paginateByCategory($category, $page = 1, $perPage = 12)
    {
        $table = (new self())->getTable();
        $offset = ($page - 1) * $perPage;

        // Get total count
        $total = Database::count($table, ['category' => $category, 'is_active' => 1]);

        // Get results
        $query = "SELECT * FROM $table WHERE category = ? AND is_active = 1 ORDER BY name ASC LIMIT $perPage OFFSET $offset";
        $results = Database::select($query, [$category]);

        $medicines = [];
        foreach ($results as $result) {
            $medicine = new static($result);
            $medicine->exists = true;
            $medicines[] = $medicine;
        }

        return [
            'items' => $medicines,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage) ?: 1
        ];
    }

    /**
     * Check if medicine requires prescription
     *
     * @return bool
     */
    public function requiresPrescription()
    {
        return $this->getAttribute('requires_prescription');
    }

    /**
     * Check if in stock
     *
     * @return bool
     */
    public function isInStock()
    {
        return $this->getAttribute('stock_quantity') > 0;
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
     * Update stock quantity
     *
     * @param int $quantity Quantity to add/subtract
     *
     * @return bool
     */
    public function updateStock($quantity)
    {
        $currentStock = $this->getAttribute('stock_quantity') ?? 0;
        $this->setAttribute('stock_quantity', $currentStock + $quantity);
        return $this->save();
    }

    /**
     * Deduct stock
     *
     * @param int $quantity Quantity to deduct
     *
     * @return bool
     */
    public function deductStock($quantity)
    {
        if ($this->getAttribute('stock_quantity') < $quantity) {
            return false; // Not enough stock
        }

        return $this->updateStock(-$quantity);
    }

    /**
     * Get all categories
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
     * Get all manufacturers
     *
     * @return array
     */
    public static function getManufacturers()
    {
        $query = 'SELECT DISTINCT manufacturer FROM ' . (new self())->getTable()
            . ' WHERE is_active = 1 ORDER BY manufacturer';

        $results = Database::select($query);
        $manufacturers = [];

        foreach ($results as $result) {
            $manufacturers[] = $result['manufacturer'];
        }

        return $manufacturers;
    }

    /**
     * Validation rules
     *
     * @return array
     */
    public static function validationRules()
    {
        return [
            'name' => 'required|min:3|max:255',
            'generic_name' => 'required|min:3',
            'category' => 'required',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'manufacturer' => 'required|min:2'
        ];
    }
}

?>
