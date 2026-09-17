<?php

/**
 * Validates and stores an uploaded photo under public/uploads/, with a
 * server-generated unique name and an extension derived from the file's
 * real detected type (never from the client-supplied filename). Shared
 * by any entity that can have a photo (products, customers, ...).
 *
 * @return array{provided: bool, path: ?string, error: ?string}
 */
function process_uploaded_image(string $fieldName, string $filenamePrefix): array
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['provided' => false, 'path' => null, 'error' => null];
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return ['provided' => true, 'path' => null, 'error' => 'upload'];
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($_FILES[$fieldName]['size'] > $maxBytes) {
        return ['provided' => true, 'path' => null, 'error' => 'size'];
    }

    $imageInfo = @getimagesize($_FILES[$fieldName]['tmp_name']);
    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    if ($imageInfo === false || !isset($mimeToExt[$imageInfo['mime']])) {
        return ['provided' => true, 'path' => null, 'error' => 'type'];
    }

    $uploadDir = __DIR__ . '/../public/uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['provided' => true, 'path' => null, 'error' => 'upload'];
    }

    $filename = uniqid($filenamePrefix . '_', true) . '.' . $mimeToExt[$imageInfo['mime']];
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $uploadDir . $filename)) {
        return ['provided' => true, 'path' => null, 'error' => 'upload'];
    }

    return ['provided' => true, 'path' => 'uploads/' . $filename, 'error' => null];
}

function delete_uploaded_image_file(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $fullPath = __DIR__ . '/../public/' . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
