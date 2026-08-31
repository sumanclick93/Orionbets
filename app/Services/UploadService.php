<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class UploadService
{
    private string $uploadBasePath;

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'image/svg+xml' => ['svg'],
        'image/x-icon' => ['ico'],
        'image/vnd.microsoft.icon' => ['ico'],
    ];

    public function __construct(?string $basePath = null)
    {
        $this->uploadBasePath = $basePath ?? (dirname(__DIR__, 2) . '/public/assets/uploads');
    }

    /**
     * Upload an image file securely.
     *
     * @param array<string, mixed> $file Element from $_FILES or $request->file($key)
     * @param string $subfolder Subdirectory inside uploads (e.g. 'cms', 'avatars', 'branding')
     * @param int $maxSizeBytes Maximum allowed size in bytes (default 10MB)
     * @return string Relative web URL path to the uploaded file (e.g. '/assets/uploads/cms/logo_abc123.png')
     */
    public function uploadImage(array $file, string $subfolder = 'images', int $maxSizeBytes = 10485760): string
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->getErrorMessage($errorCode));
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize <= 0 || $fileSize > $maxSizeBytes) {
            $maxMb = round($maxSizeBytes / (1024 * 1024), 1);
            throw new RuntimeException("File is too large. Maximum allowed size is {$maxMb}MB.");
        }

        $clientName = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        // Detect real MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        // SVG files may sometimes be detected as text/plain or text/xml
        if ($ext === 'svg' && (str_contains($mime, 'svg') || str_contains($mime, 'xml') || $mime === 'text/plain')) {
            $mime = 'image/svg+xml';
        }

        if (!array_key_exists($mime, self::ALLOWED_IMAGE_MIMES)) {
            // Allow extension-based match if valid image extension
            $validExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'];
            if (!in_array($ext, $validExts, true)) {
                throw new RuntimeException('Unsupported image file format. Allowed: JPG, PNG, WEBP, GIF, SVG, ICO.');
            }
        } else {
            $allowedForMime = self::ALLOWED_IMAGE_MIMES[$mime];
            if (!in_array($ext, $allowedForMime, true)) {
                $ext = $allowedForMime[0];
            }
        }

        $sanitizedSubfolder = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '', $subfolder) ?: 'images', '/');
        $targetDir = rtrim($this->uploadBasePath, '/') . '/' . $sanitizedSubfolder;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Failed to create upload directory: {$targetDir}");
        }

        $fileName = bin2hex(random_bytes(10)) . '_' . time() . '.' . $ext;
        $destination = $targetDir . '/' . $fileName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Failed to move uploaded file to destination.');
        }

        return '/assets/uploads/' . $sanitizedSubfolder . '/' . $fileName;
    }

    /**
     * Delete an uploaded file if it resides inside public/assets/uploads.
     */
    public function deleteFile(?string $relativePath): bool
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return false;
        }

        $clean = parse_url($relativePath, PHP_URL_PATH) ?: $relativePath;
        $clean = ltrim($clean, '/');

        if (!str_starts_with($clean, 'assets/uploads/')) {
            return false;
        }

        $sub = substr($clean, strlen('assets/uploads/'));
        $sub = str_replace(['..', '\\'], '', $sub);
        $fullPath = rtrim($this->uploadBasePath, '/') . '/' . ltrim($sub, '/');

        if (is_file($fullPath)) {
            return @unlink($fullPath);
        }

        return false;
    }

    private function getErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder for file uploads.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error occurred.',
        };
    }
}
