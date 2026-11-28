<?php
require_once __DIR__ . '/../db/auth.php';
requireStudentAccess();
require_once __DIR__ . '/../db/koneksi.php';

$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$studentName = isset($_SESSION['nama']) && is_string($_SESSION['nama'])
    ? $_SESSION['nama']
    : 'Mahasiswa';
$filterWarnings = [];
$rawRoomId = $_GET['ruang'] ?? '';
$roomFilter = '';
if (!is_string($rawRoomId)) {
    $filterWarnings[] = 'Filter ruang tidak valid; semua ruang ditampilkan.';
} elseif ($rawRoomId !== '') {
    $validatedRoomId = filter_var(
        $rawRoomId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
    );
    if ($validatedRoomId === false) {
        $filterWarnings[] = 'Filter ruang tidak valid; semua ruang ditampilkan.';
    } else {
        $roomFilter = (string) $validatedRoomId;
    }
}

$rawDate = $_GET['tanggal'] ?? '';
$dateFilter = is_string($rawDate) ? $rawDate : '';
if (!is_string($rawDate)) {
    $filterWarnings[] = 'Filter tanggal tidak valid; jadwal mendatang ditampilkan.';
}
if ($dateFilter !== '') {
    $parsedDate = preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $dateFilter) === 1
        ? DateTime::createFromFormat('!Y-m-d', $dateFilter)
        : false;
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $dateFilter) {
        $dateFilter = '';
        $filterWarnings[] = 'Filter tanggal tidak valid; jadwal mendatang ditampilkan.';
    }
}

$rawPage = $_GET['halaman'] ?? '1';
$currentPage = is_string($rawPage)
    ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($currentPage === false) {
    $currentPage = 1;
    $filterWarnings[] = 'Nomor halaman tidak valid; halaman pertama ditampilkan.';
}
$pageSize = 20;
$conditions = ["r.status = 'disetujui'"];
$parameters = [];
if ($dateFilter !== '') {
    $conditions[] = 'r.tanggal = :tanggal';
    $parameters['tanggal'] = $dateFilter;
} else {
    $conditions[] = 'r.tanggal >= CURDATE()';
}
if ($roomFilter !== '') {
    $conditions[] = 'r.id_ruang = :id_ruang';
    $parameters['id_ruang'] = $roomFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $conditions);
$joinedTables = ' FROM reservasi r
    INNER JOIN ruang ru ON ru.id_ruang = r.id_ruang ';
$countStatement = $conn->prepare('SELECT COUNT(*)' . $joinedTables . $whereSql);
$countStatement->execute($parameters);
$totalRows = (int) $countStatement->fetchColumn();
$pageCount = max(1, (int) ceil($totalRows / $pageSize));
$currentPage = min($currentPage, $pageCount);
$offset = ($currentPage - 1) * $pageSize;
$listStatement = $conn->prepare(
    'SELECT r.tanggal, r.jam_mulai, r.jam_selesai,
            ru.nama_ruang, ru.kapasitas, ru.lokasi'
    . $joinedTables . $whereSql
    . ' ORDER BY r.tanggal ASC, r.jam_mulai ASC, ru.nama_ruang ASC, r.id_reservasi ASC'
    . ' LIMIT ' . $pageSize . ' OFFSET ' . $offset
);
$listStatement->execute($parameters);
$scheduleRows = $listStatement->fetchAll(PDO::FETCH_ASSOC);
$roomRows = $conn->query(
    'SELECT id_ruang, nama_ruang FROM ruang ORDER BY nama_ruang ASC'
)->fetchAll(PDO::FETCH_ASSOC);
$filterWarning = implode(' ', array_unique($filterWarnings));
$filterParameters = [];
if ($roomFilter !== '') {
    $filterParameters['ruang'] = $roomFilter;
}
if ($dateFilter !== '') {
    $filterParameters['tanggal'] = $dateFilter;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Jadwal &amp; Ketersediaan Ruang - Reservasi Kelas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/base.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <link href="../assets/css/jadwal-mahasiswa.css" rel="stylesheet">
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
            <a class="nav-link active" href="jadwal.php" aria-current="page">
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
            <li class="breadcrumb-item active" aria-current="page">Jadwal</li>
          </ol>
        </nav>
        <div class="user-chip">
          <span class="avatar avatar-sm" aria-hidden="true"></span>
          <?php echo $escapeHtml($studentName); ?>
        </div>
      </header>

      <main class="app-content schedule-content" id="konten-utama">
        <div class="schedule-heading">
          <div>
            <h1 class="page-title">Jadwal Reservasi</h1>
            <p class="page-subtitle">Jadwal ini hanya menampilkan waktu yang telah disetujui. Pengajuan baru akan diperiksa admin.</p>
          </div>
          <a class="btn btn-primary" href="reservasi.php">
            <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Ajukan Reservasi
          </a>
        </div>

        <section aria-labelledby="judul-filter-jadwal">
          <h2 class="visually-hidden" id="judul-filter-jadwal">Cari jadwal dan ketersediaan ruang</h2>
          <form class="card-surface schedule-filter" id="form-filter-jadwal" method="get" action="jadwal.php">
            <div class="schedule-filter-field">
              <label for="filter-tanggal" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="filter-tanggal" name="tanggal"
                     value="<?php echo $escapeHtml($dateFilter); ?>">
            </div>
            <div class="schedule-filter-field">
              <label for="filter-nama-ruang" class="form-label">Nama Ruang</label>
              <select class="form-select" id="filter-nama-ruang" name="ruang">
                <option value="">Semua ruang</option>
                <?php foreach ($roomRows as $room): ?>
                  <option value="<?php echo $escapeHtml((string) $room['id_ruang']); ?>"
                    <?php echo $roomFilter === (string) $room['id_ruang'] ? 'selected' : ''; ?>>
                    <?php echo $escapeHtml((string) $room['nama_ruang']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="schedule-filter-actions">
              <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>Cari
              </button>
              <a class="btn btn-outline-secondary" href="jadwal.php">Reset</a>
            </div>
          </form>
        </section>

        <?php if ($filterWarning !== ''): ?>
          <div class="alert alert-info alert-feedback" role="status"><?php echo $escapeHtml($filterWarning); ?></div>
        <?php endif; ?>

        <section class="card-surface schedule-table-card" aria-labelledby="judul-tabel-jadwal">
          <h2 class="visually-hidden" id="judul-tabel-jadwal">Daftar reservasi yang disetujui</h2>
          <div class="table-responsive">
            <table class="table schedule-table">
              <caption class="visually-hidden">Daftar jadwal ruang yang telah disetujui.</caption>
              <thead>
                <tr>
                  <th scope="col">Tanggal</th>
                  <th scope="col">Ruang</th>
                  <th scope="col" class="text-end">Kapasitas</th>
                  <th scope="col">Lokasi</th>
                  <th scope="col">Jam</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody id="tabel-jadwal">
                <?php foreach ($scheduleRows as $reservation): ?>
                  <?php $formattedDate = DateTime::createFromFormat('!Y-m-d', (string) $reservation['tanggal']); ?>
                  <tr>
                    <td class="tabular"><?php echo $formattedDate ? $escapeHtml($formattedDate->format('d/m/Y')) : $escapeHtml((string) $reservation['tanggal']); ?></td>
                    <td><?php echo $escapeHtml((string) $reservation['nama_ruang']); ?></td>
                    <td class="text-end tabular"><?php echo (int) $reservation['kapasitas']; ?></td>
                    <td><?php echo $escapeHtml((string) $reservation['lokasi']); ?></td>
                    <td class="tabular"><?php echo $escapeHtml(substr((string) $reservation['jam_mulai'], 0, 5) . '–' . substr((string) $reservation['jam_selesai'], 0, 5)); ?></td>
                    <td><span class="availability-badge is-used">Dipesan</span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (!$scheduleRows): ?>
            <div class="empty-state text-center" id="keadaan-kosong-jadwal">
            <i class="fa-solid fa-calendar-xmark fa-2x mb-2" aria-hidden="true"></i>
              <p class="mb-0">Tidak ada reservasi disetujui yang cocok dengan filter.</p>
            </div>
          <?php endif; ?>
        </section>

        <div class="pagination-bar schedule-pagination">
          <p class="mb-0" id="info-jadwal" aria-live="polite">
            <?php if ($totalRows > 0): ?>
              Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $pageSize, $totalRows); ?> dari <?php echo $totalRows; ?> jadwal
            <?php else: ?>
              Tidak ada jadwal untuk ditampilkan.
            <?php endif; ?>
          </p>
          <?php if ($pageCount > 1): ?>
            <nav aria-label="Navigasi halaman jadwal">
              <ul class="pagination" id="pagination-jadwal">
                <?php for ($page = 1; $page <= $pageCount; $page++): ?>
                  <?php $pageQuery = http_build_query($filterParameters + ['halaman' => $page]); ?>
                  <li class="page-item<?php echo $page === $currentPage ? ' active' : ''; ?>">
                    <a class="page-link" href="jadwal.php?<?php echo $escapeHtml($pageQuery); ?>"
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