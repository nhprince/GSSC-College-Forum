<?php
declare(strict_types=1);

/**
 * Handle a secure file upload.
 *
 * @param  array  $file     Entry from $_FILES['fieldname']
 * @param  string $subdir   Subdirectory under uploads/ e.g. 'storage', 'posts', 'avatars'
 * @param  array  $allowed  Allowed extensions (overrides ALLOWED_FILE_TYPES if set)
 * @param  int    $maxSize  Max bytes (default: UPLOAD_MAX_SIZE constant)
 * @return array  ['file_path', 'file_name', 'file_type', 'file_size']
 * @throws RuntimeException on any validation or storage failure
 */
function handleUpload(array $file, string $subdir, array $allowed = [], int $maxSize = 0): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
        ];
        throw new RuntimeException($msgs[$file['error']] ?? 'Upload error.');
    }

    $maxSize  = $maxSize ?: UPLOAD_MAX_SIZE;
    $allowed  = $allowed ?: ALLOWED_FILE_TYPES;

    if ($file['size'] > $maxSize) {
        throw new RuntimeException('File too large. Maximum size: ' . round($maxSize / 1048576, 1) . ' MB.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('File type not allowed: .' . $ext);
    }

    // Secondary MIME check
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($file['tmp_name']);
    $safeMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'application/x-zip-compressed',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
    if (!in_array($mime, $safeMimes, true)) {
        throw new RuntimeException('File content type is not permitted.');
    }

    // Random filename  never trust the original
    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destDir    = __DIR__ . '/../uploads/' . trim($subdir, '/') . '/';
    $destPath   = $destDir . $storedName;

    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            throw new RuntimeException('Could not create upload directory.');
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    return [
        'file_path' => trim($subdir, '/') . '/' . $storedName,
        'file_name' => basename($file['name']),
        'file_type' => $ext,
        'file_size' => (int)$file['size'],
    ];
}

/**
 * Resize and crop an image to a square (for avatars).
 * Requires GD extension.
 */
function resizeAvatar(string $path, int $size = 200): bool
{
    if (!function_exists('imagecreatefromjpeg')) return false;

    $info = @getimagesize($path);
    if (!$info) return false;

    [$w, $h] = $info;
    $mime     = $info['mime'];

    $src = match($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png'  => @imagecreatefrompng($path),
        'image/webp' => @imagecreatefromwebp($path),
        default      => false,
    };
    if (!$src) return false;

    // Crop to square from center
    $side   = min($w, $h);
    $x      = (int)(($w - $side) / 2);
    $y      = (int)(($h - $side) / 2);
    $canvas = imagecreatetruecolor($size, $size);

    // Preserve transparency for PNG
    if ($mime === 'image/png') {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
    }

    imagecopyresampled($canvas, $src, 0, 0, $x, $y, $size, $size, $side, $side);

    $result = match($mime) {
        'image/png'  => imagepng($canvas, $path, 8),
        default      => imagejpeg($canvas, $path, 88),
    };

    imagedestroy($src);
    imagedestroy($canvas);
    return (bool)$result;
}

/**
 * Delete an uploaded file safely (prevents path traversal).
 */
function deleteUpload(string $relativePath): void
{
    $base = realpath(__DIR__ . '/../uploads');
    $full = realpath(__DIR__ . '/../uploads/' . ltrim($relativePath, '/'));
    if ($full && $base && str_starts_with($full, $base)) {
        @unlink($full);
    }
}
