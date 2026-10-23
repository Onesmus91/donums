<?php
/* =====================================================================
 * Safe uploads. Public images go to /assets/uploads (resized, re-encoded).
 * Everything sensitive (CVs, payment proofs, lesson files, mail
 * attachments) goes to the private data directory and is served only by
 * permission-checked routes.
 * ===================================================================== */

const UPLOAD_DOCS = ['pdf' => ['application/pdf'], 'doc' => ['application/msword', 'application/octet-stream', 'application/x-ole-storage'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']];

function upload_error_text(int $code): string {
    return [UPLOAD_ERR_INI_SIZE => 'The file is larger than the server allows.', UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.', UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save the file.'][$code] ?? 'The upload failed.';
}

/**
 * Save an uploaded file privately.
 * @return array{path:string,name:string,size:int,mime:string}|string  array on success, error message otherwise
 */
function save_private_upload(string $field, string $subdir, array $allowedExt, int $maxBytes, ?int $index = null) {
    $f = $_FILES[$field] ?? null;
    if (!$f) { return 'No file was uploaded.'; }
    $get = fn($k) => $index === null ? ($f[$k] ?? null) : ($f[$k][$index] ?? null);
    $err = (int) $get('error'); $tmp = (string) $get('tmp_name'); $orig = (string) $get('name'); $size = (int) $get('size');
    if ($err === UPLOAD_ERR_NO_FILE) { return 'No file was uploaded.'; }
    if ($err !== UPLOAD_ERR_OK) { return upload_error_text($err); }
    if (!is_uploaded_file($tmp)) { return 'The upload could not be verified.'; }
    if ($size > $maxBytes) { return 'The file must be ' . human_size($maxBytes) . ' or smaller.'; }
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) { return 'This file type is not allowed. Accepted: ' . strtoupper(implode(', ', $allowedExt)) . '.'; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
    if ($ext === 'pdf' && $mime !== 'application/pdf') { return 'The file does not look like a valid PDF.'; }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) && !str_starts_with($mime, 'image/')) { return 'The file does not look like a valid image.'; }
    if (in_array($ext, ['php', 'phtml', 'phar', 'exe', 'sh', 'js', 'html', 'htm', 'svg'], true)) { return 'This file type is not allowed.'; }
    $dir = DATA_DIR . '/private/' . trim($subdir, '/');
    if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
    $name = date('ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) { return 'The file could not be saved.'; }
    return ['path' => trim($subdir, '/') . '/' . $name, 'name' => mb_substr(preg_replace('/[^\w .()\-]+/u', '_', basename($orig)), 0, 120), 'size' => $size, 'mime' => $mime];
}

/** Public image upload: validated, resized (max 1600px) and re-encoded as WebP/JPEG. Returns URL or error string prefixed with "!". */
function save_public_image(string $field, string $prefix = 'img', int $maxW = 1600): string {
    $f = $_FILES[$field] ?? null;
    if (!$f || (int) ($f['error'] ?? 4) === UPLOAD_ERR_NO_FILE) { return ''; }
    if ((int) $f['error'] !== UPLOAD_ERR_OK) { return '!' . upload_error_text((int) $f['error']); }
    if (!is_uploaded_file($f['tmp_name'])) { return '!The upload could not be verified.'; }
    if ($f['size'] > 25 * 1024 * 1024) { return '!Images must be 25 MB or smaller.'; }
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) { return '!Upload a JPG, PNG or WebP image.'; }
    $dir = ROOT . '/assets/uploads';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $base = $prefix . '-' . date('ymd') . '-' . bin2hex(random_bytes(5));
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            if ($w > $maxW) { $img2 = imagescale($img, $maxW); if ($img2) { imagedestroy($img); $img = $img2; } }
            imagepalettetotruecolor($img); imagealphablending($img, true); imagesavealpha($img, true);
            if (function_exists('imagewebp') && imagewebp($img, $dir . '/' . $base . '.webp', 82)) { imagedestroy($img); return '/assets/uploads/' . $base . '.webp'; }
            if (imagejpeg($img, $dir . '/' . $base . '.jpg', 84)) { imagedestroy($img); return '/assets/uploads/' . $base . '.jpg'; }
            imagedestroy($img);
        }
    }
    // GD unavailable or the file could not be re-encoded: store the validated original instead.
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2]];
    if (move_uploaded_file($f['tmp_name'], $dir . '/' . $base . '.' . $ext)) { return '/assets/uploads/' . $base . '.' . $ext; }
    return '!The image could not be saved on this server (check folder permissions on assets/uploads).';
}

/** Send a private file to the browser (caller must have authorised access). */
function serve_private(string $relPath, string $downloadName = '', bool $inline = false, string $mime = ''): void {
    $rel = ltrim(str_replace(['..', '\\'], '', $relPath), '/');
    $full = DATA_DIR . '/private/' . $rel;
    if (!is_file($full)) { not_found(); }
    $mime = $mime ?: ((new finfo(FILEINFO_MIME_TYPE))->file($full) ?: 'application/octet-stream');
    $safeInline = $inline && in_array($mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'audio/mpeg'], true);
    header('Content-Type: ' . ($safeInline ? $mime : 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($safeInline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $downloadName ?: basename($full)) . '"');
    header('Content-Security-Policy: sandbox');
    header('Cache-Control: private, max-age=0');
    $size = filesize($full);
    // Basic HTTP range support so lesson videos can seek
    if ($safeInline && isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = $m[1] === '' ? max(0, $size - (int) $m[2]) : (int) $m[1];
        $end = ($m[2] === '' || $m[1] === '') ? $size - 1 : min((int) $m[2], $size - 1);
        if ($start <= $end && $start < $size) {
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size"); header('Accept-Ranges: bytes'); header('Content-Length: ' . ($end - $start + 1));
            $fp = fopen($full, 'rb'); fseek($fp, $start); $left = $end - $start + 1;
            while ($left > 0 && !feof($fp)) { $chunk = fread($fp, min(8192, $left)); echo $chunk; $left -= strlen($chunk); }
            fclose($fp); exit;
        }
    }
    header('Accept-Ranges: bytes'); header('Content-Length: ' . $size);
    readfile($full);
    exit;
}
