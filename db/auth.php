<?php
function requireAdminAccess(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (
        isset($_SESSION['id_pengguna'], $_SESSION['role'])
        && $_SESSION['role'] === 'admin'
    ) {
        return;
    }

    $destination = (
        isset($_SESSION['id_pengguna'], $_SESSION['role'])
        && $_SESSION['role'] === 'mahasiswa'
    )
        ? '../mahasiswa/dasboard.php'
        : '../login.php';

    header('Location: ' . $destination);
    exit;
}

function requireStudentAccess(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $rawStudentId = $_SESSION['id_pengguna'] ?? null;
    $studentId = is_int($rawStudentId) || is_string($rawStudentId)
        ? filter_var(
            $rawStudentId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
        )
        : false;

    if ($studentId !== false && ($_SESSION['role'] ?? null) === 'mahasiswa') {
        return;
    }

    $destination = ($_SESSION['role'] ?? null) === 'admin'
        ? '../admin/dasboard1.php'
        : '../login.php';
    header('Location: ' . $destination);
    exit;
}
