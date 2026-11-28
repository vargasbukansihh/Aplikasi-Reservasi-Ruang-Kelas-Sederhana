<?php
require_once __DIR__ . '/../db/auth.php';
requireAdminAccess();
require_once __DIR__ . '/../db/koneksi.php';

if (!isset($_SESSION['csrf_reservasi']) || !is_string($_SESSION['csrf_reservasi'])) {
    $_SESSION['csrf_reservasi'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_reservasi'];
$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$reservationStatuses = [
    'diajukan' => 'Diajukan',
    'disetujui' => 'Disetujui',
    'ditolak' => 'Ditolak',
    'batal' => 'Batal',
    'selesai' => 'Selesai',
];
$noticeMessage = $_SESSION['reservasi_notice'] ?? '';
$noticeType = $_SESSION['reservasi_notice_type'] ?? 'success';
unset($_SESSION['reservasi_notice'], $_SESSION['reservasi_notice_type']);

if (!is_string($noticeMessage)) {
    $noticeMessage = '';
}
if (!in_array($noticeType, ['success', 'danger', 'info'], true)) {
    $noticeType = 'success';
}

$redirectParameters = [];
foreach (['q' => 100, 'status' => 20, 'tanggal' => 10, 'halaman' => 12] as $key => $maxLength) {
    $value = $_GET[$key] ?? null;
    if (is_string($value) && strlen($value) <= $maxLength) {
        $redirectParameters[$key] = $value;
    }
}
$redirectQuery = http_build_query($redirectParameters);
$redirectToList = static function () use (&$noticeMessage, &$noticeType, $redirectQuery): void {
    $_SESSION['reservasi_notice'] = $noticeMessage;
    $_SESSION['reservasi_notice_type'] = $noticeType;
    header('Location: reservasi.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noticeMessage = '';
    $noticeType = 'danger';
    $action = $_POST['action'] ?? '';
    $rawId = $_POST['id'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';
    $reservationId = is_string($rawId)
        ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]])
        : false;

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $noticeMessage = 'Permintaan tidak valid. Muat ulang halaman lalu coba kembali.';
    } elseif (!is_string($action) || !in_array($action, ['approve', 'reject'], true)) {
        $noticeMessage = 'Aksi reservasi tidak dikenali.';
    } elseif ($reservationId === false) {
        $noticeMessage = 'ID reservasi tidak valid.';
    } elseif ($action === 'reject') {
        try {
            $statement = $conn->prepare(
                "UPDATE reservasi
                 SET status = 'ditolak'
                 WHERE id_reservasi = :id AND status = 'diajukan'"
            );
            $statement->execute(['id' => $reservationId]);

            if ($statement->rowCount() === 1) {
                $noticeMessage = 'Pengajuan reservasi berhasil ditolak.';
                $noticeType = 'success';
            } else {
                $noticeMessage = 'Reservasi tidak ditemukan atau statusnya sudah berubah.';
                $noticeType = 'info';
            }
        } catch (PDOException $exception) {
            error_log('Reservation rejection failed: ' . $exception->getMessage());
            $noticeMessage = 'Reservasi tidak dapat diproses karena terjadi kesalahan database.';
        }
    } else {
        try {
            $roomLookup = $conn->prepare(
                'SELECT id_ruang FROM reservasi WHERE id_reservasi = :id'
            );
            $roomLookup->execute(['id' => $reservationId]);
            $roomId = $roomLookup->fetchColumn();

            if ($roomId === false) {
                $noticeMessage = 'Reservasi tidak ditemukan.';
                $noticeType = 'info';
            } else {
                $conn->beginTransaction();

                $roomLock = $conn->prepare(
                    'SELECT status_aktif FROM ruang WHERE id_ruang = :id FOR UPDATE'
                );
                $roomLock->execute(['id' => $roomId]);
                $roomIsActive = $roomLock->fetchColumn();

                $reservationLookup = $conn->prepare(
                    'SELECT id_ruang, tanggal, jam_mulai, jam_selesai, status
                     FROM reservasi
                     WHERE id_reservasi = :id
                     FOR UPDATE'
                );
                $reservationLookup->execute(['id' => $reservationId]);
                $reservation = $reservationLookup->fetch(PDO::FETCH_ASSOC);

                if (!$reservation || (int) $reservation['id_ruang'] !== (int) $roomId) {
                    $conn->rollBack();
                    $noticeMessage = 'Reservasi berubah saat diproses. Muat ulang halaman lalu coba kembali.';
                    $noticeType = 'info';
                } elseif ($reservation['status'] !== 'diajukan') {
                    $conn->rollBack();
                    $noticeMessage = 'Reservasi tidak ditemukan atau statusnya sudah berubah.';
                    $noticeType = 'info';
                } elseif ($roomIsActive === false || (int) $roomIsActive !== 1) {
                    $conn->rollBack();
                    $noticeMessage = 'Reservasi tidak dapat disetujui karena ruang sudah tidak aktif.';
                } else {
                    $overlap = $conn->prepare(
                        "SELECT id_reservasi
                         FROM reservasi
                         WHERE id_ruang = :id_ruang
                           AND tanggal = :tanggal
                           AND status = 'disetujui'
                           AND jam_mulai < :jam_selesai
                           AND jam_selesai > :jam_mulai
                           AND id_reservasi <> :id_reservasi
                         LIMIT 1"
                    );
                    $overlap->execute([
                        'id_ruang' => $reservation['id_ruang'],
                        'tanggal' => $reservation['tanggal'],
                        'jam_selesai' => $reservation['jam_selesai'],
                        'jam_mulai' => $reservation['jam_mulai'],
                        'id_reservasi' => $reservationId,
                    ]);

                    if ($overlap->fetchColumn()) {
                        $conn->rollBack();
                        $noticeMessage = 'Reservasi tidak dapat disetujui karena jadwalnya bentrok dengan reservasi lain yang sudah disetujui.';
                    } else {
                        $approve = $conn->prepare(
                            "UPDATE reservasi
                             SET status = 'disetujui'
                             WHERE id_reservasi = :id AND status = 'diajukan'"
                        );
                        $approve->execute(['id' => $reservationId]);
                        if ($approve->rowCount() !== 1) {
                            $conn->rollBack();
                            $noticeMessage = 'Reservasi tidak ditemukan atau statusnya sudah berubah.';
                            $noticeType = 'info';
                        } else {
                            $conn->commit();
                            $noticeMessage = 'Reservasi berhasil disetujui.';
                            $noticeType = 'success';
                        }
                    }
                }
            }
        } catch (PDOException $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Reservation approval failed: ' . $exception->getMessage());
            $noticeMessage = 'Reservasi tidak dapat diproses karena terjadi kesalahan database.';
        }
    }

    $redirectToList();
}

$filterWarning = '';
$rawSearch = $_GET['q'] ?? '';
$search = is_string($rawSearch) ? trim($rawSearch) : '';
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
    $filterWarning = 'Pencarian dibatasi hingga 100 byte.';
}

$rawStatus = $_GET['status'] ?? '';
$statusFilter = is_string($rawStatus) ? $rawStatus : '';
if ($statusFilter !== '' && !array_key_exists($statusFilter, $reservationStatuses)) {
    $statusFilter = '';
    $filterWarning = 'Filter status tidak valid; semua status ditampilkan.';
}

$rawDate = $_GET['tanggal'] ?? '';
$dateFilter = is_string($rawDate) ? $rawDate : '';
if ($dateFilter !== '') {
    $parsedDate = DateTime::createFromFormat('!Y-m-d', $dateFilter);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $dateFilter) {
        $dateFilter = '';
        $filterWarning = 'Filter tanggal tidak valid; semua tanggal ditampilkan.';
    }
}

$rawPage = $_GET['halaman'] ?? '1';
$currentPage = is_string($rawPage)
    ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$currentPage = $currentPage === false ? 1 : $currentPage;
$pageSize = 20;
$conditions = [];
$parameters = [];

if ($statusFilter !== '') {
    $conditions[] = 'r.status = :status';
    $parameters['status'] = $statusFilter;
}
if ($dateFilter !== '') {
    $conditions[] = 'r.tanggal = :tanggal';
    $parameters['tanggal'] = $dateFilter;
}
if ($search !== '') {
    $conditions[] = '(ru.nama_ruang LIKE :room_search
        OR p.nama LIKE :name_search
        OR p.nim LIKE :nim_search
        OR r.keperluan LIKE :purpose_search)';
    $searchTerm = '%' . $search . '%';
    $parameters['room_search'] = $searchTerm;
    $parameters['name_search'] = $searchTerm;
    $parameters['nim_search'] = $searchTerm;
    $parameters['purpose_search'] = $searchTerm;
}
$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$joinedTables = ' FROM reservasi r
    INNER JOIN ruang ru ON ru.id_ruang = r.id_ruang
    INNER JOIN pengguna p ON p.id_pengguna = r.id_pengguna ';

$countStatement = $conn->prepare('SELECT COUNT(*)' . $joinedTables . $whereSql);
$countStatement->execute($parameters);
$totalRows = (int) $countStatement->fetchColumn();
$pageCount = max(1, (int) ceil($totalRows / $pageSize));
$currentPage = min($currentPage, $pageCount);
$offset = ($currentPage - 1) * $pageSize;
$listStatement = $conn->prepare(
    'SELECT r.id_reservasi, r.tanggal, r.jam_mulai, r.jam_selesai,
            r.keperluan, r.status, ru.nama_ruang, p.nama, p.nim'
    . $joinedTables . $whereSql
    . ' ORDER BY r.tanggal DESC, r.jam_mulai DESC, r.id_reservasi DESC'
    . ' LIMIT ' . $pageSize . ' OFFSET ' . $offset
);
$listStatement->execute($parameters);
$reservationRows = $listStatement->fetchAll(PDO::FETCH_ASSOC);

$filterParameters = [];
if ($search !== '') {
    $filterParameters['q'] = $search;
}
if ($statusFilter !== '') {
    $filterParameters['status'] = $statusFilter;
}
if ($dateFilter !== '') {
    $filterParameters['tanggal'] = $dateFilter;
}
$adminName = isset($_SESSION['nama']) && is_string($_SESSION['nama'])
    ? $_SESSION['nama']
    : 'Admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reservasi - Reservasi Kelas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/base.css" rel="stylesheet">
  <link href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body>
  <div class="app-shell d-flex">
    <aside class="app-sidebar d-flex flex-column gap-1">
      <a class="brand" href="dasboard1.php">
        <img class="brand-mark brand-logo" src="../assets/img/logo pbl.png" alt="" aria-hidden="true">
        Reservasi Kelas
      </a>
      <p class="nav-heading" id="judul-menu">Menu Admin</p>
      <nav aria-labelledby="judul-menu">
        <ul class="nav flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="dasboard1.php"><i class="fa-solid fa-gauge" aria-hidden="true"></i>Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="ruang.php"><i class="fa-solid fa-building" aria-hidden="true"></i>Ruang</a></li>
          <li class="nav-item"><a class="nav-link" href="data_pengguna.php"><i class="fa-solid fa-users" aria-hidden="true"></i>Pengguna</a></li>
          <li class="nav-item"><a class="nav-link active" href="reservasi.php" aria-current="page"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i>Reservasi</a></li>
          <li class="nav-item"><a class="nav-link" href="jadwal.php"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Jadwal</a></li>
          <li class="nav-item"><a class="nav-link" href="rekapitulasi.php"><i class="fa-solid fa-chart-column" aria-hidden="true"></i>Laporan</a></li>
          <li class="nav-item"><a class="nav-link" href="../db/logout.php"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Keluar</a></li>
        </ul>
      </nav>
    </aside>

    <div class="app-main flex-grow-1">
      <header class="app-header">
        <nav aria-label="Breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dasboard1.php">Admin</a></li>
            <li class="breadcrumb-item active" aria-current="page">Reservasi</li>
          </ol>
        </nav>
        <div class="user-chip">
          <?php echo $escapeHtml($adminName); ?>
        </div>
      </header>

      <main class="app-content" id="konten-utama">
        <div>
          <h1 class="page-title">Data Reservasi</h1>
          <p class="page-subtitle">Tinjau pengajuan dan kelola status reservasi ruang.</p>
        </div>

        <div id="area-notifikasi-reservasi" aria-live="polite">
          <?php if ($noticeMessage !== ''): ?>
            <div class="alert alert-<?php echo $escapeHtml($noticeType); ?> alert-feedback" role="status">
              <i class="fa-solid fa-circle-<?php echo $noticeType === 'success' ? 'check' : 'exclamation'; ?> me-2" aria-hidden="true"></i>
              <?php echo $escapeHtml($noticeMessage); ?>
            </div>
          <?php endif; ?>
          <?php if ($filterWarning !== ''): ?>
            <div class="alert alert-info alert-feedback" role="status"><?php echo $escapeHtml($filterWarning); ?></div>
          <?php endif; ?>
        </div>

        <section aria-labelledby="judul-filter-reservasi">
          <h2 class="visually-hidden" id="judul-filter-reservasi">Cari dan filter reservasi</h2>
          <form class="card-surface filter-card" method="get" action="reservasi.php" role="search">
            <div class="filter-field">
              <label for="filter-pencarian" class="form-label">Cari</label>
              <input type="search" class="form-control" id="filter-pencarian" name="q"
                     value="<?php echo $escapeHtml($search); ?>" placeholder="Ruang, mahasiswa, NIM, atau keperluan">
            </div>
            <div class="filter-field">
              <label for="filter-status" class="form-label">Status</label>
              <select class="form-select" id="filter-status" name="status">
                <option value="">Semua status</option>
                <?php foreach ($reservationStatuses as $value => $label): ?>
                  <option value="<?php echo $escapeHtml($value); ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>>
                    <?php echo $escapeHtml($label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="filter-field">
              <label for="filter-tanggal" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="filter-tanggal" name="tanggal"
                     value="<?php echo $escapeHtml($dateFilter); ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>Cari</button>
            <a class="btn btn-outline-secondary" href="reservasi.php">Reset</a>
          </form>
        </section>

        <section class="card-surface table-card" aria-labelledby="judul-daftar-reservasi">
          <h2 class="visually-hidden" id="judul-daftar-reservasi">Daftar reservasi</h2>
          <div class="table-responsive">
            <table class="table table-users">
              <caption class="visually-hidden">Daftar reservasi ruang yang tersimpan di database.</caption>
              <thead>
                <tr>
                  <th scope="col">Ruang</th>
                  <th scope="col">Tanggal</th>
                  <th scope="col">Jam</th>
                  <th scope="col">Mahasiswa</th>
                  <th scope="col">Keperluan</th>
                  <th scope="col">Status</th>
                  <th scope="col">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reservationRows as $reservation): ?>
                  <?php
                  $statusLabel = $reservationStatuses[$reservation['status']] ?? $reservation['status'];
                  $badgeClass = $reservation['status'] === 'disetujui' ? 'is-aktif' : 'is-nonaktif';
                  $formattedDate = DateTime::createFromFormat('!Y-m-d', (string) $reservation['tanggal']);
                  ?>
                  <tr>
                    <td><?php echo $escapeHtml((string) $reservation['nama_ruang']); ?></td>
                    <td class="tabular"><?php echo $formattedDate ? $escapeHtml($formattedDate->format('d/m/Y')) : $escapeHtml((string) $reservation['tanggal']); ?></td>
                    <td class="tabular"><?php echo $escapeHtml(substr((string) $reservation['jam_mulai'], 0, 5) . '–' . substr((string) $reservation['jam_selesai'], 0, 5)); ?></td>
                    <td>
                      <?php echo $escapeHtml((string) $reservation['nama']); ?>
                      <div class="text-secondary-token"><?php echo $escapeHtml((string) $reservation['nim']); ?></div>
                    </td>
                    <td><?php echo $escapeHtml((string) $reservation['keperluan']); ?></td>
                    <td><span class="badge-status <?php echo $escapeHtml($badgeClass); ?>"><?php echo $escapeHtml((string) $statusLabel); ?></span></td>
                    <td>
                      <?php if ($reservation['status'] === 'diajukan'): ?>
                        <div class="d-flex flex-wrap gap-2">
                          <form method="post" action="reservasi.php<?php echo $redirectQuery !== '' ? '?' . $escapeHtml($redirectQuery) : ''; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $escapeHtml($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo $escapeHtml((string) $reservation['id_reservasi']); ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-primary btn-sm">Setujui</button>
                          </form>
                          <form method="post" action="reservasi.php<?php echo $redirectQuery !== '' ? '?' . $escapeHtml($redirectQuery) : ''; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $escapeHtml($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo $escapeHtml((string) $reservation['id_reservasi']); ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Tolak</button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span class="text-secondary-token">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (!$reservationRows): ?>
            <div class="empty-state text-center">
              <i class="fa-regular fa-calendar-xmark fa-2x mb-2" aria-hidden="true"></i>
              <p class="mb-0">Tidak ada reservasi yang cocok dengan filter.</p>
            </div>
          <?php endif; ?>
        </section>

        <div class="pagination-bar">
          <p class="mb-0" aria-live="polite">
            <?php if ($totalRows > 0): ?>
              Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $pageSize, $totalRows); ?> dari <?php echo $totalRows; ?> reservasi
            <?php else: ?>
              Belum ada reservasi untuk ditampilkan.
            <?php endif; ?>
          </p>
          <?php if ($pageCount > 1): ?>
            <nav aria-label="Navigasi halaman reservasi">
              <ul class="pagination">
                <?php for ($page = 1; $page <= $pageCount; $page++): ?>
                  <?php $pageQuery = http_build_query($filterParameters + ['halaman' => $page]); ?>
                  <li class="page-item<?php echo $page === $currentPage ? ' active' : ''; ?>">
                    <a class="page-link" href="reservasi.php?<?php echo $escapeHtml($pageQuery); ?>"
                       <?php echo $page === $currentPage ? 'aria-current="page"' : ''; ?>>
                      <?php echo $page; ?>
                    </a>
                  </li>
                <?php endfor; ?>
              </ul>
            </nav>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
