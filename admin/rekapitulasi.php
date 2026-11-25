<?php
require_once __DIR__ . '/../db/auth.php';
requireAdminAccess();
require_once __DIR__ . '/../db/koneksi.php';

$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$adminName = isset($_SESSION['nama']) && is_string($_SESSION['nama'])
    ? $_SESSION['nama']
    : 'Admin';
$databasePeriod = $conn->query(
    'SELECT CURDATE() AS today, LAST_DAY(CURDATE()) AS month_end'
)->fetch(PDO::FETCH_ASSOC);
$defaultFromDate = substr((string) $databasePeriod['today'], 0, 7) . '-01';
$defaultToDate = (string) $databasePeriod['month_end'];
$filterWarnings = [];
$fromDate = $defaultFromDate;
$toDate = $defaultToDate;
$rawFromDate = $_GET['dari'] ?? null;
$rawToDate = $_GET['sampai'] ?? null;

if ($rawFromDate !== null) {
    $parsedFromDate = is_string($rawFromDate)
        && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $rawFromDate) === 1
        ? DateTime::createFromFormat('!Y-m-d', $rawFromDate)
        : false;
    if ($parsedFromDate && $parsedFromDate->format('Y-m-d') === $rawFromDate) {
        $fromDate = $rawFromDate;
    } else {
        $filterWarnings[] = 'Tanggal awal tidak valid; periode bulan ini digunakan.';
    }
}
if ($rawToDate !== null) {
    $parsedToDate = is_string($rawToDate)
        && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $rawToDate) === 1
        ? DateTime::createFromFormat('!Y-m-d', $rawToDate)
        : false;
    if ($parsedToDate && $parsedToDate->format('Y-m-d') === $rawToDate) {
        $toDate = $rawToDate;
    } else {
        $filterWarnings[] = 'Tanggal akhir tidak valid; periode bulan ini digunakan.';
    }
}
$dateRangeIsValid = $fromDate <= $toDate;
if (!$dateRangeIsValid) {
    $filterWarnings[] = 'Tanggal akhir harus sama dengan atau setelah tanggal awal.';
}

$roomRows = $conn->query(
    'SELECT id_ruang, nama_ruang FROM ruang ORDER BY nama_ruang ASC'
)->fetchAll(PDO::FETCH_ASSOC);
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
        $roomExists = false;
        foreach ($roomRows as $room) {
            if ((int) $room['id_ruang'] === $validatedRoomId) {
                $roomExists = true;
                break;
            }
        }
        if ($roomExists) {
            $roomFilter = (string) $validatedRoomId;
        } else {
            $filterWarnings[] = 'Ruang yang dipilih tidak ditemukan; semua ruang ditampilkan.';
        }
    }
}
$filterWarning = implode(' ', array_unique($filterWarnings));

$reportRows = [];
if ($dateRangeIsValid) {
    $reportSql = "SELECT ru.id_ruang, ru.nama_ruang,
                         COUNT(*) AS total,
                         SUM(CASE WHEN r.status = 'diajukan' THEN 1 ELSE 0 END) AS submitted_count,
                         SUM(CASE WHEN r.status = 'disetujui' THEN 1 ELSE 0 END) AS approved_count,
                         SUM(CASE WHEN r.status = 'ditolak' THEN 1 ELSE 0 END) AS rejected_count,
                         SUM(CASE WHEN r.status = 'selesai' THEN 1 ELSE 0 END) AS completed_count,
                         SUM(CASE WHEN r.status = 'batal' THEN 1 ELSE 0 END) AS cancelled_count,
                         SUM(CASE WHEN r.status = 'selesai'
                                  AND r.jam_selesai > r.jam_mulai
                             THEN TIME_TO_SEC(TIMEDIFF(r.jam_selesai, r.jam_mulai)) / 3600
                             ELSE 0 END) AS completed_hours
                  FROM reservasi r
                  INNER JOIN ruang ru ON ru.id_ruang = r.id_ruang
                  WHERE r.tanggal BETWEEN :from_date AND :to_date";
    $reportParameters = [
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ];
    if ($roomFilter !== '') {
        $reportSql .= ' AND r.id_ruang = :id_ruang';
        $reportParameters['id_ruang'] = $roomFilter;
    }
    $reportSql .= ' GROUP BY ru.id_ruang, ru.nama_ruang ORDER BY total DESC, ru.nama_ruang ASC';
    $reportStatement = $conn->prepare($reportSql);
    $reportStatement->execute($reportParameters);
    $reportRows = $reportStatement->fetchAll(PDO::FETCH_ASSOC);
}

$totalReservations = 0;
$totalUsageHours = 0.0;
$mostUsedRoom = 'Belum ada data';
$highestCompletedCount = 0;
foreach ($reportRows as $reportRow) {
    $totalReservations += (int) $reportRow['total'];
    $totalUsageHours += (float) $reportRow['completed_hours'];
    $completedCount = (int) $reportRow['completed_count'];
    if (
        $completedCount > $highestCompletedCount
        || (
            $completedCount > 0
            && $completedCount === $highestCompletedCount
            && strcmp((string) $reportRow['nama_ruang'], $mostUsedRoom) < 0
        )
    ) {
        $mostUsedRoom = (string) $reportRow['nama_ruang'];
        $highestCompletedCount = $completedCount;
    }
}
$maximumRoomReservations = 0;
foreach ($reportRows as $reportRow) {
    $maximumRoomReservations = max($maximumRoomReservations, (int) $reportRow['total']);
}
$chartDescription = $reportRows
    ? 'Grafik jumlah reservasi per ruang: ' . implode('; ', array_map(
        static function (array $row): string {
            return (string) $row['nama_ruang'] . ', ' . (int) $row['total'] . ' reservasi';
        },
        $reportRows
    ))
    : 'Tidak ada data reservasi untuk ditampilkan';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan Penggunaan Ruang - Reservasi Kelas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/base.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <link href="../assets/css/rekapitulasi.css" rel="stylesheet">
</head>
<body>
  <div class="app-shell d-flex">
    <aside class="app-sidebar d-flex flex-column gap-1">
      <a class="brand" href="dasboard1.php">
        <img class="brand-mark brand-logo" src="../assets/img/logo pbl.png" alt="" aria-hidden="true">
        Reservasi Kelas
      </a>
      <p class="nav-heading" id="judul-menu">Menu Admin / Petugas</p>
      <nav aria-labelledby="judul-menu">
        <ul class="nav flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="dasboard1.php"><i class="fa-solid fa-gauge" aria-hidden="true"></i>Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="ruang.php"><i class="fa-solid fa-building" aria-hidden="true"></i>Ruang</a></li>
          <li class="nav-item"><a class="nav-link" href="data_pengguna.php"><i class="fa-solid fa-users" aria-hidden="true"></i>Pengguna</a></li>
          <li class="nav-item"><a class="nav-link" href="reservasi.php"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i>Reservasi</a></li>
          <li class="nav-item"><a class="nav-link" href="jadwal.php"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Jadwal</a></li>
          <li class="nav-item">
            <a class="nav-link active" href="rekapitulasi.php" aria-current="page">
              <i class="fa-solid fa-chart-column" aria-hidden="true"></i>Laporan
            </a>
          </li>
        </ul>
      </nav>
      <div class="flex-grow-1 sidebar-spacer"></div>
      <div class="sidebar-user">
        <span class="avatar" aria-hidden="true"></span>
        <div>
          <div class="user-name"><?php echo $escapeHtml($adminName); ?></div>
          <div class="user-role">Admin</div>
        </div>
      </div>
    </aside>

    <div class="app-main flex-grow-1">
      <header class="app-header">
        <nav aria-label="Breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dasboard1.php">Admin</a></li>
            <li class="breadcrumb-item active" aria-current="page">Laporan</li>
          </ol>
        </nav>
        <div class="user-chip"><span class="avatar avatar-sm" aria-hidden="true"></span><?php echo $escapeHtml($adminName); ?></div>
      </header>

      <main class="app-content report-content" id="konten-utama">
        <div class="report-heading">
          <div>
            <h1 class="page-title">Laporan Penggunaan Ruang</h1>
            <p class="page-subtitle">Jumlah mencakup semua status; jam pemakaian dihitung dari reservasi selesai.</p>
          </div>
          <button type="button" class="btn btn-primary" id="btn-ekspor">
            <i class="fa-solid fa-file-pdf me-2" aria-hidden="true"></i>Ekspor PDF
          </button>
        </div>

        <form class="card-surface report-filter" id="form-filter-laporan" method="get" action="rekapitulasi.php">
          <div class="report-filter-field">
            <label for="periode-dari" class="form-label">Periode Dari</label>
            <input type="date" class="form-control tabular" id="periode-dari" name="dari"
                   value="<?php echo $escapeHtml($fromDate); ?>" required>
          </div>
          <div class="report-filter-field">
            <label for="periode-sampai" class="form-label">Sampai</label>
            <input type="date" class="form-control tabular" id="periode-sampai" name="sampai"
                   value="<?php echo $escapeHtml($toDate); ?>" required>
          </div>
          <div class="report-filter-field">
            <label for="filter-ruang" class="form-label">Ruang</label>
            <select class="form-select" id="filter-ruang" name="ruang">
              <option value="">Semua ruang</option>
              <?php foreach ($roomRows as $room): ?>
                <option value="<?php echo $escapeHtml((string) $room['id_ruang']); ?>"
                  <?php echo $roomFilter === (string) $room['id_ruang'] ? 'selected' : ''; ?>>
                  <?php echo $escapeHtml((string) $room['nama_ruang']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary report-submit">
            <i class="fa-solid fa-filter me-2" aria-hidden="true"></i>Tampilkan
          </button>
        </form>

        <?php if ($filterWarning !== ''): ?>
          <div class="alert alert-info alert-feedback" role="status"><?php echo $escapeHtml($filterWarning); ?></div>
        <?php endif; ?>

        <section class="report-summary" aria-label="Ringkasan penggunaan ruang">
          <article class="card-surface summary-card">
            <h2>Total Reservasi</h2>
            <p id="total-reservasi" class="tabular"><?php echo $totalReservations; ?></p>
          </article>
          <article class="card-surface summary-card">
            <h2>Total Jam Pemakaian</h2>
            <p id="total-jam" class="tabular"><?php echo number_format($totalUsageHours, 2, ',', '.'); ?> jam</p>
          </article>
          <article class="card-surface summary-card">
            <h2>Ruang Paling Sering Dipakai</h2>
            <p id="ruang-teratas"><?php echo $escapeHtml($mostUsedRoom); ?></p>
          </article>
        </section>

        <div class="report-details">
          <section class="card-surface report-panel" aria-labelledby="judul-grafik">
            <h2 class="report-panel-title" id="judul-grafik">Reservasi per Ruang</h2>
            <div class="chart" id="grafik-ruang" role="img" aria-label="<?php echo $escapeHtml($chartDescription); ?>">
              <div class="chart-bars" id="chart-bars">
                <?php foreach ($reportRows as $reportRow): ?>
                  <?php
                  $reservationTotal = (int) $reportRow['total'];
                  $barHeight = $maximumRoomReservations > 0
                      ? max(10, min(100, (int) round($reservationTotal / $maximumRoomReservations * 10) * 10))
                      : 0;
                  ?>
                  <div class="chart-bar-item">
                    <div class="chart-bar chart-bar-<?php echo $barHeight; ?>"
                         title="<?php echo $escapeHtml((string) $reportRow['nama_ruang'] . ': ' . $reservationTotal . ' reservasi'); ?>"></div>
                    <span class="chart-label" title="<?php echo $escapeHtml((string) $reportRow['nama_ruang']); ?>">
                      <?php echo $escapeHtml((string) $reportRow['nama_ruang']); ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <section class="card-surface report-panel" aria-labelledby="judul-rekap">
            <h2 class="report-panel-title" id="judul-rekap">Rekap per Ruang</h2>
            <div class="table-responsive report-table-wrap">
              <table class="table report-table">
                <caption class="visually-hidden">Ringkasan jumlah reservasi dan jam pemakaian tiap ruang.</caption>
                <thead>
                  <tr>
                    <th scope="col">Ruang</th>
                    <th scope="col" class="text-end">Total</th>
                    <th scope="col" class="text-end">Diajukan</th>
                    <th scope="col" class="text-end">Disetujui</th>
                    <th scope="col" class="text-end">Ditolak</th>
                    <th scope="col" class="text-end">Selesai</th>
                    <th scope="col" class="text-end">Batal</th>
                    <th scope="col" class="text-end">Jam Pemakaian</th>
                  </tr>
                </thead>
                <tbody id="tabel-rekap-ruang">
                  <?php foreach ($reportRows as $reportRow): ?>
                    <tr>
                      <td><?php echo $escapeHtml((string) $reportRow['nama_ruang']); ?></td>
                      <td class="text-end tabular"><?php echo (int) $reportRow['total']; ?></td>
                      <td class="text-end tabular"><?php echo (int) $reportRow['submitted_count']; ?></td>
                      <td class="text-end tabular"><?php echo (int) $reportRow['approved_count']; ?></td>
                      <td class="text-end tabular"><?php echo (int) $reportRow['rejected_count']; ?></td>
                      <td class="text-end tabular"><?php echo (int) $reportRow['completed_count']; ?></td>
                      <td class="text-end tabular"><?php echo (int) $reportRow['cancelled_count']; ?></td>
                      <td class="text-end tabular"><?php echo number_format((float) $reportRow['completed_hours'], 2, ',', '.'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if (!$reportRows): ?>
              <p id="rekap-kosong" class="report-empty">Tidak ada rekap ruang untuk periode dan filter yang dipilih.</p>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>
  </div>

  <script src="../assets/js/rekapitulasi.js"></script>
</body>
</html>