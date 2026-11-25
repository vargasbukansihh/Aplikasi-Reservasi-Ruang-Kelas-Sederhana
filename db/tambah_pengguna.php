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
    $_SESSION['pengguna_notice'] = $message;
    $_SESSION['pengguna_notice_type'] = $type;

    if ($reopenModal && $formValues !== null) {
        $_SESSION['pengguna_form_values'] = $formValues;
        $_SESSION['pengguna_reopen_modal'] = true;
    } else {
        unset($_SESSION['pengguna_form_values'], $_SESSION['pengguna_reopen_modal']);
    }

    header('Location: ../admin/data_pengguna.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect('Permintaan tidak valid.', 'danger');
}

$csrfToken = $_SESSION['csrf_pengguna'] ?? null;
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
    'nim' => '',
    'nama' => '',
    'username' => '',
    'kelas' => '',
    'role' => '',
];
$rawNim = $_POST['nim'] ?? '';
$rawName = $_POST['nama'] ?? '';
$rawUsername = $_POST['username'] ?? '';
$rawClass = $_POST['kelas'] ?? '';
$rawRole = $_POST['role'] ?? '';
$rawPassword = $_POST['password'] ?? '';

if (
    !is_string($rawNim)
    || !is_string($rawName)
    || !is_string($rawUsername)
    || !is_string($rawClass)
    || !is_string($rawRole)
    || !is_string($rawPassword)
) {
    $redirect('Data pengguna tidak valid.', 'danger', $formValues, true);
}

$formValues = [
    'id' => '',
    'nim' => trim($rawNim),
    'nama' => trim($rawName),
    'username' => trim($rawUsername),
    'kelas' => trim($rawClass),
    'role' => trim($rawRole),
];
$hasValidTextLength = static function (string $value, int $max, bool $allowEmpty = false): bool {
    if ($value === '') {
        return $allowEmpty;
    }

    return preg_match('/\A.{1,' . $max . '}\z/us', $value) === 1;
};
$nimIsValid = $formValues['role'] === 'admin'
    ? $hasValidTextLength($formValues['nim'], 20, true)
    : $hasValidTextLength($formValues['nim'], 20);
$passwordIsValid = strlen($rawPassword) <= 72
    && preg_match('/\A.{8,}\z/us', $rawPassword) === 1;

if (
    !$hasValidTextLength($formValues['nama'], 100)
    || !$hasValidTextLength($formValues['username'], 100)
    || !$hasValidTextLength($formValues['kelas'], 20, true)
    || !$nimIsValid
    || !in_array($formValues['role'], ['admin', 'mahasiswa'], true)
    || !$passwordIsValid
) {
    $redirect(
        'Periksa nama, username, NIM, kelas, peran, dan password. NIM wajib untuk mahasiswa; password minimal 8 karakter dan maksimal 72 byte.',
        'danger',
        $formValues,
        true
    );
}

try {
    $duplicateUsername = $conn->prepare(
        'SELECT 1 FROM pengguna WHERE username = :username LIMIT 1'
    );
    $duplicateUsername->execute(['username' => $formValues['username']]);
    if ($duplicateUsername->fetchColumn()) {
        $redirect('Username tersebut sudah digunakan.', 'danger', $formValues, true);
    }

    if ($formValues['nim'] !== '') {
        $duplicateNim = $conn->prepare(
            "SELECT 1 FROM pengguna WHERE nim = :nim AND nim <> '' LIMIT 1"
        );
        $duplicateNim->execute(['nim' => $formValues['nim']]);
        if ($duplicateNim->fetchColumn()) {
            $redirect('NIM tersebut sudah digunakan.', 'danger', $formValues, true);
        }
    }

    $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);
    if (!is_string($passwordHash)) {
        $redirect('Password tidak dapat diproses.', 'danger', $formValues, true);
    }

    $statement = $conn->prepare(
        'INSERT INTO pengguna (nim, nama, username, password, kelas, role)
         VALUES (:nim, :nama, :username, :password, :kelas, :role)'
    );
    $statement->execute([
        'nim' => $formValues['nim'],
        'nama' => $formValues['nama'],
        'username' => $formValues['username'],
        'password' => $passwordHash,
        'kelas' => $formValues['kelas'],
        'role' => $formValues['role'],
    ]);
} catch (PDOException $exception) {
    error_log('User creation failed: ' . $exception->getMessage());
    $message = $exception->getCode() === '23000'
        ? 'Username atau NIM tersebut sudah digunakan.'
        : 'Pengguna tidak dapat ditambahkan karena terjadi kesalahan database.';
    $redirect($message, 'danger', $formValues, true);
}

$redirect('Pengguna berhasil ditambahkan.');
