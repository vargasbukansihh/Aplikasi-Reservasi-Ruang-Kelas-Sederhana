<?php
require_once __DIR__ . '/auth.php';
requireAdminAccess();
require_once __DIR__ . '/koneksi.php';

$redirect = static function (
    string $message,
    string $type = 'success',
    ?array $formValues = null,
    bool $reopenModal = false
): void {
    $_SESSION['pengguna_notice'] = $message;
    $_SESSION['pengguna_notice_type'] = $type;

    if ($reopenModal && $formValues !== null) {
        $_SESSION['pengguna_form_values'] = $formValues;
        $_SESSION['pengguna_reopen_modal'] = true;
    } else {
        unset($_SESSION['pengguna_form_values'], $_SESSION['pengguna_reopen_modal']);
    }

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

$formValues = [
    'id' => '',
    'nim' => '',
    'nama' => '',
    'username' => '',
    'kelas' => '',
    'role' => '',
];
$rawId = $_POST['id'] ?? '';
$rawNim = $_POST['nim'] ?? '';
$rawName = $_POST['nama'] ?? '';
$rawUsername = $_POST['username'] ?? '';
$rawClass = $_POST['kelas'] ?? '';
$rawRole = $_POST['role'] ?? '';
$rawPassword = $_POST['password'] ?? '';

if (
    !is_string($rawId)
    || !is_string($rawNim)
    || !is_string($rawName)
    || !is_string($rawUsername)
    || !is_string($rawClass)
    || !is_string($rawRole)
    || !is_string($rawPassword)
) {
    $redirect('Data pengguna tidak valid.', 'danger', $formValues, true);
}

$formValues = [
    'id' => trim($rawId),
    'nim' => trim($rawNim),
    'nama' => trim($rawName),
    'username' => trim($rawUsername),
    'kelas' => trim($rawClass),
    'role' => trim($rawRole),
];
$userId = filter_var(
    $formValues['id'],
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
);
$hasValidTextLength = static function (string $value, int $max, bool $allowEmpty = false): bool {
    if ($value === '') {
        return $allowEmpty;
    }

    return preg_match('/\A.{1,' . $max . '}\z/us', $value) === 1;
};
$nimIsValid = $formValues['role'] === 'admin'
    ? $hasValidTextLength($formValues['nim'], 20, true)
    : $hasValidTextLength($formValues['nim'], 20);
$passwordIsValid = $rawPassword === ''
    || (
        strlen($rawPassword) <= 72
        && preg_match('/\A.{8,}\z/us', $rawPassword) === 1
    );

if (
    $userId === false
    || !$hasValidTextLength($formValues['nama'], 100)
    || !$hasValidTextLength($formValues['username'], 100)
    || !$hasValidTextLength($formValues['kelas'], 20, true)
    || !$nimIsValid
    || !in_array($formValues['role'], ['admin', 'mahasiswa'], true)
    || !$passwordIsValid
) {
    $redirect(
        'Periksa nama, username, NIM, kelas, peran, dan password. NIM wajib untuk mahasiswa; password baru minimal 8 karakter dan maksimal 72 byte.',
        'danger',
        $formValues,
        true
    );
}

try {
    $existingStatement = $conn->prepare(
        'SELECT role FROM pengguna WHERE id_pengguna = :id'
    );
    $existingStatement->execute(['id' => $userId]);
    $existingUser = $existingStatement->fetch(PDO::FETCH_ASSOC);
    if (!$existingUser) {
        $redirect('Pengguna yang akan diubah tidak ditemukan.', 'danger', $formValues, true);
    }

    if (
        $existingUser['role'] === 'admin'
        && $formValues['role'] !== 'admin'
        && (int) $conn->query(
            "SELECT COUNT(*) FROM pengguna WHERE role = 'admin'"
        )->fetchColumn() <= 1
    ) {
        $redirect(
            'Peran admin terakhir tidak dapat diubah menjadi mahasiswa.',
            'danger',
            $formValues,
            true
        );
    }

    $duplicateUsername = $conn->prepare(
        'SELECT 1 FROM pengguna
         WHERE username = :username AND id_pengguna <> :id
         LIMIT 1'
    );
    $duplicateUsername->execute([
        'username' => $formValues['username'],
        'id' => $userId,
    ]);
    if ($duplicateUsername->fetchColumn()) {
        $redirect('Username tersebut sudah digunakan.', 'danger', $formValues, true);
    }

    if ($formValues['nim'] !== '') {
        $duplicateNim = $conn->prepare(
            "SELECT 1 FROM pengguna
             WHERE nim = :nim AND nim <> '' AND id_pengguna <> :id
             LIMIT 1"
        );
        $duplicateNim->execute([
            'nim' => $formValues['nim'],
            'id' => $userId,
        ]);
        if ($duplicateNim->fetchColumn()) {
            $redirect('NIM tersebut sudah digunakan.', 'danger', $formValues, true);
        }
    }

    if ($rawPassword !== '') {
        $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);
        if (!is_string($passwordHash)) {
            $redirect('Password tidak dapat diproses.', 'danger', $formValues, true);
        }

        $statement = $conn->prepare(
            'UPDATE pengguna
             SET nim = :nim, nama = :nama, username = :username,
                 password = :password, kelas = :kelas, role = :role
             WHERE id_pengguna = :id'
        );
        $statement->execute([
            'nim' => $formValues['nim'],
            'nama' => $formValues['nama'],
            'username' => $formValues['username'],
            'password' => $passwordHash,
            'kelas' => $formValues['kelas'],
            'role' => $formValues['role'],
            'id' => $userId,
        ]);
    } else {
        $statement = $conn->prepare(
            'UPDATE pengguna
             SET nim = :nim, nama = :nama, username = :username,
                 kelas = :kelas, role = :role
             WHERE id_pengguna = :id'
        );
        $statement->execute([
            'nim' => $formValues['nim'],
            'nama' => $formValues['nama'],
            'username' => $formValues['username'],
            'kelas' => $formValues['kelas'],
            'role' => $formValues['role'],
            'id' => $userId,
        ]);
    }
} catch (PDOException $exception) {
    error_log('User update failed: ' . $exception->getMessage());
    $message = $exception->getCode() === '23000'
        ? 'Username atau NIM tersebut sudah digunakan.'
        : 'Pengguna tidak dapat diubah karena terjadi kesalahan database.';
    $redirect($message, 'danger', $formValues, true);
}

if ($userId === (int) $_SESSION['id_pengguna']) {
    $_SESSION['nama'] = $formValues['nama'];
    $_SESSION['role'] = $formValues['role'];
}
$redirect('Perubahan pengguna berhasil disimpan.');
