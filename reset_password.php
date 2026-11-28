<?php
session_start();
require_once __DIR__ . '/db/koneksi.php';

$token = $_GET['token'] ?? '';
$tokenIsValidFormat = is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
$tokenIsValid = false;
if ($tokenIsValidFormat) {
    $statement = $conn->prepare(
        'SELECT 1 FROM password_reset_tokens
         WHERE token_hash = :token_hash AND expires_at > CURRENT_TIMESTAMP
         LIMIT 1'
    );
    $statement->execute(['token_hash' => hash('sha256', $token)]);
    $tokenIsValid = (bool) $statement->fetchColumn();
}

if (!isset($_SESSION['csrf_password_reset']) || !is_string($_SESSION['csrf_password_reset'])) {
    $_SESSION['csrf_password_reset'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_password_reset'];
$notice = $_SESSION['password_reset_notice'] ?? '';
$noticeType = $_SESSION['password_reset_notice_type'] ?? 'info';
unset($_SESSION['password_reset_notice'], $_SESSION['password_reset_notice_type']);
if (!is_string($notice)) {
    $notice = '';
}
if (!in_array($noticeType, ['info', 'danger'], true)) {
    $noticeType = 'info';
}
$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buat Password Baru - Reservasi Kelas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="assets/css/base.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link href="assets/css/login.css" rel="stylesheet">
</head>
<body class="login-page">
  <main class="login-layout">
    <a class="login-brand" href="login.php" aria-label="Reservasi Kelas, kembali ke halaman masuk">
      <img class="brand-mark brand-logo" src="assets/img/logo pbl.png" alt="" aria-hidden="true">
      <span>Reservasi Kelas</span>
    </a>
    <section class="card-surface login-card" aria-labelledby="judul-password-baru">
      <header class="login-card-heading">
        <h1 id="judul-password-baru">Buat Password Baru</h1>
        <p>Gunakan password minimal 8 karakter.</p>
      </header>
      <?php if ($notice !== ''): ?>
        <div class="login-feedback" role="<?php echo $noticeType === 'danger' ? 'alert' : 'status'; ?>">
          <?php echo $escapeHtml($notice); ?>
        </div>
      <?php endif; ?>
      <?php if ($tokenIsValid): ?>
        <form method="post" action="db/reset_password.php">
          <input type="hidden" name="csrf_token" value="<?php echo $escapeHtml($csrfToken); ?>">
          <input type="hidden" name="token" value="<?php echo $escapeHtml($token); ?>">
          <div class="login-field">
            <label for="password-baru" class="form-label">Password baru</label>
            <input type="password" class="form-control" id="password-baru" name="password"
                   autocomplete="new-password" required minlength="8" maxlength="72">
          </div>
          <div class="login-field">
            <label for="konfirmasi-password-baru" class="form-label">Konfirmasi password</label>
            <input type="password" class="form-control" id="konfirmasi-password-baru"
                   name="konfirmasi_password" autocomplete="new-password" required minlength="8" maxlength="72">
          </div>
          <button type="submit" class="btn btn-primary login-submit">Simpan password baru</button>
        </form>
      <?php else: ?>
        <p class="login-register">Tautan reset tidak valid atau sudah kedaluwarsa.</p>
        <p class="login-register"><a href="lupa_password.php">Minta tautan baru</a></p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
