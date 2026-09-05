<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            echo json_encode(['success' => false, 'message' => 'CSRF token tidak valid.']);
        } else {
            header('Location: list.php?msg=csrf_error');
        }
        exit;
    }
}

define('SU_TIMEOUT', 600);

function is_superuser(): bool {
    if (empty($_SESSION['superuser']) || empty($_SESSION['superuser_time'])) {
        return false;
    }

    if ((time() - (int)$_SESSION['superuser_time']) > SU_TIMEOUT) {
        $_SESSION['superuser']      = false;
        $_SESSION['superuser_time'] = null;
        return false;
    }
    return (bool)$_SESSION['superuser'];
}

function su_refresh(): void {
    $_SESSION['superuser_time'] = time();
}

function su_logout(): void {
    $_SESSION['superuser']      = false;
    $_SESSION['superuser_time'] = null;
}

function su_login(string $pin, mysqli $conn): array {

    if (!isset($_SESSION['su_attempts']))    $_SESSION['su_attempts']    = 0;
    if (!isset($_SESSION['su_locked_until'])) $_SESSION['su_locked_until'] = 0;


    if ($_SESSION['su_locked_until'] > time()) {
        $remaining = $_SESSION['su_locked_until'] - time();
        return ['success' => false, 'message' => "Terlalu banyak percobaan. Coba lagi dalam {$remaining} detik."];
    }


    if (!preg_match('/^\d{6}$/', $pin)) {
        return ['success' => false, 'message' => 'PIN harus 6 digit angka.'];
    }


    $stmt = $conn->prepare("SELECT pin_hash FROM admin_security WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($hash);
    $stmt->fetch();
    $stmt->close();

    if (!$hash) {
        return ['success' => false, 'message' => 'Konfigurasi superuser belum disetup.'];
    }



    if (password_verify($pin, $hash)) {

        $_SESSION['su_attempts']    = 0;
        $_SESSION['su_locked_until'] = 0;
        $_SESSION['superuser']      = true;
        $_SESSION['superuser_time'] = time();
        return ['success' => true, 'message' => 'Superuser aktif.'];
    }


    $_SESSION['su_attempts']++;
    if ($_SESSION['su_attempts'] >= 5) {
        $_SESSION['su_locked_until'] = time() + SU_TIMEOUT;
        $_SESSION['su_attempts']     = 0;
        return ['success' => false, 'message' => 'Terlalu banyak percobaan. Akun terkunci 10 menit.'];
    }

    $left = 5 - $_SESSION['su_attempts'];
    return ['success' => false, 'message' => "PIN salah. Sisa percobaan: {$left}."];
}






function rp(int $n): string {
    return $n > 0 ? 'Rp ' . number_format($n, 0, ',', '.') : '—';
}

function badge(string $tipe): string {
    $map = [
        'INCOME'     => 'badge-income',
        'EXPENSE'    => 'badge-expense',
        'TRANSFER'   => 'badge-transfer',
        'WITHDRAWAL' => 'badge-withdrawal',
    ];
    $cls = $map[$tipe] ?? '';
    return "<span class='badge {$cls}'>" . htmlspecialchars($tipe) . '</span>';
}

function status_badge(string $status): string {
    $cls = $status === 'CONFIRMED' ? 'badge-confirmed' : 'badge-pending';
    return "<span class='badge {$cls}'>" . htmlspecialchars($status) . '</span>';
}
