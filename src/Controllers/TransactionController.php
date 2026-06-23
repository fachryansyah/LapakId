<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers;

use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Auth;
use Throwable;

class TransactionController extends Controller
{
    public function index(): string
    {
        if (!Auth::check()) {
            \Fahri\LapakId\Core\Flash::set('error', 'Silakan login terlebih dahulu.');
            $this->redirect('/login');
        }

        $user = Auth::user();
        $transactions = [];
        $error = null;

        try {
            $transactions = $this->db()->selectAll(
                "SELECT t.invoice_code, t.total_price, t.created_at, t.status, "
                . "p.item_name, c.name AS category_name "
                . "FROM transactions t "
                . "INNER JOIN products p ON p.id = t.product_id "
                . "INNER JOIN categories c ON c.id = p.category_id "
                . "WHERE t.user_id = :user_id "
                . "ORDER BY t.created_at DESC",
                ['user_id' => $user['id']]
            );
        } catch (Throwable $throwable) {
            $error = 'Terjadi kesalahan saat memuat transaksi: ' . $throwable->getMessage();
        }

        return $this->render('front/transaction.twig', [
            'title' => 'Riwayat Transaksi',
            'transactions' => $transactions,
            'error' => $error,
        ]);
    }
}
