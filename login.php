<?php
session_start();

$destinations = [
    'admin' => 'admin/dasboard1.php',
    'mahasiswa' => 'mahasiswa/dasboard.php',
];
$sessionRole = $_SESSION['role'] ?? null;
if (
    isset($_SESSION['id_pengguna'])
    && is_string($sessionRole)
    && isset($destinations[$sessionRole])
) {
    header('Location: ' . $destinations[$sessionRole]);
    exit;
}

require_once __DIR__ . '/db/koneksi.php';

$loginError = '';
$loginSuccess = $_SESSION['password_reset_success']
    ?? $_SESSION['registration_success']
    ?? '';
unset($_SESSION['registration_success'], $_SESSION['password_reset_success']);
if (!is_string($loginSuccess)) {
    $loginSuccess = '';
}
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = $_POST['identitas'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!is_string($identity) || !is_string($password)) {
        $loginError = 'Username/NIM atau password tidak sesuai.';
        $identity = '';
    } else {
        $identity = trim($identity);

        if ($identity === '' || $password === '') {
            $loginError = 'Username/NIM atau password tidak sesuai.';
        } else {
            $statement = $conn->prepare(
                'SELECT id_pengguna, nama, password, role
                 FROM pengguna
                 WHERE nim = :nim OR username = :username'
            );
            $statement->execute([
                'nim' => $identity,
                'username' => $identity,
            ]);
            $matchingUsers = $statement->fetchAll(PDO::FETCH_ASSOC);
            $user = null;
            foreach ($matchingUsers as $candidate) {
                if (
                    isset($destinations[$candidate['role']])
                    && password_verify($password, (string) $candidate['password'])
                ) {
                    $user = $candidate;
                    break;
                }
            }

            if ($user !== null) {
                session_regenerate_id(true);
                $_SESSION['id_pengguna'] = $user['id_pengguna'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $user['role'];

                header('Location: ' . $destinations[$user['role']]);
                exit;
            }

            $loginError = 'Username/NIM atau password tidak sesuai.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk - Reservasi Kelas</title>
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
    <a class="login-brand" href="login.php" aria-label="Reservasi Kelas, halaman masuk">
      <img class="brand-mark brand-logo" src="assets/img/logo pbl.png" alt="" aria-hidden="true">
      <span>Reservasi Kelas</span>
    </a>

    <section class="card-surface login-card" aria-labelledby="judul-login">
      <header class="login-card-heading">
        <h1 id="judul-login">Login</h1>
        <p>Gunakan akun Anda untuk mengakses aplikasi.</p>
      </header>

      <div id="pesan-login" class="login-feedback" role="status" aria-live="polite"
           <?php echo $loginError === '' && $loginSuccess === '' ? 'hidden' : ''; ?>>
        <?php echo htmlspecialchars($loginError !== '' ? $loginError : $loginSuccess, ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <form id="form-login" method="post" action="login.php" novalidate>
        <div class="login-field">
          <label for="identitas-login" class="form-label">Username / NIM</label>
          <input type="text" class="form-control" id="identitas-login" name="identitas"
                 placeholder="Username atau NIM" autocomplete="username"
                 value="<?php echo htmlspecialchars($identity, ENT_QUOTES, 'UTF-8'); ?>"
                 required aria-describedby="err-identitas-login">
          <div class="invalid-feedback" id="err-identitas-login">Username atau NIM wajib diisi.</div>
        </div>

        <div class="login-field">
          <label for="password-login" class="form-label">Password</label>
          <div class="login-password-field">
            <input type="password" class="form-control" id="password-login" name="password"
                   placeholder="Masukkan password" autocomplete="current-password"
                   required aria-describedby="err-password-login">
            <div class="invalid-feedback" id="err-password-login">Password wajib diisi.</div>
            <button type="button" class="login-password-toggle" id="toggle-password-login"
                    aria-label="Tampilkan password" aria-pressed="false">
              <i class="fa-solid fa-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="login-options">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="ingat-saya" name="ingat">
            <label class="form-check-label" for="ingat-saya">Ingat saya</label>
          </div>
          <a class="login-text-button" id="lupa-password" href="lupa_password.php">Lupa password?</a>
        </div>

        <button type="submit" class="btn btn-primary login-submit">
          <i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>Masuk
        </button>
      </form>

      <p class="login-register">
        Belum punya akun? <a href="registrasi.php">Daftar</a>
      </p>
    </section>

  </main>

  <script src="assets/js/login.js"></script>
</body>
</html>