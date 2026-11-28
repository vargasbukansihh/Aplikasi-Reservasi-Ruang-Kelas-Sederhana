<?php
require_once __DIR__ . '/../db/auth.php';
requireStudentAccess();
require_once __DIR__ . '/../db/koneksi.php';

$studentId = (int) $_SESSION['id_pengguna'];
$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
if (!isset($_SESSION['csrf_mahasiswa_reservasi']) || !is_string($_SESSION['csrf_mahasiswa_reservasi'])) {
    $_SESSION['csrf_mahasiswa_reservasi'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_mahasiswa_reservasi'];
$currentDateTime = $conn->query(
    'SELECT CURDATE() AS today, CURTIME() AS time'
)->fetch(PDO::FETCH_ASSOC);
$today = (string) $currentDateTime['today'];
$currentTime = substr((string) $currentDateTime['time'], 0, 5);
$roomRows = $conn->query(
    'SELECT id_ruang, nama_ruang FROM ruang WHERE status_aktif = 1 ORDER BY nama_ruang ASC'
)->fetchAll(PDO::FETCH_ASSOC);
$formValues = [
    'ruang' => '',
    'tanggal' => '',
    'jam_mulai' => '',
    'jam_selesai' => '',
    'keperluan' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noticeMessage = '';
    $noticeType = 'danger';
    $reopenModal = false;
    $action = $_POST['action'] ?? '';
    $rawToken = $_POST['csrf_token'] ?? '';
    unset($_SESSION['student_reservation_form'], $_SESSION['student_reservation_reopen']);

    if (!is_string($rawToken) || !hash_equals($csrfToken, $rawToken)) {
        $noticeMessage = 'Permintaan tidak valid. Muat ulang halaman lalu coba kembali.';
    } elseif (!is_string($action) || $action !== 'create') {
        $noticeMessage = 'Aksi tidak valid. Mahasiswa hanya dapat mengajukan reservasi.';
    } else {
        $rawRoomId = $_POST['id_ruang'] ?? '';
        $rawDate = $_POST['tanggal'] ?? '';
        $rawStart = $_POST['jam_mulai'] ?? '';
        $rawEnd = $_POST['jam_selesai'] ?? '';
        $rawPurpose = $_POST['keperluan'] ?? '';

        if (
            !is_string($rawRoomId)
            || !is_string($rawDate)
            || !is_string($rawStart)
            || !is_string($rawEnd)
            || !is_string($rawPurpose)
        ) {
            $noticeMessage = 'Data reservasi tidak valid.';
        } else {
            $formValues = [
                'ruang' => trim($rawRoomId),
                'tanggal' => trim($rawDate),
                'jam_mulai' => trim($rawStart),
                'jam_selesai' => trim($rawEnd),
                'keperluan' => trim($rawPurpose),
            ];
            $roomId = filter_var(
                $formValues['ruang'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
            );
            $parsedDate = preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $formValues['tanggal']) === 1
                ? DateTime::createFromFormat('!Y-m-d', $formValues['tanggal'])
                : false;
            $dateIsValid = $parsedDate && $parsedDate->format('Y-m-d') === $formValues['tanggal'];
            $timePattern = '/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/';
            $timesAreValid = preg_match($timePattern, $formValues['jam_mulai']) === 1
                && preg_match($timePattern, $formValues['jam_selesai']) === 1
                && $formValues['jam_selesai'] > $formValues['jam_mulai'];
            $purposeIsValid = preg_match('/\A.{3,200}\z/us', $formValues['keperluan']) === 1;

            if (
                $roomId === false
                || !$dateIsValid
                || !$timesAreValid
                || !$purposeIsValid
                || $formValues['tanggal'] < $today
                || ($formValues['tanggal'] === $today && $formValues['jam_mulai'] <= $currentTime)
            ) {
                $noticeMessage = 'Periksa ruang, tanggal, jam, dan keperluan. Pengajuan harus untuk hari ini atau mendatang, jam mulai belum lewat, dan keperluan harus 3–200 karakter.';
                $reopenModal = true;
            } else {
                try {
                    $conn->beginTransaction();
                    $activeRoom = $conn->prepare(
                        'SELECT status_aktif FROM ruang WHERE id_ruang = :id FOR UPDATE'
                    );
                    $activeRoom->execute(['id' => $roomId]);
                    $roomStatus = $activeRoom->fetchColumn();

                    if ($roomStatus === false || (int) $roomStatus !== 1) {
                        $conn->rollBack();
                        $noticeMessage = 'Ruang tidak ditemukan atau sudah tidak aktif.';
                        $reopenModal = true;
                    } else {
                        $insert = $conn->prepare(
                            'INSERT INTO reservasi
                                (id_ruang, id_pengguna, tanggal, jam_mulai, jam_selesai, keperluan)
                             VALUES
                                (:room_id, :student_id, :reservation_date, :start_time, :end_time, :purpose)'
                        );
                        $insert->execute([
                            'room_id' => $roomId,
                            'student_id' => $studentId,
                            'reservation_date' => $formValues['tanggal'],
                            'start_time' => $formValues['jam_mulai'] . ':00',
                            'end_time' => $formValues['jam_selesai'] . ':00',
                            'purpose' => $formValues['keperluan'],
                        ]);
                        $conn->commit();
                        $noticeMessage = 'Pengajuan reservasi berhasil dikirim dan menunggu persetujuan admin.';
                        $noticeType = 'success';
                    }
                } catch (PDOException $exception) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    error_log('Student reservation submission failed: ' . $exception->getMessage());
                    $noticeMessage = 'Pengajuan reservasi tidak dapat disimpan karena terjadi kesalahan database.';
                    $reopenModal = true;
                }
            }
        }

        if ($reopenModal) {
            $_SESSION['student_reservation_form'] = $formValues;
            $_SESSION['student_reservation_reopen'] = true;
        } else {
            unset($_SESSION['student_reservation_form'], $_SESSION['student_reservation_reopen']);
        }
    }

    $_SESSION['student_reservasi_notice'] = $noticeMessage;
    $_SESSION['student_reservasi_notice_type'] = $noticeType;
    header('Location: reservasi.php');
    exit;
}

$noticeMessage = $_SESSION['student_reservasi_notice'] ?? '';
$noticeType = $_SESSION['student_reservasi_notice_type'] ?? 'success';
$storedFormValues = $_SESSION['student_reservation_form'] ?? null;
$reopenModal = ($_SESSION['student_reservation_reopen'] ?? false) === true;
unset(
    $_SESSION['student_reservasi_notice'],
    $_SESSION['student_reservasi_notice_type'],
    $_SESSION['student_reservation_form'],
    $_SESSION['student_reservation_reopen']
);
if (!is_string($noticeMessage)) {
    $noticeMessage = '';
}
if (!in_array($noticeType, ['success', 'danger', 'info'], true)) {
    $noticeType = 'success';
}
if (is_array($storedFormValues)) {
    foreach ($formValues as $key => $defaultValue) {
        if (isset($storedFormValues[$key]) && is_string($storedFormValues[$key])) {
            $formValues[$key] = $storedFormValues[$key];
        }
    }
}
$studentName = isset($_SESSION['nama']) && is_string($_SESSION['nama'])
    ? $_SESSION['nama']
    : 'Mahasiswa';
$reservationRowsStatement = $conn->prepare(
    'SELECT r.id_reservasi, r.tanggal, r.jam_mulai, r.jam_selesai, r.keperluan, r.status, ru.nama_ruang
     FROM reservasi r
     INNER JOIN ruang ru ON ru.id_ruang = r.id_ruang
     WHERE r.id_pengguna = :student_id
     ORDER BY r.tanggal DESC, r.jam_mulai DESC, r.id_reservasi DESC'
);
$reservationRowsStatement->execute(['student_id' => $studentId]);
$reservationRows = $reservationRowsStatement->fetchAll(PDO::FETCH_ASSOC);
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
    'selesai' => 'is-finished',
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reservasi Saya - Reservasi Kelas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/base.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <link href="../assets/css/reservasi-mahasiswa.css" rel="stylesheet">
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
            <a class="nav-link" href="dasboard.php">
              <i class="fa-solid fa-gauge" aria-hidden="true"></i>Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="jadwal.php">
              <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Jadwal
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="reservasi.php" aria-current="page">
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
            <li class="breadcrumb-item active" aria-current="page">Reservasi</li>
          </ol>
        </nav>
        <div class="user-chip">
          <span class="avatar avatar-sm" aria-hidden="true"></span>
          <?php echo $escapeHtml($studentName); ?>
        </div>
      </header>

      <main class="app-content reservations-content" id="konten-utama">
        <div id="area-notifikasi-reservasi" aria-live="polite">
          <?php if ($noticeMessage !== ''): ?>
            <div class="alert alert-<?php echo $escapeHtml($noticeType); ?> alert-feedback" role="status">
              <?php echo $escapeHtml($noticeMessage); ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="reservations-heading">
          <div>
            <h1 class="page-title">Reservasi Saya</h1>
            <p class="page-subtitle">Riwayat dan status pengajuan reservasi Anda</p>
          </div>
          <button type="button" class="btn btn-primary" id="btn-ajukan-reservasi"
                  data-modal-target="#modal-reservasi">
            <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Ajukan Reservasi
          </button>
        </div>

        <section class="card-surface reservations-table-card" aria-labelledby="judul-riwayat-reservasi">
          <h2 class="visually-hidden" id="judul-riwayat-reservasi">Riwayat reservasi</h2>
          <div class="table-responsive">
            <table class="table reservations-table">
              <caption class="visually-hidden">Riwayat reservasi ruang kelas dan status pengajuannya.</caption>
              <thead>
                <tr>
                  <th scope="col">Ruang</th>
                  <th scope="col">Tanggal</th>
                  <th scope="col">Jam</th>
                  <th scope="col">Keperluan</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody id="tabel-reservasi">
                <?php foreach ($reservationRows as $reservation): ?>
                  <?php
                  $status = (string) $reservation['status'];
                  $statusLabel = $reservationStatusLabels[$status] ?? $status;
                  $statusClass = $reservationStatusClasses[$status] ?? 'is-cancelled';
                  $formattedDate = DateTime::createFromFormat('!Y-m-d', (string) $reservation['tanggal']);
                  $startTime = substr((string) $reservation['jam_mulai'], 0, 5);
                  $endTime = substr((string) $reservation['jam_selesai'], 0, 5);
                  ?>
                  <tr>
                    <td><?php echo $escapeHtml((string) $reservation['nama_ruang']); ?></td>
                    <td class="tabular"><?php echo $formattedDate ? $escapeHtml($formattedDate->format('d/m/Y')) : $escapeHtml((string) $reservation['tanggal']); ?></td>
                    <td class="tabular"><?php echo $escapeHtml($startTime . '–' . $endTime); ?></td>
                    <td><?php echo $escapeHtml((string) $reservation['keperluan']); ?></td>
                    <td><span class="badge-status reservation-status <?php echo $escapeHtml($statusClass); ?>"><?php echo $escapeHtml($statusLabel); ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="empty-state text-center" id="keadaan-kosong-reservasi" <?php echo $reservationRows ? 'hidden' : ''; ?>>
            <i class="fa-regular fa-calendar-xmark fa-2x mb-2" aria-hidden="true"></i>
            <p class="mb-0">Belum ada pengajuan reservasi.</p>
          </div>
        </section>
      </main>
    </div>
  </div>

  <div class="modal fade" id="modal-reservasi" tabindex="-1" aria-labelledby="judul-modal-reservasi" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="form-reservasi" method="post" action="reservasi.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo $escapeHtml($csrfToken); ?>">
          <input type="hidden" name="action" value="create">
          <div class="modal-header">
            <h2 class="modal-title" id="judul-modal-reservasi">Ajukan Reservasi</h2>
            <button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div>
              <label for="field-ruang" class="form-label">Ruang <span class="text-danger" aria-hidden="true">*</span></label>
              <select class="form-select" id="field-ruang" name="id_ruang" required>
                <option value="">Pilih ruang</option>
                <?php foreach ($roomRows as $room): ?>
                  <option value="<?php echo $escapeHtml((string) $room['id_ruang']); ?>"
                    <?php echo $formValues['ruang'] === (string) $room['id_ruang'] ? 'selected' : ''; ?>>
                    <?php echo $escapeHtml((string) $room['nama_ruang']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Pilih ruang yang ingin dipesan.</div>
            </div>
            <div>
              <label for="field-tanggal" class="form-label">Tanggal <span class="text-danger" aria-hidden="true">*</span></label>
              <input type="date" class="form-control" id="field-tanggal" name="tanggal"
                     value="<?php echo $escapeHtml($formValues['tanggal']); ?>" min="<?php echo $escapeHtml($today); ?>"
                     data-today="<?php echo $escapeHtml($today); ?>" data-current-time="<?php echo $escapeHtml($currentTime); ?>" required>
              <div class="invalid-feedback">Pilih tanggal reservasi.</div>
            </div>
            <div class="reservation-time-fields">
              <div>
                <label for="field-jam-mulai" class="form-label">Jam Mulai <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="time" class="form-control tabular" id="field-jam-mulai" name="jam_mulai"
                       value="<?php echo $escapeHtml($formValues['jam_mulai']); ?>"
                       min="<?php echo $escapeHtml($formValues['tanggal'] === $today ? $currentTime : ''); ?>" required>
                <div class="invalid-feedback">Pilih jam mulai.</div>
              </div>
              <div>
                <label for="field-jam-selesai" class="form-label">Jam Selesai <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="time" class="form-control tabular" id="field-jam-selesai" name="jam_selesai"
                       value="<?php echo $escapeHtml($formValues['jam_selesai']); ?>" required>
                <div class="invalid-feedback">Pilih jam selesai.</div>
              </div>
            </div>
            <div class="invalid-feedback reservation-time-error" id="err-jam-reservasi" role="alert">
              Jam selesai harus lebih besar dari jam mulai.
            </div>
            <div>
              <label for="field-keperluan" class="form-label">Keperluan <span class="text-danger" aria-hidden="true">*</span></label>
              <textarea class="form-control" id="field-keperluan" name="keperluan" rows="3" required maxlength="200"
                        placeholder="Tulis keperluan..." aria-describedby="err-keperluan"><?php echo $escapeHtml($formValues['keperluan']); ?></textarea>
              <div class="invalid-feedback" id="err-keperluan">Jelaskan keperluan reservasi (minimal 3 karakter).</div>
            </div>
            <div class="reservation-current-status">
              <span>Status awal:</span>
              <span class="badge-status reservation-status is-pending">Diajukan</span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-modal-dismiss>Batal</button>
            <button type="submit" class="btn btn-primary">Ajukan Reservasi</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>window.reopenReservationModal = <?php echo $reopenModal ? 'true' : 'false'; ?>;</script>
  <script src="../assets/js/modal.js"></script>
  <script src="../assets/js/reservasi-mahasiswa.js"></script>
</body>
</html>