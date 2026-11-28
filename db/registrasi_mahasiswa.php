<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$redirectWithError = static function (string $message, array $values = []): void {
    $_SESSION['registration_error'] = $message;
    $_SESSION['registration_values'] = $values;
    header('Location: ../registrasi.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../registrasi.php');
    exit;
}

$sessionToken = $_SESSION['csrf_registration'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($sessionToken)
    || !is_string($submittedToken)
    || !hash_equals($sessionToken, $submittedToken)
) {
    $redirectWithError('Form sudah kedaluwarsa. Muat ulang halaman lalu coba kembali.');
}

$rawName = $_POST['nama'] ?? '';
$rawNim = $_POST['nim'] ?? '';
$rawUsername = $_POST['username'] ?? '';
$rawEmail = $_POST['email'] ?? '';
$rawClass = $_POST['kelas'] ?? '';
$rawPassword = $_POST['password'] ?? '';
$rawConfirmation = $_POST['konfirmasi_password'] ?? '';
$rawAgreement = $_POST['setuju'] ?? '';
if (
    !is_string($rawName)
    || !is_string($rawNim)
    || !is_string($rawUsername)
    || !is_string($rawEmail)
    || !is_string($rawClass)
    || !is_string($rawPassword)
    || !is_string($rawConfirmation)
    || !is_string($rawAgreement)
) {
    $redirectWithError('Data registrasi tidak valid.');
}

$values = [
    'nama' => trim($rawName),
    'nim' => trim($rawNim),
    'username' => trim($rawUsername),
    'email' => trim($rawEmail),
    'kelas' => trim($rawClass),
];
$hasValidTextLength = static function (string $value, int $maximum, bool $allowEmpty = false): bool {
    if ($value === '') {
        return $allowEmpty;
    }

    return preg_match('/\A.{1,' . $maximum . '}\z/us', $value) === 1;
};
$passwordIsValid = strlen($rawPassword) <= 72
    && preg_match('/\A.{8,}\z/us', $rawPassword) === 1;

if (
    !$hasValidTextLength($values['nama'], 100)
    || !$hasValidTextLength($values['nim'], 20)
    || !$hasValidTextLength($values['username'], 100)
    || !$hasValidTextLength($values['email'], 254)
    || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false
    || !$hasValidTextLength($values['kelas'], 20, true)
    || !$passwordIsValid
    || $rawPassword !== $rawConfirmation
    || $rawAgreement !== '1'
) {
    $redirectWithError(
        'Periksa kembali nama, NIM, username, email, kelas, password, konfirmasi password, dan persetujuan.',
        $values
    );
}

require_once __DIR__ . '/koneksi.php';

try {
    $duplicateUsername = $conn->prepare(
        'SELECT 1 FROM pengguna WHERE username = :username LIMIT 1'
    );
    $duplicateUsername->execute(['username' => $values['username']]);
    if ($duplicateUsername->fetchColumn()) {
        $redirectWithError('Username tersebut sudah digunakan.', $values);
    }

    $duplicateEmail = $conn->prepare(
        'SELECT 1 FROM pengguna WHERE email = :email LIMIT 1'
    );
    $duplicateEmail->execute(['email' => $values['email']]);
    if ($duplicateEmail->fetchColumn()) {
        $redirectWithError('Email tersebut sudah digunakan.', $values);
    }

    $duplicateNim = $conn->prepare(
        "SELECT 1 FROM pengguna WHERE nim = :nim AND nim <> '' LIMIT 1"
    );
    $duplicateNim->execute(['nim' => $values['nim']]);
    if ($duplicateNim->fetchColumn()) {
        $redirectWithError('NIM tersebut sudah terdaftar.', $values);
    }

    $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);
    if (!is_string($passwordHash)) {
        $redirectWithError('Password tidak dapat diproses.', $values);
    }

    $statement = $conn->prepare(
        'INSERT INTO pengguna (nim, nama, username, email, password, kelas, role)
         VALUES (:nim, :nama, :username, :email, :password, :kelas, :role)'
    );
    $statement->execute([
        'nim' => $values['nim'],
        'nama' => $values['nama'],
        'username' => $values['username'],
        'email' => $values['email'],
        'password' => $passwordHash,
        'kelas' => $values['kelas'],
        'role' => 'mahasiswa',
    ]);
} catch (PDOException $exception) {
    error_log('Student registration failed: ' . $exception->getMessage());
    $message = $exception->getCode() === '23000'
        ? 'NIM, username, atau email tersebut sudah digunakan.'
        : 'Registrasi gagal karena terjadi kesalahan database.';
    $redirectWithError($message, $values);
}

$_SESSION['registration_success'] = 'Registrasi berhasil. Silakan masuk menggunakan NIM atau username.';
unset($_SESSION['registration_error'], $_SESSION['registration_values']);
header('Location: ../login.php');
exit;
