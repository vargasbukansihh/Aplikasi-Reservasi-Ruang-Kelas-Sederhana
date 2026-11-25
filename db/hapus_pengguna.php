<?php
require_once __DIR__ . '/auth.php';
requireAdminAccess();
require_once __DIR__ . '/koneksi.php';

$redirect = static function (string $message, string $type = 'success'): void {
    unset($_SESSION['pengguna_form_values'], $_SESSION['pengguna_reopen_modal']);
    $_SESSION['pengguna_notice'] = $message;
    $_SESSION['pengguna_notice_type'] = $type;
    header('Location: ../admin/data_pengguna.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect('Permintaan tidak valid.', 'danger');
}

$csrfToken = $_SESSION['csrf_pengguna'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrfToken)
    || !is_string($submittedToken)
    || !hash_equals($csrfToken, $submittedToken)
) {
    $redirect('Permintaan tidak valid. Muat ulang halaman lalu coba kembali.', 'danger');
}

$rawId = $_POST['id'] ?? '';
$userId = is_string($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]])
    : false;
if ($userId === false) {
    $redirect('ID pengguna tidak valid.', 'danger');
}

if ($userId === (int) $_SESSION['id_pengguna']) {
    $redirect('Anda tidak dapat menghapus akun yang sedang digunakan.', 'danger');
}

try {
    $existingStatement = $conn->prepare(
        'SELECT role FROM pengguna WHERE id_pengguna = :id'
    );
    $existingStatement->execute(['id' => $userId]);
    $existingUser = $existingStatement->fetch(PDO::FETCH_ASSOC);
    if (!$existingUser) {
        $redirect('Pengguna yang akan dihapus tidak ditemukan.', 'danger');
    }

    if (
        $existingUser['role'] === 'admin'
        && (int) $conn->query(
            "SELECT COUNT(*) FROM pengguna WHERE role = 'admin'"
        )->fetchColumn() <= 1
    ) {
        $redirect('Admin terakhir tidak dapat dihapus.', 'danger');
    }

    $reservationCount = $conn->prepare(
        'SELECT COUNT(*) FROM reservasi WHERE id_pengguna = :id'
    );
    $reservationCount->execute(['id' => $userId]);
    if ((int) $reservationCount->fetchColumn() > 0) {
        $redirect(
            'Pengguna tidak dapat dihapus karena memiliki riwayat reservasi.',
            'danger'
        );
    }

    $statement = $conn->prepare('DELETE FROM pengguna WHERE id_pengguna = :id');
    $statement->execute(['id' => $userId]);
} catch (PDOException $exception) {
    error_log('User deletion failed: ' . $exception->getMessage());
    $message = $exception->getCode() === '23000'
        ? 'Pengguna tidak dapat dihapus karena masih terkait dengan data reservasi.'
        : 'Pengguna tidak dapat dihapus karena terjadi kesalahan database.';
    $redirect($message, 'danger');
}

$redirect('Pengguna berhasil dihapus.');
