<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers;

use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Env;

class WebhookController extends Controller
{
    public function paymentHook(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            return;
        }

        $input = file_get_contents('php://input');
        $payload = json_decode($input, true);

        if (!$payload) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ]);
            return;
        }

        // Validate structure
        if (!isset($payload['event'], $payload['payment'], $payload['signature']) || $payload['event'] !== 'payment.completed') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => ['payload' => ['Invalid payload structure.']]
            ]);
            return;
        }

        // Verify signature
        $expected = hash('sha256', 
            $payload['payment']['merchant_order_id'] . 
            $payload['payment']['status'] . 
            $payload['payment']['amount'] . 
            $payload['payment']['id']
        );

        if ($expected !== $payload['signature']) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized',
                'errors' => ['signature' => ['Invalid signature.']]
            ]);
            return;
        }

        $invoiceCode = (string) $payload['payment']['merchant_order_id'];
        $status = strtolower((string) $payload['payment']['status']);
        $dbStatus = $this->mapPaymentStatus($status);

        try {
            $transaction = $this->db()->selectOne(
                "SELECT t.*, p.item_name, u.email as user_email "
                . "FROM transactions t "
                . "INNER JOIN products p ON p.id = t.product_id "
                . "LEFT JOIN users u ON u.id = t.user_id "
                . "WHERE t.invoice_code = :invoice_code",
                ['invoice_code' => $invoiceCode]
            );

            if (!$transaction) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Not Found',
                    'errors' => ['invoice' => ['Transaction not found.']]
                ]);
                return;
            }

            if ($status === 'paid') {
                if ($transaction['status'] !== 'Paid') {
                    $this->db()->execute(
                        "UPDATE transactions "
                        . "SET status = :status, paid_at = COALESCE(paid_at, :paid_at) "
                        . "WHERE id = :id",
                        [
                            'status' => $dbStatus,
                            'paid_at' => date('Y-m-d H:i:s'),
                            'id' => $transaction['id'],
                        ]
                    );

                    $this->db()->execute(
                        "UPDATE product_items pi "
                        . "JOIN transaction_items ti ON pi.id = ti.product_item_id "
                        . "SET pi.status = 'sold', pi.updated_at = NOW() "
                        . "WHERE ti.transaction_id = :transaction_id",
                        ['transaction_id' => $transaction['id']]
                    );

                    $emailResult = $this->sendAccountEmail($transaction, $payload);
                    if (!$emailResult['success']) {
                        error_log('Failed to send account email for invoice ' . $transaction['invoice_code'] . ': ' . $emailResult['message']);
                    }
                }
            } else {
                $this->db()->execute(
                    "UPDATE transactions SET status = :status WHERE id = :id",
                    [
                        'status' => $dbStatus,
                        'id' => $transaction['id'],
                    ]
                );
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'OK'
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Server Error',
                'errors' => ['system' => ['Internal server error.']]
            ]);
        }
    }

    private function mapPaymentStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'Paid',
            'failed', 'expired', 'cancelled' => 'Canceled',
            default => 'Pending',
        };
    }

    private function sendAccountEmail(array $transaction, array $payload): array
    {
        $toEmail = $transaction['user_email'] ?? $transaction['customer_email'] ?? $transaction['customer_no'] ?? $payload['payment']['customer_name'] ?? '';
        if (empty($toEmail)) {
            return ['success' => false, 'message' => 'No email address found.'];
        }

        $transactionItems = $this->db()->selectAll(
            "SELECT pi.account_data, p.item_name "
            . "FROM transaction_items ti "
            . "JOIN product_items pi ON pi.id = ti.product_item_id "
            . "JOIN products p ON p.id = pi.product_id "
            . "WHERE ti.transaction_id = :transaction_id",
            ['transaction_id' => $transaction['id']]
        );

        if (empty($transactionItems)) {
            return ['success' => false, 'message' => 'No account data found.'];
        }

        $itemName = $transactionItems[0]['item_name'] ?? 'Produk';
        
        $plainBody = "Terima Kasih atas Pembelian Anda!\n\n";
        $plainBody .= "Berikut adalah detail akun untuk pembelian {$itemName} (Invoice: {$transaction['invoice_code']}):\n\n";

        $htmlBody = "<h2>Terima Kasih atas Pembelian Anda!</h2>";
        $htmlBody .= "<p>Berikut adalah detail akun untuk pembelian <strong>{$itemName}</strong> (Invoice: {$transaction['invoice_code']}):</p>";
        $htmlBody .= "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse;'>";

        foreach ($transactionItems as $item) {
            $accountData = json_decode($item['account_data'], true);
            if (is_array($accountData)) {
                foreach ($accountData as $key => $value) {
                    $plainBody .= ucfirst($key) . ": " . $value . "\n";
                    $htmlBody .= "<tr><td><strong>" . htmlspecialchars(ucfirst($key)) . "</strong></td><td>" . htmlspecialchars((string)$value) . "</td></tr>";
                }
            } else {
                $plainBody .= $item['account_data'] . "\n";
                $htmlBody .= "<tr><td colspan='2'>" . htmlspecialchars((string)$item['account_data']) . "</td></tr>";
            }
            $plainBody .= "\n";
        }
        
        $htmlBody .= "</table>";
        $htmlBody .= "<p>Jika ada kendala, silakan hubungi kami.</p>";
        $plainBody .= "Jika ada kendala, silakan hubungi kami.\n";

        $emailId = Env::get('MAILRY_EMAIL_ID');
        $secretToken = Env::get('MAILRY_SECRET_TOKEN');

        if (!$emailId || !$secretToken) {
            return ['success' => false, 'message' => 'Mailry configuration is missing.'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.mailry.co/public/inbox/send');
        curl_setopt($ch, CURLOPT_POST, 1);

        $postData = [
            'emailId' => $emailId,
            'to' => $toEmail,
            'cc' => '',
            'subject' => "Detail Akun Pesanan Anda - {$transaction['invoice_code']}",
            'plainBody' => $plainBody,
            'htmlBody' => $htmlBody,
            'attachments' => ''
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $secretToken
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($err || $httpCode >= 300) {
            return ['success' => false, 'message' => "Gagal mengirim email: " . ($err ?: $response)];
        }

        return ['success' => true, 'message' => 'OK'];
    }
}
