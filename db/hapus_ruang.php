<?php
require_once __DIR__ . '/auth.php';
requireAdminAccess();
require_once __DIR__ . '/koneksi.php';

$redirect = static function (string $message, string $type = 'success'): void {
    unset($_SESSION['ruang_form_values'], $_SESSION['ruang_reopen_modal']);
    $_SESSION['ruang_notice'] = $message;
    $_SESSION['ruang_notice_type'] = $type;
    header('Location: ../admin/ruang.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect('Permintaan tidak valid.', 'danger');
}

$csrfToken = $_SESSION['csrf_ruang'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrfToken)
    || !is_string($submittedToken)
    || !hash_equals($csrfToken, $submittedToken)
) {
    $redirect('Permintaan tidak valid. Muat ulang halaman lalu coba kembali.', 'danger');
}

$rawId = $_POST['id'] ?? '';
$roomId = is_string($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]])
    : false;
if ($roomId === false) {
    $redirect('ID ruang tidak valid.', 'danger');
}

try {
    $exists = $conn->prepare('SELECT 1 FROM ruang WHERE id_ruang = :id');
    $exists->execute(['id' => $roomId]);
    if (!$exists->fetchColumn()) {
        $redirect('Ruang yang akan dihapus tidak ditemukan.', 'danger');
    }

    $reservationCount = $conn->prepare(
        'SELECT COUNT(*) FROM reservasi WHERE id_ruang = :id'
    );
    $reservationCount->execute(['id' => $roomId]);
    if ((int) $reservationCount->fetchColumn() > 0) {
        $redirect(
            'Ruang tidak dapat dihapus karena sudah memiliki riwayat reservasi. Nonaktifkan ruang jika tidak lagi digunakan.',
            'danger'
        );
    }

    $statement = $conn->prepare('DELETE FROM ruang WHERE id_ruang = :id');
    $statement->execute(['id' => $roomId]);
} catch (PDOException $exception) {
    error_log('Room deletion failed: ' . $exception->getMessage());
    $message = $exception->getCode() === '23000'
        ? 'Ruang tidak dapat dihapus karena sudah memiliki riwayat reservasi. Nonaktifkan ruang jika tidak lagi digunakan.'
        : 'Ruang tidak dapat dihapus karena terjadi kesalahan database.';
    $redirect($message, 'danger');
}

$redirect('Ruang berhasil dihapus.');
