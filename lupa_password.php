<?php
session_start();

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
$email = $_SESSION['password_reset_email'] ?? '';
unset($_SESSION['password_reset_email']);
if (!is_string($email)) {
    $email = '';
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
  <title>Lupa Password - Reservasi Kelas</title>
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
    <section class="card-surface login-card" aria-labelledby="judul-reset">
      <header class="login-card-heading">
        <h1 id="judul-reset">Lupa Password?</h1>
        <p>Masukkan email yang terdaftar. Kami akan mengirim tautan untuk membuat password baru.</p>
      </header>
      <?php if ($notice !== ''): ?>
        <div class="login-feedback" role="<?php echo $noticeType === 'danger' ? 'alert' : 'status'; ?>">
          <?php echo $escapeHtml($notice); ?>
        </div>
      <?php endif; ?>
      <form method="post" action="db/permintaan_reset_password.php">
        <input type="hidden" name="csrf_token" value="<?php echo $escapeHtml($csrfToken); ?>">
        <div class="login-field">
          <label for="email-reset" class="form-label">Email</label>
          <input type="email" class="form-control" id="email-reset" name="email"
                 placeholder="nama@contoh.com" autocomplete="email" required maxlength="254"
                 value="<?php echo $escapeHtml($email); ?>">
        </div>
        <button type="submit" class="btn btn-primary login-submit">Kirim tautan reset</button>
      </form>
      <p class="login-register"><a href="login.php">Kembali ke login</a></p>
    </section>
  </main>
</body>
</html>
