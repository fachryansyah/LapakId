<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers;

use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Env;
use Fahri\LapakId\Core\Flash;
use Fahri\LapakId\Core\Auth;
use Throwable;

class CheckoutController extends Controller
{
    public function store(): void
    {
        try {
            if (!Auth::check()) {
                Flash::set('error', 'Silakan login terlebih dahulu untuk melakukan pembelian.');
                $this->redirect('/login');
            }

            $user = Auth::user();
            $email = $user['email'] ?? '';
            $productId = (int) $this->input('product_id');
            $productItemId = (int) $this->input('product_item_id');
            $paymentMethod = $this->input('payment_method');

            if ($email === '' || $productId <= 0 || $productItemId <= 0 || $paymentMethod === '') {
                Flash::set('error', 'Semua kolom pembelian wajib diisi.');
                $this->redirectBack();
            }

            // Find the product
            $product = $this->db()->selectOne(
                "SELECT p.id, p.price, c.name AS category_name "
                . "FROM products p "
                . "JOIN categories c ON c.id = p.category_id "
                . "WHERE p.id = :id AND p.deleted_at IS NULL",
                ['id' => $productId]
            );

            if ($product === null) {
                Flash::set('error', 'Produk tidak ditemukan atau tidak aktif.');
                $this->redirectBack();
            }

            // Verify product item is valid and available
            $productItem = $this->db()->selectOne(
                "SELECT id FROM product_items WHERE id = :id AND product_id = :product_id AND status = 'available' AND deleted_at IS NULL",
                ['id' => $productItemId, 'product_id' => $productId]
            );

            if ($productItem === null) {
                Flash::set('error', 'Akun/spesifikasi yang dipilih tidak tersedia.');
                $this->redirectBack();
            }

            // Generate Invoice Code
            $invoiceCode = 'INV-' . strtoupper(substr(uniqid(), -5)) . rand(100, 999);
            $totalPrice = (float) $product['price'];
            $discount = 0.0;
            $customerNo = $email;

            // Prepare Payment Data
            $qrisContent = null;
            $checkoutUrl = null;

            if ($paymentMethod === 'qris') {
                $paymentUrl = Env::get('PAYMENT_API_URL');
                $paymentKey = Env::get('PAYMENT_API_KEY');

                if (empty($paymentUrl) || empty($paymentKey)) {
                    Flash::set('error', 'Konfigurasi pembayaran belum diatur.');
                    $this->redirectBack();
                }

                $amount = $totalPrice;

                $payload = [
                    'merchant_order_id' => $invoiceCode,
                    'amount' => $amount,
                    'customer_name' => $email,
                    'callback_url' => $this->absoluteUrl('/payment/hook'),
                    'return_url' => $this->absoluteUrl('/payment/success'),
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $paymentUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-API-Key: ' . $paymentKey,
                ]);
                
                $response = curl_exec($ch);
                $err = curl_error($ch);
                curl_close($ch);

                if ($err) {
                    Flash::set('error', 'Gagal terhubung ke gateway pembayaran.');
                    $this->redirectBack();
                }

                $responseData = json_decode($response, true);

                if (isset($responseData['success']) && $responseData['success'] === true && isset($responseData['data'])) {
                    $qrisContent = $responseData['data']['qr_string'] ?? null;
                    $checkoutUrl = $responseData['data']['payment_url'] ?? null;
                } else {
                    $errorMsg = $responseData['message'] ?? 'Gagal membuat transaksi pembayaran.';
                    Flash::set('error', $errorMsg);
                    $this->redirectBack();
                }
            }

            // Insert into transactions
            $this->db()->execute(
                "INSERT INTO transactions (user_id, product_id, invoice_code, total_price, status, qris_code) "
                . "VALUES (:user_id, :product_id, :invoice_code, :total_price, :status, :qris_code)",
                [
                    'user_id' => $user['id'] ?? null,
                    'product_id' => $productId,
                    'qris_code' => $qrisContent,
                    'invoice_code' => $invoiceCode,
                    'total_price' => $totalPrice,
                    'status' => 'Pending',
                ]
            );

            $transactionId = $this->db()->getConnection()->lastInsertId();

            // Insert into transaction_items
            $this->db()->execute(
                "INSERT INTO transaction_items (transaction_id, product_item_id, created_at, updated_at) "
                . "VALUES (:transaction_id, :product_item_id, NOW(), NOW())",
                [
                    'transaction_id' => $transactionId,
                    'product_item_id' => $productItemId,
                ]
            );

            // Change item status to checking/sold to reserve it
            $this->db()->execute(
                "UPDATE product_items SET status = 'checking', updated_at = NOW() WHERE id = :id",
                ['id' => $productItemId]
            );

            // Redirect to invoice page
            $this->redirect('/invoice/' . $invoiceCode);

        } catch (Throwable $e) {
            Flash::set('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
            $this->redirectBack();
        }
    }

    public function invoice(string $invoiceCode): string
    {
        try {
            $transaction = $this->db()->selectOne(
                "SELECT t.*, p.item_name, c.name AS category_name "
                . "FROM transactions t "
                . "JOIN products p ON p.id = t.product_id "
                . "JOIN categories c ON c.id = p.category_id "
                . "WHERE t.invoice_code = :invoice_code",
                ['invoice_code' => $invoiceCode]
            );

            if ($transaction === null) {
                Flash::set('error', 'Invoice tidak ditemukan.');
                $this->redirect('/');
            }

            return $this->render('front/invoice.twig', [
                'title' => 'Invoice ' . $invoiceCode,
                'transaction' => $transaction,
            ]);
        } catch (Throwable $e) {
            Flash::set('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
            $this->redirect('/');
        }
    }

    public function invoiceStatus(string $invoiceCode): void
    {
        header('Content-Type: application/json');
        try {
            $transaction = $this->db()->selectOne(
                "SELECT status FROM transactions WHERE invoice_code = :invoice_code",
                ['invoice_code' => $invoiceCode]
            );

            if ($transaction === null) {
                echo json_encode(['success' => false, 'message' => 'Invoice tidak ditemukan.']);
                return;
            }

            echo json_encode(['success' => true, 'status' => $transaction['status']]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem.']);
        }
    }

    private function redirectBack(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    private function absoluteUrl(string $path): string
    {
        $baseUrl = rtrim((string) Env::get('APP_URL', ''), '/');

        if ($baseUrl === '') {
            $https = $_SERVER['HTTPS'] ?? '';
            $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $scheme . '://' . $host;
        }

        $normalizedPath = '/' . ltrim($path, '/');

        return $baseUrl . $normalizedPath;
    }
}
