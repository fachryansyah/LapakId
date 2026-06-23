<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers\Admin;

use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Flash;
use Throwable;

class ProductController extends Controller
{
    public function index(): string
    {
        $this->requireAdmin();

        $products = [];
        $databaseError = null;

        try {
            $products = $this->db()->selectAll(
                "SELECT p.*, c.name AS category_name, "
                . "(SELECT COUNT(*) FROM product_items pi WHERE pi.product_id = p.id AND pi.deleted_at IS NULL) as total_account "
                . "FROM products p "
                . "INNER JOIN categories c ON p.category_id = c.id "
                . "WHERE p.deleted_at IS NULL AND c.deleted_at IS NULL "
                . "ORDER BY p.id DESC"
            );
        } catch (Throwable $throwable) {
            $databaseError = $throwable->getMessage();
        }

        return $this->render('admin/products/index.twig', [
            'title' => 'Products',
            'pageTitle' => 'Products',
            'pageSubtitle' => 'Kelola harga dan nominal untuk setiap produk.',
            'products' => $products,
            'databaseError' => $databaseError,
        ]);
    }

    public function create(): string
    {
        $this->requireAdmin();

        $categories = $this->getCategories();

        return $this->renderForm('create', $this->defaultFormData(), $categories);
    }

    public function store(): string
    {
        $this->requireAdmin();

        $form = $this->formDataFromRequest();
        $errors = $this->validateForm($form);
        $categories = $this->getCategories();

        if ($errors !== []) {
            return $this->renderForm('create', $form, $categories, $errors);
        }

        try {
            $this->db()->execute(
                'INSERT INTO products (category_id, item_name, price, created_at, updated_at) '
                . 'VALUES (:category_id, :item_name, :price, NOW(), NOW())',
                [
                    'category_id' => $form['category_id'],
                    'item_name' => $form['item_name'],
                    'price' => number_format((float) $form['price'], 2, '.', ''),
                ]
            );

            Flash::set('success', 'Produk berhasil dibuat.');
            $this->redirect('/admin/products');
        } catch (Throwable $throwable) {
            return $this->renderForm('create', $form, $categories, [], $throwable->getMessage());
        }
    }

    public function edit(int $id): string
    {
        $this->requireAdmin();

        try {
            $product = $this->findProduct($id);

            if ($product === null) {
                Flash::set('error', 'Produk tidak ditemukan.');
                $this->redirect('/admin/products');
            }

            $categories = $this->getCategories();
            return $this->renderForm('edit', $product, $categories, [], null, $id);
        } catch (Throwable $throwable) {
            Flash::set('error', 'Gagal memuat produk. ' . $throwable->getMessage());
            $this->redirect('/admin/products');
        }
    }

    public function update(int $id): string
    {
        $this->requireAdmin();

        $existingProduct = $this->findProduct($id);

        if ($existingProduct === null) {
            Flash::set('error', 'Produk tidak ditemukan.');
            $this->redirect('/admin/products');
        }

        $form = $this->formDataFromRequest();
        $errors = $this->validateForm($form);
        $categories = $this->getCategories();

        if ($errors !== []) {
            return $this->renderForm('edit', $form, $categories, $errors, null, $id);
        }

        try {
            $this->db()->execute(
                'UPDATE products SET category_id = :category_id, item_name = :item_name, price = :price, '
                . 'updated_at = NOW() WHERE id = :id AND deleted_at IS NULL',
                [
                    'id' => $id,
                    'category_id' => $form['category_id'],
                    'item_name' => $form['item_name'],
                    'price' => number_format((float) $form['price'], 2, '.', ''),
                ]
            );

            Flash::set('success', 'Produk berhasil diperbarui.');
            $this->redirect('/admin/products');
        } catch (Throwable $throwable) {
            return $this->renderForm('edit', $form, $categories, [], $throwable->getMessage(), $id);
        }
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        try {
            $this->db()->execute(
                'UPDATE products SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND deleted_at IS NULL',
                ['id' => $id]
            );

            Flash::set('success', 'Produk berhasil dihapus.');
        } catch (Throwable $throwable) {
            Flash::set('error', 'Gagal menghapus produk. ' . $throwable->getMessage());
        }

        $this->redirect('/admin/products');
    }

    private function renderForm(
        string $mode,
        array $form,
        array $categories,
        array $errors = [],
        ?string $databaseError = null,
        ?int $productId = null
    ): string {
        return $this->render('admin/products/form.twig', [
            'title' => $mode === 'create' ? 'Create Product' : 'Edit Product',
            'pageTitle' => $mode === 'create' ? 'Create Product' : 'Edit Product',
            'pageSubtitle' => 'Isi detail produk.',
            'formMode' => $mode,
            'submitUrl' => $mode === 'create' ? '/admin/products' : '/admin/products/' . $productId . '/update',
            'productId' => $productId,
            'form' => $form,
            'categories' => $categories,
            'errors' => $errors,
            'databaseError' => $databaseError,
        ]);
    }

    private function defaultFormData(): array
    {
        return [
            'category_id' => '',
            'item_name' => '',
            'price' => '',
        ];
    }

    private function formDataFromRequest(): array
    {
        return [
            'category_id' => $this->input('category_id'),
            'item_name' => $this->input('item_name'),
            'price' => $this->input('price'),
        ];
    }

    private function validateForm(array $form): array
    {
        $errors = [];

        if ($form['category_id'] === '') {
            $errors[] = 'Kategori wajib dipilih.';
        }

        if ($form['item_name'] === '') {
            $errors[] = 'Nama produk wajib diisi.';
        }

        if ($form['price'] === '' || !is_numeric($form['price']) || (float) $form['price'] <= 0) {
            $errors[] = 'Harga harus lebih dari nol.';
        }

        return $errors;
    }

    private function findProduct(int $id): ?array
    {
        $product = $this->db()->selectOne(
            'SELECT * FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );

        if ($product === null) {
            return null;
        }

        return [
            'category_id' => (string) $product['category_id'],
            'item_name' => (string) $product['item_name'],
            'price' => (string) $product['price'],
        ];
    }

    private function getCategories(): array
    {
        return $this->db()->selectAll(
            'SELECT id, name FROM categories WHERE deleted_at IS NULL ORDER BY name ASC'
        );
    }
}
