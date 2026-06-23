<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers\Admin;

use Fahri\LapakId\Core\Controller;

class UploadController extends Controller
{
    public function categoryMedia(string $field): string
    {
        $this->requireAdmin();

        if (!in_array($field, ['icon', 'cover'], true)) {
            return $this->json(['success' => false, 'error' => 'Invalid upload field.'], 400);
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return $this->json(['success' => false, 'error' => 'No file uploaded.'], 400);
        }

        $file = $_FILES['file'];

        if (is_array($file['name'] ?? null)) {
            return $this->json(['success' => false, 'error' => 'Only one file is allowed.'], 400);
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return $this->json(['success' => false, 'error' => 'Upload failed.'], 400);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            return $this->json(['success' => false, 'error' => 'File size must be <= 2MB.'], 400);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            return $this->json(['success' => false, 'error' => 'Invalid upload.'], 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $tmpName) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
        ];

        if (!is_string($mimeType) || !array_key_exists($mimeType, $allowedMimes)) {
            return $this->json(['success' => false, 'error' => 'Only image files are allowed.'], 400);
        }

        $extension = $allowedMimes[$mimeType];
        $uuid = $this->uuidV4();

        $projectRoot = dirname(__DIR__, 3);
        $targetDir = $projectRoot . '/public/storage/uploads/categories/' . $field;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return $this->json(['success' => false, 'error' => 'Failed to create storage folder.'], 500);
        }

        $targetName = $uuid . '.' . $extension;
        $targetPath = $targetDir . '/' . $targetName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            return $this->json(['success' => false, 'error' => 'Failed to save uploaded file.'], 500);
        }

        $publicPath = '/storage/uploads/categories/' . $field . '/' . $targetName;

        return $this->json(['success' => true, 'path' => $publicPath]);
    }

    public function productItemImage(): string
    {
        $this->requireAdmin();

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return $this->json(['success' => false, 'error' => 'No file uploaded.'], 400);
        }

        $file = $_FILES['file'];

        if (is_array($file['name'] ?? null)) {
            return $this->json(['success' => false, 'error' => 'Only one file is allowed.'], 400);
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return $this->json(['success' => false, 'error' => 'Upload failed.'], 400);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return $this->json(['success' => false, 'error' => 'File size must be <= 5MB.'], 400);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            return $this->json(['success' => false, 'error' => 'Invalid upload.'], 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $tmpName) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!is_string($mimeType) || !array_key_exists($mimeType, $allowedMimes)) {
            return $this->json(['success' => false, 'error' => 'Only image files are allowed.'], 400);
        }

        $extension = $allowedMimes[$mimeType];
        $uuid = $this->uuidV4();

        $projectRoot = dirname(__DIR__, 3);
        $targetDir = $projectRoot . '/public/storage/uploads/product_items';

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return $this->json(['success' => false, 'error' => 'Failed to create storage folder.'], 500);
        }

        $targetName = $uuid . '.' . $extension;
        $targetPath = $targetDir . '/' . $targetName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            return $this->json(['success' => false, 'error' => 'Failed to save uploaded file.'], 500);
        }

        $publicPath = '/storage/uploads/product_items/' . $targetName;

        return $this->json(['success' => true, 'path' => $publicPath]);
    }

    private function json(array $payload, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');

        return json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
