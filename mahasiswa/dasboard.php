<?php
require_once __DIR__ . '/../db/auth.php';
requireStudentAccess();
require_once __DIR__ . '/../db/koneksi.php';

$studentId = (int) $_SESSION['id_pengguna'];
$studentName = isset($_SESSION['nama']) && is_string($_SESSION['nama'])
    ? $_SESSION['nama']
    : 'Mahasiswa';
$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$roomStatistics = $conn->query(
    'SELECT COUNT(*) AS total,
            COALESCE(SUM(status_aktif = 1), 0) AS active
     FROM ruang'
)->fetch(PDO::FETCH_ASSOC);
$todayReservationStatement = $conn->prepare(
    'SELECT COUNT(*) FROM reservasi
     WHERE id_pengguna = :student_id AND tanggal = CURDATE()'
);
$todayReservationStatement->execute(['student_id' => $studentId]);
$todayReservationCount = (int) $todayReservationStatement->fetchColumn();
$pendingReservationStatement = $conn->prepare(
    "SELECT COUNT(*) FROM reservasi
     WHERE id_pengguna = :student_id AND status = 'diajukan'"
);
$pendingReservationStatement->execute(['student_id' => $studentId]);
$pendingReservationCount = (int) $pendingReservationStatement->fetchColumn();
$todayReservationsStatement = $conn->prepare(
    'SELECT r.jam_mulai, r.jam_selesai, r.keperluan, r.status, ru.nama_ruang
     FROM reservasi r
     INNER JOIN ruang ru ON ru.id_ruang = r.id_ruang
     WHERE r.id_pengguna = :student_id AND r.tanggal = CURDATE()
     ORDER BY r.jam_mulai ASC, r.id_reservasi ASC
     LIMIT 5'
);
$todayReservationsStatement->execute(['student_id' => $studentId]);
$todayReservations = $todayReservationsStatement->fetchAll(PDO::FETCH_ASSOC);
$availableRooms = $conn->query(
    "SELECT ru.nama_ruang, ru.lokasi, ru.kapasitas
     FROM ruang ru
     WHERE ru.status_aktif = 1
       AND NOT EXISTS (
           SELECT 1
           FROM reservasi r
           WHERE r.id_ruang = ru.id_ruang
             AND r.tanggal = CURDATE()
             AND r.status = 'disetujui'
             AND r.jam_mulai <= CURTIME()
             AND r.jam_selesai > CURTIME()
       )
     ORDER BY ru.nama_ruang ASC
     LIMIT 4"
)->fetchAll(PDO::FETCH_ASSOC);
$reservationStatusLabels = [
    'diajukan' => 'Diajukan',
    'disetujui' => 'Disetujui',
    'ditolak' => 'Ditolak',
    'batal' => 'Batal',
    'selesai' => 'Selesai',
];
$reservationStatusClasses = [
    'diajukan' => 'is-pending',
    'disetujui' => 'is-approved',
    'ditolak' => 'is-rejected',
    'batal' => 'is-cancelled',
    'selesai' => 'is-completed',
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Mahasiswa - Reservasi Kelas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/base.css" rel="stylesheet">
  <link href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>" rel="stylesheet">
  <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
  <div class="app-shell d-flex">
    <aside class="app-sidebar d-flex flex-column gap-1">
      <a class="brand" href="dasboard.php">
        <img class="brand-mark brand-logo" src="../assets/img/logo pbl.png" alt="" aria-hidden="true">
        Reservasi Kelas
      </a>
      <p class="nav-heading" id="judul-menu">Menu Mahasiswa</p>
      <nav aria-labelledby="judul-menu">
        <ul class="nav flex-column gap-1">
          <li class="nav-item">
            <a class="nav-link active" href="dasboard.php" aria-current="page">
              <i class="fa-solid fa-gauge" aria-hidden="true"></i>Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="jadwal.php">
              <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Jadwal
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="reservasi.php">
              <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>Reservasi
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
            <li class="breadcrumb-item"><a href="dasboard.php">Mahasiswa</a></li>
            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
          </ol>
        </nav>
        <div class="user-chip">
          <?php echo htmlspecialchars($studentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
      </header>

      <main class="app-content dashboard-content" id="konten-utama">
        <div class="dashboard-heading">
          <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Ringkasan ruang dan reservasi hari ini</p>
          </div>
          <a class="btn btn-primary" href="reservasi.php">
            <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Reservasi Baru
          </a>
        </div>

        <section class="dashboard-stats" aria-label="Ringkasan">
          <article class="card-surface stat-card">
            <h2>Ruang Aktif</h2>
            <p class="tabular"><?php echo (int) $roomStatistics['active']; ?></p>
          </article>
          <article class="card-surface stat-card">
            <h2>Total Ruang</h2>
            <p class="tabular"><?php echo (int) $roomStatistics['total']; ?></p>
          </article>
          <article class="card-surface stat-card">
            <h2>Reservasi Hari Ini</h2>
            <p class="tabular"><?php echo $todayReservationCount; ?></p>
          </article>
          <article class="card-surface stat-card">
            <h2>Menunggu Persetujuan</h2>
            <p class="tabular"><?php echo $pendingReservationCount; ?></p>
          </article>
        </section>

        <div class="dashboard-panels">
          <section class="card-surface dashboard-panel" aria-labelledby="judul-reservasi-hari-ini">
            <h2 class="dashboard-panel-title" id="judul-reservasi-hari-ini">Reservasi Hari Ini</h2>
            <div class="table-responsive dashboard-table-wrap">
              <table class="table dashboard-table">
                <caption class="visually-hidden">Daftar reservasi ruang untuk hari ini.</caption>
                <thead>
                  <tr>
                    <th scope="col">Ruang</th>
                    <th scope="col">Jam</th>
                    <th scope="col">Keperluan</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($todayReservations as $reservation): ?>
                    <?php
                    $status = (string) $reservation['status'];
                    $statusLabel = $reservationStatusLabels[$status] ?? $status;
                    $statusClass = $reservationStatusClasses[$status] ?? 'is-cancelled';
                    ?>
                    <tr>
                      <td><?php echo $escapeHtml((string) $reservation['nama_ruang']); ?></td>
                      <td class="tabular"><?php echo $escapeHtml(substr((string) $reservation['jam_mulai'], 0, 5) . '–' . substr((string) $reservation['jam_selesai'], 0, 5)); ?></td>
                      <td><?php echo $escapeHtml((string) $reservation['keperluan']); ?></td>
                      <td><span class="reservation-status <?php echo $escapeHtml($statusClass); ?>"><?php echo $escapeHtml($statusLabel); ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$todayReservations): ?>
                    <tr><td colspan="4" class="text-center text-secondary-token">Belum ada reservasi hari ini.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>

          <section class="card-surface dashboard-panel available-panel" aria-labelledby="judul-ruang-tersedia">
            <h2 class="dashboard-panel-title" id="judul-ruang-tersedia">Ruang Tersedia Saat Ini</h2>
            <ul class="available-rooms">
              <?php foreach ($availableRooms as $room): ?>
                <li class="available-room">
                  <div>
                    <h3><?php echo $escapeHtml((string) $room['nama_ruang']); ?></h3>
                    <p><?php echo $escapeHtml((string) $room['lokasi']); ?> <span aria-hidden="true">|</span> Kap. <?php echo (int) $room['kapasitas']; ?></p>
                  </div>
                  <span class="room-availability">Tersedia</span>
                </li>
              <?php endforeach; ?>
              <?php if (!$availableRooms): ?>
                <li class="text-secondary-token">Tidak ada ruang aktif yang tersedia saat ini.</li>
              <?php endif; ?>
            </ul>
          </section>
        </div>
      </main>
    </div>
  </div>
</body>
</html>