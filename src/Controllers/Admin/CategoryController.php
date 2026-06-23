<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers\Admin;

use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Flash;
use Throwable;

class CategoryController extends Controller
{
    public function index(): string
    {
        $this->requireAdmin();

        $categories = [];
        $databaseError = null;

        try {
            $categories = $this->db()->selectAll(
                "SELECT c.*, COUNT(p.id) AS item_count, MIN(p.price) AS starting_price "
                . "FROM categories c "
                . "LEFT JOIN products p ON p.category_id = c.id AND p.deleted_at IS NULL "
                . "WHERE c.deleted_at IS NULL "
                . "GROUP BY c.id "
                . "ORDER BY c.id DESC"
            );
        } catch (Throwable $throwable) {
            $databaseError = $throwable->getMessage();
        }

        return $this->render('admin/categories/index.twig', [
            'title' => 'Categories',
            'pageTitle' => 'Category Management',
            'pageSubtitle' => 'Kelola kategori seperti game topup, pulsa, token PLN, dan paket data.',
            'categories' => $categories,
            'databaseError' => $databaseError,
        ]);
    }

    public function create(): string
    {
        $this->requireAdmin();

        return $this->renderForm('create', $this->defaultFormData());
    }

    public function store(): string
    {
        $this->requireAdmin();

        $form = $this->formDataFromRequest();
        $errors = $this->validateCategoryForm($form);

        if ($errors !== []) {
            return $this->renderForm('create', $form, $errors);
        }

        $connection = $this->db()->getConnection();

        try {
            $connection->beginTransaction();

            $this->db()->execute(
                'INSERT INTO categories (name, icon, cover, description, created_at, updated_at) '
                . 'VALUES (:name, :icon, :cover, :description, NOW(), NOW())',
                [
                    'name' => $form['name'],
                    'icon' => $form['icon'] !== '' ? $form['icon'] : null,
                    'cover' => $form['cover'] !== '' ? $form['cover'] : null,
                    'description' => $form['description'] !== '' ? $form['description'] : null,
                ]
            );

            $connection->commit();
            Flash::set('success', 'Kategori berhasil dibuat.');
            $this->redirect('/admin/categories');
        } catch (Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return $this->renderForm('create', $form, [], $throwable->getMessage());
        }
    }

    public function edit(int $id): string
    {
        $this->requireAdmin();

        try {
            $category = $this->findCategory($id);

            if ($category === null) {
                Flash::set('error', 'Kategori tidak ditemukan.');
                $this->redirect('/admin/categories');
            }

            return $this->renderForm('edit', $category, [], null, $id);
        } catch (Throwable $throwable) {
            Flash::set('error', 'Gagal memuat kategori. ' . $throwable->getMessage());
            $this->redirect('/admin/categories');
        }
    }

    public function update(int $id): string
    {
        $this->requireAdmin();

        $existingCategory = $this->findCategory($id);

        if ($existingCategory === null) {
            Flash::set('error', 'Kategori tidak ditemukan.');
            $this->redirect('/admin/categories');
        }

        $form = $this->formDataFromRequest();
        $errors = $this->validateCategoryForm($form);

        if ($errors !== []) {
            return $this->renderForm('edit', $form, $errors, null, $id);
        }

        $connection = $this->db()->getConnection();

        try {
            $connection->beginTransaction();

            $this->db()->execute(
                'UPDATE categories SET name = :name, icon = :icon, cover = :cover, description = :description, '
                . 'updated_at = NOW() WHERE id = :id AND deleted_at IS NULL',
                [
                    'id' => $id,
                    'name' => $form['name'],
                    'icon' => $form['icon'] !== '' ? $form['icon'] : null,
                    'cover' => $form['cover'] !== '' ? $form['cover'] : null,
                    'description' => $form['description'] !== '' ? $form['description'] : null,
                ]
            );

            $connection->commit();
            Flash::set('success', 'Kategori berhasil diperbarui.');
            $this->redirect('/admin/categories');
        } catch (Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return $this->renderForm('edit', $form, [], $throwable->getMessage(), $id);
        }
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        $connection = $this->db()->getConnection();

        try {
            $connection->beginTransaction();

            $this->db()->execute(
                'UPDATE categories SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND deleted_at IS NULL',
                ['id' => $id]
            );
            $this->db()->execute(
                'UPDATE products SET deleted_at = NOW(), updated_at = NOW() '
                . 'WHERE category_id = :category_id AND deleted_at IS NULL',
                ['category_id' => $id]
            );

            $connection->commit();
            Flash::set('success', 'Kategori berhasil dihapus.');
        } catch (Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            Flash::set('error', 'Gagal menghapus kategori. ' . $throwable->getMessage());
        }

        $this->redirect('/admin/categories');
    }

    private function renderForm(
        string $mode,
        array $form,
        array $errors = [],
        ?string $databaseError = null,
        ?int $categoryId = null
    ): string {
        return $this->render('admin/categories/form.twig', [
            'title' => $mode === 'create' ? 'Create Category' : 'Edit Category',
            'pageTitle' => $mode === 'create' ? 'Create Category' : 'Edit Category',
            'pageSubtitle' => 'Isi detail kategori.',
            'formMode' => $mode,
            'submitUrl' => $mode === 'create' ? '/admin/categories' : '/admin/categories/' . $categoryId . '/update',
            'categoryId' => $categoryId,
            'form' => $form,
            'errors' => $errors,
            'databaseError' => $databaseError,
        ]);
    }

    private function defaultFormData(): array
    {
        return [
            'name' => '',
            'icon' => '',
            'cover' => '',
            'description' => '',
        ];
    }

    private function formDataFromRequest(): array
    {
        return [
            'name' => $this->input('name'),
            'icon' => $this->input('icon'),
            'cover' => $this->input('cover'),
            'description' => $this->input('description'),
        ];
    }

    private function validateCategoryForm(array $form): array
    {
        $errors = [];

        if ($form['name'] === '') {
            $errors[] = 'Nama kategori wajib diisi.';
        }

        return $errors;
    }

    private function findCategory(int $id): ?array
    {
        $category = $this->db()->selectOne(
            'SELECT * FROM categories WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );

        if ($category === null) {
            return null;
        }

        return [
            'name' => (string) $category['name'],
            'icon' => (string) ($category['icon'] ?? ''),
            'cover' => (string) ($category['cover'] ?? ''),
            'description' => (string) ($category['description'] ?? ''),
        ];
    }
}
