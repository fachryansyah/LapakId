<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers\Admin;

use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Flash;
use Throwable;

class ProductItemController extends Controller
{
    public function index(int $productId): string
    {
        $this->requireAdmin();

        $productItems = [];
        $databaseError = null;
        $product = null;

        try {
            $product = $this->findProduct($productId);
            if ($product === null) {
                Flash::set('error', 'Produk tidak ditemukan.');
                $this->redirect('/admin/products');
            }

            $productItems = $this->db()->selectAll(
                "SELECT pi.*, p.item_name, c.name AS category_name "
                . "FROM product_items pi "
                . "INNER JOIN products p ON pi.product_id = p.id "
                . "INNER JOIN categories c ON p.category_id = c.id "
                . "WHERE pi.deleted_at IS NULL AND pi.product_id = :product_id "
                . "ORDER BY pi.id DESC",
                ['product_id' => $productId]
            );
        } catch (Throwable $throwable) {
            $databaseError = $throwable->getMessage();
        }

        return $this->render('admin/product_items/index.twig', [
            'title' => 'Product Items',
            'pageTitle' => 'Product Items',
            'pageSubtitle' => 'Kelola data akun untuk produk: ' . ($product['item_name'] ?? ''),
            'productItems' => $productItems,
            'databaseError' => $databaseError,
            'productId' => $productId,
            'product' => $product,
        ]);
    }

    public function create(int $productId): string
    {
        $this->requireAdmin();
        $product = $this->findProduct($productId);
        if ($product === null) {
            Flash::set('error', 'Produk tidak ditemukan.');
            $this->redirect('/admin/products');
        }

        return $this->renderForm('create', $this->defaultFormData(), $productId, $product);
    }

    public function store(int $productId): string
    {
        $this->requireAdmin();
        $product = $this->findProduct($productId);
        if ($product === null) {
            Flash::set('error', 'Produk tidak ditemukan.');
            $this->redirect('/admin/products');
        }

        $form = $this->formDataFromRequest();
        $errors = $this->validateForm($form);

        if ($errors !== []) {
            return $this->renderForm('create', $form, $productId, $product, $errors);
        }

        try {
            $accountDataJson = json_encode(['data' => $form['account_data']], JSON_THROW_ON_ERROR);

            $this->db()->execute(
                'INSERT INTO product_items (product_id, account_data, status, description, created_at, updated_at) '
                . 'VALUES (:product_id, :account_data, :status, :description, NOW(), NOW())',
                [
                    'product_id' => $productId,
                    'account_data' => $accountDataJson,
                    'status' => $form['status'],
                    'description' => $form['description'],
                ]
            );

            $productItemId = $this->db()->lastInsertId();

            if (is_array($form['images'])) {
                foreach ($form['images'] as $imagePath) {
                    if (is_string($imagePath) && $imagePath !== '') {
                        $this->db()->execute(
                            'INSERT INTO product_images (product_item_id, image_path, created_at, updated_at) VALUES (:product_item_id, :image_path, NOW(), NOW())',
                            [
                                'product_item_id' => $productItemId,
                                'image_path' => $imagePath,
                            ]
                        );
                    }
                }
            }

            Flash::set('success', 'Item produk berhasil ditambahkan.');
            $this->redirect('/admin/products/' . $productId . '/items');
        } catch (Throwable $throwable) {
            return $this->renderForm('create', $form, $productId, $product, [], $throwable->getMessage());
        }
    }

    public function edit(int $productId, int $id): string
    {
        $this->requireAdmin();
        $product = $this->findProduct($productId);
        if ($product === null) {
            Flash::set('error', 'Produk tidak ditemukan.');
            $this->redirect('/admin/products');
        }

        try {
            $item = $this->findProductItem($id, $productId);

            if ($item === null) {
                Flash::set('error', 'Item produk tidak ditemukan.');
                $this->redirect('/admin/products/' . $productId . '/items');
            }

            return $this->renderForm('edit', $item, $productId, $product, [], null, $id);
        } catch (Throwable $throwable) {
            Flash::set('error', 'Gagal memuat item produk. ' . $throwable->getMessage());
            $this->redirect('/admin/products/' . $productId . '/items');
        }
    }

    public function update(int $productId, int $id): string
    {
        $this->requireAdmin();
        $product = $this->findProduct($productId);
        if ($product === null) {
            Flash::set('error', 'Produk tidak ditemukan.');
            $this->redirect('/admin/products');
        }

        $existingItem = $this->findProductItem($id, $productId);

        if ($existingItem === null) {
            Flash::set('error', 'Item produk tidak ditemukan.');
            $this->redirect('/admin/products/' . $productId . '/items');
        }

        $form = $this->formDataFromRequest();
        $errors = $this->validateForm($form);

        if ($errors !== []) {
            return $this->renderForm('edit', $form, $productId, $product, $errors, null, $id);
        }

        try {
            $accountDataJson = json_encode(['data' => $form['account_data']], JSON_THROW_ON_ERROR);

            $this->db()->execute(
                'UPDATE product_items SET account_data = :account_data, '
                . 'status = :status, description = :description, updated_at = NOW() WHERE id = :id AND product_id = :product_id AND deleted_at IS NULL',
                [
                    'id' => $id,
                    'product_id' => $productId,
                    'account_data' => $accountDataJson,
                    'status' => $form['status'],
                    'description' => $form['description'],
                ]
            );

            // Update images
            $existingImages = $this->db()->selectAll(
                'SELECT id, image_path FROM product_images WHERE product_item_id = :product_item_id AND deleted_at IS NULL',
                ['product_item_id' => $id]
            );

            $existingPaths = [];
            foreach ($existingImages as $img) {
                $existingPaths[$img['image_path']] = $img['id'];
            }

            $submittedPaths = [];
            if (is_array($form['images'])) {
                foreach ($form['images'] as $path) {
                    if (is_string($path) && $path !== '') {
                        $submittedPaths[] = $path;
                    }
                }
            }

            // Delete removed images
            foreach ($existingPaths as $path => $imageId) {
                if (!in_array($path, $submittedPaths, true)) {
                    $this->db()->execute(
                        'UPDATE product_images SET deleted_at = NOW() WHERE id = :id',
                        ['id' => $imageId]
                    );
                }
            }

            // Insert new images
            foreach ($submittedPaths as $path) {
                if (!isset($existingPaths[$path])) {
                    $this->db()->execute(
                        'INSERT INTO product_images (product_item_id, image_path, created_at, updated_at) VALUES (:product_item_id, :image_path, NOW(), NOW())',
                        [
                            'product_item_id' => $id,
                            'image_path' => $path,
                        ]
                    );
                }
            }

            Flash::set('success', 'Item produk berhasil diperbarui.');
            $this->redirect('/admin/products/' . $productId . '/items');
        } catch (Throwable $throwable) {
            return $this->renderForm('edit', $form, $productId, $product, [], $throwable->getMessage(), $id);
        }
    }

    public function delete(int $productId, int $id): void
    {
        $this->requireAdmin();

        try {
            $this->db()->execute(
                'UPDATE product_items SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND product_id = :product_id AND deleted_at IS NULL',
                ['id' => $id, 'product_id' => $productId]
            );

            Flash::set('success', 'Item produk berhasil dihapus.');
        } catch (Throwable $throwable) {
            Flash::set('error', 'Gagal menghapus item produk. ' . $throwable->getMessage());
        }

        $this->redirect('/admin/products/' . $productId . '/items');
    }

    private function renderForm(
        string $mode,
        array $form,
        int $productId,
        array $product,
        array $errors = [],
        ?string $databaseError = null,
        ?int $itemId = null
    ): string {
        return $this->render('admin/product_items/form.twig', [
            'title' => $mode === 'create' ? 'Create Product Item' : 'Edit Product Item',
            'pageTitle' => $mode === 'create' ? 'Create Product Item' : 'Edit Product Item',
            'pageSubtitle' => 'Isi detail data akun untuk produk: ' . $product['item_name'],
            'formMode' => $mode,
            'submitUrl' => $mode === 'create' ? '/admin/products/' . $productId . '/items' : '/admin/products/' . $productId . '/items/' . $itemId . '/update',
            'itemId' => $itemId,
            'form' => $form,
            'productId' => $productId,
            'product' => $product,
            'errors' => $errors,
            'databaseError' => $databaseError,
        ]);
    }

    private function defaultFormData(): array
    {
        return [
            'account_data' => '',
            'status' => 'available',
            'description' => '',
            'images' => [],
        ];
    }

    private function formDataFromRequest(): array
    {
        return [
            'account_data' => $this->input('account_data'),
            'status' => $this->input('status', 'available'),
            'description' => $this->input('description'),
            'images' => $_POST['images'] ?? [],
        ];
    }

    private function validateForm(array $form): array
    {
        $errors = [];

        if ($form['account_data'] === '') {
            $errors[] = 'Data akun wajib diisi.';
        }

        if (!in_array($form['status'], ['available', 'sold', 'checking'])) {
            $errors[] = 'Status tidak valid.';
        }

        return $errors;
    }

    private function findProductItem(int $id, int $productId): ?array
    {
        $item = $this->db()->selectOne(
            'SELECT * FROM product_items WHERE id = :id AND product_id = :product_id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'product_id' => $productId]
        );

        if ($item === null) {
            return null;
        }

        $accountData = json_decode($item['account_data'], true);
        $accountText = $accountData['data'] ?? '';

        $images = $this->db()->selectAll(
            'SELECT * FROM product_images WHERE product_item_id = :product_item_id AND deleted_at IS NULL ORDER BY id ASC',
            ['product_item_id' => $id]
        );

        $imagePaths = array_map(function ($img) {
            return $img['image_path'];
        }, $images);

        return [
            'account_data' => $accountText,
            'status' => (string) $item['status'],
            'description' => $item['description'] ?? '',
            'images' => $imagePaths,
        ];
    }

    private function findProduct(int $id): ?array
    {
        return $this->db()->selectOne(
            'SELECT p.id, p.item_name, c.name AS category_name '
            . 'FROM products p '
            . 'INNER JOIN categories c ON p.category_id = c.id '
            . 'WHERE p.id = :id AND p.deleted_at IS NULL AND c.deleted_at IS NULL '
            . 'LIMIT 1',
            ['id' => $id]
        );
    }
}
