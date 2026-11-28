<?php
require_once __DIR__ . '/../db/auth.php';
requireAdminAccess();
require_once __DIR__ . '/../db/koneksi.php';

if (!isset($_SESSION['csrf_pengguna']) || !is_string($_SESSION['csrf_pengguna'])) {
    $_SESSION['csrf_pengguna'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_pengguna'];
$noticeMessage = $_SESSION['pengguna_notice'] ?? '';
$noticeType = $_SESSION['pengguna_notice_type'] ?? 'success';
unset($_SESSION['pengguna_notice'], $_SESSION['pengguna_notice_type']);

if (!is_string($noticeMessage)) {
    $noticeMessage = '';
}
if (!in_array($noticeType, ['success', 'danger', 'info'], true)) {
    $noticeType = 'success';
}

$formValues = [
    'id' => '',
    'nim' => '',
    'nama' => '',
    'username' => '',
    'email' => '',
    'kelas' => '',
    'role' => '',
];
$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$storedFormValues = $_SESSION['pengguna_form_values'] ?? null;
$showUserModal = ($_SESSION['pengguna_reopen_modal'] ?? false) === true;
unset($_SESSION['pengguna_form_values'], $_SESSION['pengguna_reopen_modal']);

if (is_array($storedFormValues)) {
    foreach ($formValues as $key => $defaultValue) {
        if (isset($storedFormValues[$key]) && is_string($storedFormValues[$key])) {
            $formValues[$key] = $storedFormValues[$key];
        }
    }
}

$users = $conn->query(
    'SELECT id_pengguna, nim, nama, username, email, kelas, role
     FROM pengguna
     ORDER BY id_pengguna DESC'
)->fetchAll(PDO::FETCH_ASSOC);
$currentAdminName = $escapeHtml((string) ($_SESSION['nama'] ?? 'Admin'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Data Pengguna - Reservasi Kelas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/base.css" rel="stylesheet">
  <link href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body>
  <div class="app-shell d-flex">

    <!-- Sidebar Admin -->
    <aside class="app-sidebar d-flex flex-column gap-1">
      <a class="brand" href="dasboard1.php">
        <img class="brand-mark brand-logo" src="../assets/img/logo pbl.png" alt="" aria-hidden="true">
        Reservasi Kelas
      </a>
      <p class="nav-heading" id="judul-menu">Menu Admin</p>
      <nav aria-labelledby="judul-menu">
        <ul class="nav flex-column gap-1">
          <li class="nav-item">
            <a class="nav-link" href="dasboard1.php">
              <i class="fa-solid fa-gauge" aria-hidden="true"></i>Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="ruang.php">
              <i class="fa-solid fa-building" aria-hidden="true"></i>Ruang
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="data_pengguna.php" aria-current="page">
              <i class="fa-solid fa-users" aria-hidden="true"></i>Pengguna
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="reservasi.php">
              <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>Reservasi
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="jadwal.php">
              <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Jadwal
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="rekapitulasi.php">
              <i class="fa-solid fa-chart-column" aria-hidden="true"></i>Laporan
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="../db/logout.php">
              <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Keluar
            </a>
          </li>
        </ul>
      </nav>
    </aside>

    <div class="app-main flex-grow-1">
      <header class="app-header">
        <nav aria-label="Breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dasboard1.php">Admin</a></li>
            <li class="breadcrumb-item active" aria-current="page">Pengguna</li>
          </ol>
        </nav>
        <div class="user-chip">
          <?php echo $currentAdminName; ?>
        </div>
      </header>

      <main class="app-content" id="konten-utama">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div>
            <h1 class="page-title">Data Pengguna</h1>
            <p class="page-subtitle">Kelola pengguna / peminjam</p>
          </div>
          <button type="button" class="btn btn-primary" id="btn-tambah"
                  data-modal-target="#modal-pengguna">
            <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Tambah Pengguna
          </button>
        </div>

        <!-- Umpan balik aksi (tambah, ubah, hapus, cari) -->
        <div id="area-notifikasi" aria-live="polite">
          <?php if ($noticeMessage !== ''): ?>
            <div class="alert alert-<?php echo $escapeHtml($noticeType); ?> alert-feedback alert-dismissible" role="status">
              <i class="fa-solid fa-circle-<?php echo $noticeType === 'success' ? 'check' : 'exclamation'; ?> me-2" aria-hidden="true"></i>
              <?php echo $escapeHtml($noticeMessage); ?>
              <button type="button" class="btn-close" data-alert-dismiss aria-label="Tutup notifikasi"></button>
            </div>
          <?php endif; ?>
        </div>

        <!-- Pencarian dan filter -->
        <section aria-labelledby="judul-cari">
          <h2 class="visually-hidden" id="judul-cari">Cari dan filter pengguna</h2>
          <form class="card-surface filter-card" id="form-cari" role="search" novalidate>
            <div class="filter-field">
              <label for="cari-kata" class="form-label">Cari Pengguna</label>
              <input type="search" class="form-control" id="cari-kata" name="q"
                     placeholder="Nama / NIM / username / email..." autocomplete="off">
            </div>
            <div class="filter-field">
              <label for="cari-peran" class="form-label">Peran</label>
              <select class="form-select" id="cari-peran" name="peran">
                <option value="">Semua peran</option>
                <option value="mahasiswa">Mahasiswa</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>Cari
            </button>
          </form>
        </section>

        <!-- Tabel pengguna -->
        <section class="card-surface table-card" aria-labelledby="judul-tabel">
          <h2 class="visually-hidden" id="judul-tabel">Daftar pengguna</h2>
          <div class="table-responsive">
            <table class="table table-users">
              <caption class="visually-hidden">Daftar pengguna dan peminjam ruang</caption>
              <thead>
                <tr>
                  <th scope="col" class="col-no">No</th>
                  <th scope="col">Nama</th>
                  <th scope="col" class="col-id">NIM</th>
                  <th scope="col">Username</th>
                  <th scope="col">Email</th>
                  <th scope="col">Kelas</th>
                  <th scope="col" class="col-peran">Peran</th>
                  <th scope="col" class="col-aksi">Aksi</th>
                </tr>
              </thead>
              <tbody id="tabel-pengguna">
                <?php foreach ($users as $index => $user): ?>
                  <?php $roleLabel = $user['role'] === 'admin' ? 'Admin' : 'Mahasiswa'; ?>
                  <tr data-role="<?php echo $escapeHtml($user['role']); ?>">
                    <td class="tabular"><?php echo $index + 1; ?></td>
                    <td><?php echo $escapeHtml($user['nama']); ?></td>
                    <td class="tabular"><?php echo $escapeHtml($user['nim'] !== '' ? $user['nim'] : '—'); ?></td>
                    <td><?php echo $escapeHtml($user['username']); ?></td>
                    <td><?php echo $escapeHtml($user['email'] ?? '—'); ?></td>
                    <td><?php echo $escapeHtml($user['kelas'] !== '' ? $user['kelas'] : '—'); ?></td>
                    <td><?php echo $roleLabel; ?></td>
                    <td class="text-nowrap">
                      <button type="button" class="btn-aksi btn-ubah"
                              data-id="<?php echo $escapeHtml((string) $user['id_pengguna']); ?>"
                              data-nama="<?php echo $escapeHtml($user['nama']); ?>"
                              data-nim="<?php echo $escapeHtml($user['nim']); ?>"
                              data-username="<?php echo $escapeHtml($user['username']); ?>"
                              data-email="<?php echo $escapeHtml($user['email'] ?? ''); ?>"
                              data-kelas="<?php echo $escapeHtml($user['kelas']); ?>"
                              data-role="<?php echo $escapeHtml($user['role']); ?>"
                              aria-label="Edit pengguna <?php echo $escapeHtml($user['nama']); ?>">Edit</button>
                      <button type="button" class="btn-aksi is-danger btn-hapus"
                              data-id="<?php echo $escapeHtml((string) $user['id_pengguna']); ?>"
                              data-nama="<?php echo $escapeHtml($user['nama']); ?>"
                              data-nim="<?php echo $escapeHtml($user['nim']); ?>"
                              data-username="<?php echo $escapeHtml($user['username']); ?>"
                              data-role="<?php echo $escapeHtml($user['role']); ?>"
                              aria-label="Hapus pengguna <?php echo $escapeHtml($user['nama']); ?>">Hapus</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="empty-state text-center" id="keadaan-kosong" hidden>
            <i class="fa-solid fa-users-slash fa-2x mb-2" aria-hidden="true"></i>
            <p class="mb-0" id="teks-kosong">Belum ada data pengguna.</p>
          </div>
        </section>

        <div class="pagination-bar">
          <p class="mb-0" id="info-halaman">Belum ada data pengguna.</p>
          <nav aria-label="Navigasi halaman pengguna">
            <ul class="pagination" id="pagination-pengguna"></ul>
          </nav>
        </div>

      </main>
    </div>
  </div>

  <!-- Modal tambah / ubah pengguna -->
  <div class="modal fade" id="modal-pengguna" tabindex="-1" aria-labelledby="judul-modal-pengguna" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="form-pengguna" method="post"
              action="<?php echo $formValues['id'] === '' ? '../db/tambah_pengguna.php' : '../db/edit_pengguna.php'; ?>" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo $escapeHtml($csrfToken); ?>">
          <input type="hidden" name="id" id="field-id" value="<?php echo $escapeHtml($formValues['id']); ?>">
          <div class="modal-header">
            <h2 class="modal-title" id="judul-modal-pengguna">Tambah Pengguna</h2>
            <button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div>
              <label for="field-nama" class="form-label">Nama Lengkap <span class="text-danger" aria-hidden="true">*</span></label>
              <input type="text" class="form-control" id="field-nama" name="nama" required maxlength="100"
                     value="<?php echo $escapeHtml($formValues['nama']); ?>"
                     placeholder="Nama pengguna" aria-describedby="err-nama" autocomplete="off">
              <div class="invalid-feedback" id="err-nama">Nama lengkap wajib diisi.</div>
            </div>
            <div>
              <label for="field-nim" class="form-label">NIM</label>
              <input type="text" class="form-control tabular" id="field-nim" name="nim" maxlength="20"
                     value="<?php echo $escapeHtml($formValues['nim']); ?>"
                     placeholder="Wajib untuk mahasiswa" aria-describedby="err-nim" autocomplete="off">
              <div class="invalid-feedback" id="err-nim">NIM wajib diisi untuk mahasiswa.</div>
            </div>
            <div>
              <label for="field-username" class="form-label">Username <span class="text-danger" aria-hidden="true">*</span></label>
              <input type="text" class="form-control" id="field-username" name="username" required maxlength="100"
                     value="<?php echo $escapeHtml($formValues['username']); ?>"
                     placeholder="Username untuk login" aria-describedby="err-username" autocomplete="off">
              <div class="invalid-feedback" id="err-username">Username wajib diisi.</div>
            </div>
            <div>
              <label for="field-email" class="form-label">Email <span class="text-danger" aria-hidden="true">*</span></label>
              <input type="email" class="form-control" id="field-email" name="email" required maxlength="254"
                     value="<?php echo $escapeHtml($formValues['email']); ?>"
                     placeholder="nama@contoh.com" aria-describedby="err-email" autocomplete="email">
              <div class="invalid-feedback" id="err-email">Masukkan alamat email yang valid.</div>
            </div>
            <div>
              <label for="field-kelas" class="form-label">Kelas</label>
              <input type="text" class="form-control" id="field-kelas" name="kelas" maxlength="20"
                     value="<?php echo $escapeHtml($formValues['kelas']); ?>"
                     placeholder="Contoh: TI-2A" aria-describedby="err-kelas" autocomplete="off">
              <div class="invalid-feedback" id="err-kelas">Kelas maksimal 20 karakter.</div>
            </div>
            <div>
              <label for="field-role" class="form-label">Peran <span class="text-danger" aria-hidden="true">*</span></label>
              <select class="form-select" id="field-role" name="role" required aria-describedby="err-role">
                <option value="">Pilih peran</option>
                <option value="mahasiswa" <?php echo $formValues['role'] === 'mahasiswa' ? 'selected' : ''; ?>>Mahasiswa</option>
                <option value="admin" <?php echo $formValues['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
              </select>
              <div class="invalid-feedback" id="err-role">Peran wajib dipilih.</div>
            </div>
            <div>
              <label for="field-password" class="form-label">Password
                <?php if ($formValues['id'] === ''): ?>
                  <span class="text-danger" aria-hidden="true">*</span>
                <?php endif; ?>
              </label>
              <input type="password" class="form-control" id="field-password" name="password"
                     minlength="8" maxlength="72" required autocomplete="new-password"
                     aria-describedby="err-password">
              <div class="form-text" id="bantuan-password">Minimal 8 karakter. Saat mengubah akun, kosongkan agar password tetap.</div>
              <div class="invalid-feedback" id="err-password">Password minimal 8 karakter dan maksimal 72 byte.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-modal-dismiss>Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-simpan">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal konfirmasi hapus -->
  <div class="modal fade" id="modal-hapus" tabindex="-1" aria-labelledby="judul-modal-hapus" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="form-hapus" method="post" action="../db/hapus_pengguna.php">
          <input type="hidden" name="csrf_token" value="<?php echo $escapeHtml($csrfToken); ?>">
          <input type="hidden" name="id" id="hapus-id">
          <div class="modal-header">
            <h2 class="modal-title" id="judul-modal-hapus">Hapus Pengguna</h2>
            <button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0">Anda yakin ingin menghapus pengguna berikut?</p>
            <div class="confirm-box" id="hapus-ringkasan"></div>
            <p class="confirm-warning">Tindakan ini tidak dapat dibatalkan.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-modal-dismiss>Batal</button>
            <button type="submit" class="btn btn-danger">Hapus</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    window.reopenUserModal = <?php echo $showUserModal ? 'true' : 'false'; ?>;
  </script>
  <script src="../assets/js/modal.js"></script>
  <script src="../assets/js/style.js"></script>
</body>
</html>