<?php
require_once __DIR__ . '/auth.php';
requireAdminAccess();
require_once __DIR__ . '/koneksi.php';

$redirect = static function (
    string $message,
    string $type = 'success',
    ?array $formValues = null,
    bool $reopenModal = false
): void {
    $_SESSION['ruang_notice'] = $message;
    $_SESSION['ruang_notice_type'] = $type;

    if ($reopenModal && $formValues !== null) {
        $_SESSION['ruang_form_values'] = $formValues;
        $_SESSION['ruang_reopen_modal'] = true;
    } else {
        unset($_SESSION['ruang_form_values'], $_SESSION['ruang_reopen_modal']);
    }

    header('Location: ../admin/ruang.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect('Permintaan tidak valid.', 'danger');
}

$csrfToken = $_SESSION['csrf_ruang'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrfToken)
    || !is_string($submittedToken)
    || !hash_equals($csrfToken, $submittedToken)
) {
    $redirect('Permintaan tidak valid. Muat ulang halaman lalu coba kembali.', 'danger');
}

$formValues = [
    'id' => '',
    'nama' => '',
    'kapasitas' => '',
    'lokasi' => '',
    'aktif' => true,
];
$rawName = $_POST['nama'] ?? '';
$rawCapacity = $_POST['kapasitas'] ?? '';
$rawLocation = $_POST['lokasi'] ?? '';
$rawActive = $_POST['aktif'] ?? null;

if (
    !is_string($rawName)
    || !is_string($rawCapacity)
    || !is_string($rawLocation)
    || ($rawActive !== null && $rawActive !== '1')
) {
    $redirect('Data ruang tidak valid.', 'danger', $formValues, true);
}

$formValues = [
    'id' => '',
    'nama' => trim($rawName),
    'kapasitas' => trim($rawCapacity),
    'lokasi' => trim($rawLocation),
    'aktif' => $rawActive === '1',
];
$capacityInput = preg_match('/\A[0-9]+\z/', $formValues['kapasitas']) === 1
    ? ltrim($formValues['kapasitas'], '0')
    : '';
$capacity = filter_var(
    $capacityInput,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
);
$nameIsValid = preg_match('/\A.{1,100}\z/us', $formValues['nama']) === 1;
$locationIsValid = preg_match('/\A.{1,100}\z/us', $formValues['lokasi']) === 1;

if (!$nameIsValid || !$locationIsValid || $capacity === false) {
    $redirect(
        'Isi nama dan lokasi (maksimal 100 karakter), serta kapasitas berupa bilangan bulat lebih dari 0.',
        'danger',
        $formValues,
        true
    );
}

try {
    $statement = $conn->prepare(
        'INSERT INTO ruang (nama_ruang, lokasi, kapasitas, status_aktif)
         VALUES (:nama, :lokasi, :kapasitas, :status_aktif)'
    );
    $statement->execute([
        'nama' => $formValues['nama'],
        'lokasi' => $formValues['lokasi'],
        'kapasitas' => $capacity,
        'status_aktif' => $formValues['aktif'] ? 1 : 0,
    ]);
} catch (PDOException $exception) {
    error_log('Room creation failed: ' . $exception->getMessage());
    $redirect(
        'Ruang tidak dapat ditambahkan karena terjadi kesalahan database.',
        'danger',
        $formValues,
        true
    );
}

$redirect('Ruang berhasil ditambahkan.');
