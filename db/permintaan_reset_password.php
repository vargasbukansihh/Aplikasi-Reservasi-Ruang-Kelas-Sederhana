<?php
session_start();

$redirect = static function (string $message, string $type = 'info'): void {
    $_SESSION['password_reset_notice'] = $message;
    $_SESSION['password_reset_notice_type'] = $type;
    header('Location: ../lupa_password.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../lupa_password.php');
    exit;
}

$sessionToken = $_SESSION['csrf_password_reset'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($sessionToken)
    || !is_string($submittedToken)
    || !hash_equals($sessionToken, $submittedToken)
) {
    $redirect('Form sudah kedaluwarsa. Muat ulang halaman lalu coba kembali.', 'danger');
}

$rawEmail = $_POST['email'] ?? '';
if (!is_string($rawEmail)) {
    $redirect('Alamat email tidak valid.', 'danger');
}
$email = trim($rawEmail);
$_SESSION['password_reset_email'] = $email;
if (
    strlen($email) > 254
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
) {
    $redirect('Masukkan alamat email yang valid.', 'danger');
}

try {
    require_once __DIR__ . '/password_reset_mail.php';
    $mailConfiguration = getPasswordResetMailConfiguration();
    require_once __DIR__ . '/koneksi.php';

    $statement = $conn->prepare(
        'SELECT id_pengguna, email FROM pengguna WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $recentRequest = $conn->prepare(
            'SELECT 1 FROM password_reset_tokens
             WHERE id_pengguna = :id_pengguna
               AND expires_at > DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 29 MINUTE)
             LIMIT 1'
        );
        $recentRequest->execute(['id_pengguna' => $user['id_pengguna']]);

        if (!$recentRequest->fetchColumn()) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $saveToken = $conn->prepare(
                'INSERT INTO password_reset_tokens (id_pengguna, token_hash, expires_at)
                 VALUES (:id_pengguna, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE))
                 ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)'
            );
            $saveToken->execute([
                'id_pengguna' => $user['id_pengguna'],
                'token_hash' => $tokenHash,
            ]);

            if (!sendPasswordResetEmail($user['email'], $token, $mailConfiguration)) {
                $deleteToken = $conn->prepare(
                    'DELETE FROM password_reset_tokens
                     WHERE id_pengguna = :id_pengguna AND token_hash = :token_hash'
                );
                $deleteToken->execute([
                    'id_pengguna' => $user['id_pengguna'],
                    'token_hash' => $tokenHash,
                ]);
                throw new RuntimeException('PHP mail() did not accept the password reset message.');
            }
        }
    }
} catch (PDOException $exception) {
    error_log('Password reset request failed: ' . $exception->getMessage());
    $redirect('Permintaan reset password gagal diproses. Coba lagi nanti.', 'danger');
} catch (RuntimeException $exception) {
    error_log('Password reset email delivery failed: ' . $exception->getMessage());
    $redirect(
        'Email pemulihan belum dapat dikirim. Coba lagi nanti atau hubungi administrator.',
        'danger'
    );
}

$redirect(
    'Jika email tersebut terdaftar, tautan reset akan dikirim. Periksa kotak masuk atau folder spam.'
);
