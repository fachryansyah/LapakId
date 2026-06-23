<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers;

use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Flash;
use Throwable;

class HomeController extends Controller
{
    public function index(): string
    {
        $categories = [];
        $products = [];
        $databaseError = null;

        try {
            $categories = $this->db()->selectAll(
                "SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY id DESC"
            );

            foreach ($categories as &$category) {
                $category['products'] = $this->db()->selectAll(
                    "SELECT id, item_name, price FROM products "
                    . "WHERE category_id = :category_id AND deleted_at IS NULL ORDER BY price ASC",
                    ['category_id' => (int) $category['id']]
                );
            }
            unset($category);

            // Fetch products for the "Akun Pilihan" section
            $products = $this->db()->selectAll(
                "SELECT p.id, p.category_id, p.item_name, p.price, c.name as category_name, c.icon as category_icon, c.cover as category_cover "
                . "FROM products p "
                . "JOIN categories c ON p.category_id = c.id "
                . "WHERE p.deleted_at IS NULL AND c.deleted_at IS NULL "
                . "ORDER BY p.id DESC LIMIT 8"
            );
        } catch (Throwable $throwable) {
            $databaseError = $throwable->getMessage();
        }

        return $this->render('front/home.twig', [
            'title' => 'Topup Game, Pulsa, PLN & Data',
            'categories' => $categories,
            'products' => $products,
            'databaseError' => $databaseError,
        ]);
    }

    public function products(): string
    {
        $products = [];
        $filterCategories = [];
        $databaseError = null;
        $searchQuery = $this->input('q');
        $selectedCategory = $this->input('category');
        $selectedCategoryId = ctype_digit($selectedCategory) ? (int) $selectedCategory : null;

        try {
            $filterCategories = $this->db()->selectAll(
                "SELECT id, name "
                . "FROM categories "
                . "WHERE deleted_at IS NULL "
                . "ORDER BY name ASC"
            );

            $availableCategoryIds = array_map(
                static fn (array $category): int => (int) $category['id'],
                $filterCategories
            );

            if ($selectedCategoryId !== null && !in_array($selectedCategoryId, $availableCategoryIds, true)) {
                $selectedCategoryId = null;
            }

            $sql = "SELECT p.id, p.item_name, p.price, p.category_id, c.name as category_name, c.icon as category_icon, c.cover as category_cover "
                . "FROM products p "
                . "JOIN categories c ON p.category_id = c.id "
                . "WHERE p.deleted_at IS NULL AND c.deleted_at IS NULL";

            $params = [];

            if ($searchQuery !== '') {
                $searchPattern = '%' . $searchQuery . '%';

                $sql .= " AND (p.item_name LIKE :search_item "
                    . "OR c.name LIKE :search_name "
                    . "OR COALESCE(c.description, '') LIKE :search_description)";

                $params['search_item'] = $searchPattern;
                $params['search_name'] = $searchPattern;
                $params['search_description'] = $searchPattern;
            }

            if ($selectedCategoryId !== null) {
                $sql .= " AND p.category_id = :selected_category_id";
                $params['selected_category_id'] = $selectedCategoryId;
            }

            $sql .= " ORDER BY p.id DESC";

            $products = $this->db()->selectAll($sql, $params);
        } catch (Throwable $throwable) {
            $databaseError = $throwable->getMessage();
        }

        return $this->render('front/products.twig', [
            'title' => 'Semua Produk',
            'products' => $products,
            'filterCategories' => $filterCategories,
            'searchQuery' => $searchQuery,
            'selectedCategoryId' => $selectedCategoryId,
            'databaseError' => $databaseError,
        ]);
    }

    public function categoryDetail(int $id): string
    {
        try {
            $product = $this->db()->selectOne(
                "SELECT id FROM products WHERE category_id = :cid AND deleted_at IS NULL ORDER BY price ASC, id ASC LIMIT 1",
                ['cid' => $id]
            );

            if ($product) {
                $this->redirect('/product/' . $product['id']);
            }

            Flash::set('error', 'Kategori tidak memiliki produk.');
            $this->redirect('/products');
        } catch (Throwable $throwable) {
            Flash::set('error', 'Gagal memuat kategori. ' . $throwable->getMessage());
            $this->redirect('/products');
        }
        return '';
    }

    public function product(int $id): string
    {
        try {
            $product = $this->findActiveProductForFront($id);

            if ($product === null) {
                Flash::set('error', 'Produk yang dipilih tidak ditemukan.');
                $this->redirect('/products');
            }

            return $this->render('front/product.twig', [
                'title' => 'Top Up ' . $product['category_name'] . ' - ' . $product['name'],
                'product' => $product,
            ]);
        } catch (Throwable $throwable) {
            Flash::set('error', 'Gagal memuat detail produk. ' . $throwable->getMessage());
            $this->redirect('/products');
        }
        return '';
    }

    private function findActiveProductForFront(int $id): ?array
    {
        $product = $this->db()->selectOne(
            "SELECT p.id, p.item_name, p.price, p.category_id, c.name as category_name, c.icon as category_icon, c.cover as category_cover, c.description as category_description "
            . "FROM products p "
            . "JOIN categories c ON p.category_id = c.id "
            . "WHERE p.id = :id AND p.deleted_at IS NULL AND c.deleted_at IS NULL "
            . "LIMIT 1",
            ['id' => $id]
        );

        if ($product === null) {
            return null;
        }

        $productItems = $this->db()->selectAll(
            "SELECT id, description "
            . "FROM product_items "
            . "WHERE product_id = :product_id AND deleted_at IS NULL AND status = 'available' "
            . "ORDER BY id ASC",
            ['product_id' => $id]
        );

        $items = [];
        foreach ($productItems as $item) {
            $images = $this->db()->selectAll(
                "SELECT image_path FROM product_images WHERE product_item_id = :product_item_id AND deleted_at IS NULL",
                ['product_item_id' => $item['id']]
            );
            
            $items[] = [
                'id' => (int) $item['id'],
                'description' => (string) ($item['description'] ?? ''),
                'images' => array_map(function ($img) { return $img['image_path']; }, $images),
            ];
        }

        return [
            'id' => (int) $product['id'],
            'name' => (string) $product['item_name'],
            'price' => (float) $product['price'],
            'category_id' => (int) $product['category_id'],
            'category_name' => (string) $product['category_name'],
            'category_description' => (string) ($product['category_description'] ?? ''),
            'banner_url' => (string) ($product['category_cover'] ?? ''),
            'icon_url' => (string) ($product['category_icon'] ?? ''),
            'publisher' => 'LapakId',
            'items' => $items,
        ];
    }
}
