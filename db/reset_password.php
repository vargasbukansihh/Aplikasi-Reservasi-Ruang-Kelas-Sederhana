<?php
session_start();

$redirect = static function (string $message, string $type = 'danger', string $token = ''): void {
    $_SESSION['password_reset_notice'] = $message;
    $_SESSION['password_reset_notice_type'] = $type;
    header('Location: ../reset_password.php?token=' . rawurlencode($token));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../lupa_password.php');
    exit;
}

$sessionToken = $_SESSION['csrf_password_reset'] ?? null;
$submittedCsrfToken = $_POST['csrf_token'] ?? null;
$rawToken = $_POST['token'] ?? '';
if (
    !is_string($sessionToken)
    || !is_string($submittedCsrfToken)
    || !hash_equals($sessionToken, $submittedCsrfToken)
    || !is_string($rawToken)
    || preg_match('/\A[a-f0-9]{64}\z/', $rawToken) !== 1
) {
    $redirect('Form reset tidak valid atau sudah kedaluwarsa.', 'danger');
}

$rawPassword = $_POST['password'] ?? '';
$rawConfirmation = $_POST['konfirmasi_password'] ?? '';
if (!is_string($rawPassword) || !is_string($rawConfirmation)) {
    $redirect('Password tidak valid.', 'danger', $rawToken);
}
if (
    strlen($rawPassword) > 72
    || preg_match('/\A.{8,}\z/us', $rawPassword) !== 1
    || $rawPassword !== $rawConfirmation
) {
    $redirect('Password minimal 8 karakter, maksimal 72 byte, dan harus sama dengan konfirmasi.', 'danger', $rawToken);
}

$passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);
if (!is_string($passwordHash)) {
    $redirect('Password tidak dapat diproses. Silakan coba kembali.', 'danger', $rawToken);
}

require_once __DIR__ . '/koneksi.php';
$tokenHash = hash('sha256', $rawToken);

try {
    $conn->beginTransaction();
    $findToken = $conn->prepare(
        'SELECT id_pengguna FROM password_reset_tokens
         WHERE token_hash = :token_hash AND expires_at > CURRENT_TIMESTAMP
         LIMIT 1 FOR UPDATE'
    );
    $findToken->execute(['token_hash' => $tokenHash]);
    $reset = $findToken->fetch(PDO::FETCH_ASSOC);
    if (!$reset) {
        $conn->rollBack();
        $redirect('Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.', 'danger');
    }

    $updatePassword = $conn->prepare(
        'UPDATE pengguna SET password = :password WHERE id_pengguna = :id_pengguna'
    );
    $updatePassword->execute([
        'password' => $passwordHash,
        'id_pengguna' => $reset['id_pengguna'],
    ]);

    $deleteToken = $conn->prepare(
        'DELETE FROM password_reset_tokens WHERE id_pengguna = :id_pengguna'
    );
    $deleteToken->execute(['id_pengguna' => $reset['id_pengguna']]);
    $conn->commit();
} catch (PDOException $exception) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Password reset completion failed: ' . $exception->getMessage());
    $redirect('Password gagal diperbarui karena terjadi kesalahan. Silakan coba kembali.', 'danger', $rawToken);
}

unset($_SESSION['csrf_password_reset'], $_SESSION['password_reset_email']);
$_SESSION['password_reset_success'] = 'Password berhasil diperbarui. Silakan masuk dengan password baru.';
header('Location: ../login.php');
exit;
