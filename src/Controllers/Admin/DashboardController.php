<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers\Admin;

use Fahri\LapakId\Core\Controller;
use Throwable;

class DashboardController extends Controller
{
    public function index(): string
    {
        $this->requireAdmin();

        $metrics = [
            'product_count' => 0,
            'active_product_count' => 0,
            'transaction_count' => 0,
            'user_count' => 0,
            'total_revenue' => '0.00',
        ];
        $recentTransactions = [];
        $databaseError = null;

        try {
            $metrics = $this->db()->selectOne(
                "SELECT "
                . "(SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL) AS product_count, "
                . "(SELECT COUNT(*) FROM products WHERE deleted_at IS NULL) AS active_product_count, "
                . "(SELECT COUNT(*) FROM transactions) AS transaction_count, "
                . "(SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS user_count, "
                . "(SELECT COALESCE(SUM(total_price), 0) FROM transactions) AS total_revenue"
            ) ?? $metrics;

            $recentTransactions = $this->db()->selectAll(
                "SELECT t.invoice_code, t.total_price, t.created_at, "
                . "COALESCE(u.fullname, 'Checkout Tamu') AS customer_name, "
                . "p.item_name, c.name AS category_name "
                . "FROM transactions t "
                . "LEFT JOIN users u ON u.id = t.user_id "
                . "INNER JOIN products p ON p.id = t.product_id "
                . "INNER JOIN categories c ON c.id = p.category_id "
                . "ORDER BY t.created_at DESC LIMIT 6"
            );
        } catch (Throwable $throwable) {
            $databaseError = $throwable->getMessage();
        }

        return $this->render('admin/dashboard.twig', [
            'title' => 'Dashboard',
            'pageTitle' => 'Dashboard',
            'pageSubtitle' => 'Ringkasan inventaris produk dan aktivitas transaksi.',
            'metrics' => $metrics,
            'recentTransactions' => $recentTransactions,
            'databaseError' => $databaseError,
        ]);
    }
}
