<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Product;

use App\Domain\Product\Product;
use App\Domain\Product\ProductNotFoundException;
use App\Domain\Product\ProductRepository;
use PDO;

class ProductDatabase implements ProductRepository
{

    /**
     * @var db
     */
    private $db;

    /**
     * @var Product[]
     */
    private $products;

    /**
     * ProductDatabase constructor.
     *
     * @param array|null $products
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;

    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array
    {
        $query = $this->db->prepare("SELECT * FROM products ORDER BY id ASC LIMIT 5000");
        $query->execute();
        $this->products = $query->fetchAll(PDO::FETCH_ASSOC);
        return array_values($this->products);

    }

    /**
     * {@inheritdoc}
     */
    public function findProductOfId(int $id): Product
    {
        $query = $this->db->prepare("SELECT * FROM products WHERE id = ?");
        $query->execute(array($id));
        $product = $query->fetchAll(PDO::FETCH_ASSOC);


        if (!isset($product[0])) {
            throw new ProductNotFoundException();
        }

        $this->products = new Product((int)$product[0]['id'], $product[0]['sku'], $product[0]['attributes']);
        return $this->products;

    }
}
