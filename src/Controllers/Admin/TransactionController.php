<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers\Admin;

use Fahri\LapakId\Core\Controller;
use Throwable;

class TransactionController extends Controller
{
    public function index(): string
    {
        $this->requireAdmin();

        $transactions = [];
        $databaseError = null;

        try {
            $transactions = $this->db()->selectAll(
                "SELECT t.invoice_code, t.total_price, t.created_at, t.status, "
                . "COALESCE(u.fullname, 'Checkout Tamu') AS customer_name, "
                . "COALESCE(u.email, '-') AS customer_email, "
                . "p.item_name, c.name AS category_name "
                . "FROM transactions t "
                . "LEFT JOIN users u ON u.id = t.user_id "
                . "INNER JOIN products p ON p.id = t.product_id "
                . "INNER JOIN categories c ON c.id = p.category_id "
                . "ORDER BY t.created_at DESC"
            );
        } catch (Throwable $throwable) {
            $databaseError = $throwable->getMessage();
        }

        return $this->render('admin/transactions/index.twig', [
            'title' => 'Transactions',
            'pageTitle' => 'Transactions',
            'pageSubtitle' => 'Pantau riwayat pembelian dari checkout tamu dan pengguna terdaftar.',
            'transactions' => $transactions,
            'databaseError' => $databaseError,
        ]);
    }
}
