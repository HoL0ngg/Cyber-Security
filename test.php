<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "anm");

//chống tấn công csrf bằng csrf_token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── ĐĂNG KÝ ──
$register_error = '';
$register_ok = '';
if (isset($_POST['do_register'])) {
    $username = trim($_POST['reg_username']);
    $password = $_POST['reg_password'];
    // Kiểm tra username đã tồn tại chưa
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
    if (mysqli_num_rows($check) > 0) {
        $register_error = 'Tên đăng nhập đã tồn tại!';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT); //bcrypt
        mysqli_query($conn, "INSERT INTO users (username, password, balance) VALUES ('$username', '$hashed', 1000)");
        $register_ok = 'Tạo tài khoản thành công! Hãy đăng nhập.';
    }
}

// ── ĐĂNG NHẬP ──
$login_error = '';
if (isset($_POST['do_login'])) {

    header('Content-Type: application/json');

    $username = $_POST['username'];
    $password = $_POST['password'];
    $res  = mysqli_query($conn, "SELECT * FROM users WHERE username='$username'");
    $user = mysqli_fetch_assoc($res);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];

        echo json_encode([
            "success" => true
        ]);
        // header("Location: ?page=xss");
        exit;
    } else {
        $login_error = 'Sai tài khoản hoặc mật khẩu!';
        echo json_encode([
            "success" => false,
            "message" => "Sai tài khoản hoặc mật khẩu!"
        ]);
    }
    exit;
}

// ── ĐĂNG XUẤT ──

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ?page=xss");
    exit;
}

// xóa session cả client

// if (isset($_GET['logout'])) {
//     session_destroy();

//     // Xóa cookie PHPSESSID trong trình duyệt
//     setcookie('PHPSESSID', '', time() - 3600, '/');

//     header("Location: ?page=xss");
//     exit;
// }

// ── TOGGLE XSS PROTECTION ──
if (isset($_POST['toggle_xss'])) {
    $_SESSION['xss_protected'] = !($_SESSION['xss_protected'] ?? false);
    header("Location: ?page=xss");
    exit;
}
$xss_protected = $_SESSION['xss_protected'] ?? false;

// ── XSS: Gửi bình luận ──
$xss_msg = '';
if (isset($_POST['send_comment'])) {
    $comment = trim($_POST['comment'] ?? '');

    if ($comment !== '') {
        $stmt = $conn->prepare("INSERT INTO comments (content) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param("s", $comment);
            $stmt->execute();
            $stmt->close();
        }

        $xss_msg = $xss_protected
            ? 'Đã lưu bình luận. Script sẽ không chạy vì đầu ra được mã hóa.'
            : 'Đã lưu bình luận.';
    }
}

// ── TOGGLE CSRF PROTECTION ──
if (isset($_POST['toggle_csrf'])) {
    $_SESSION['csrf_protected'] = !($_SESSION['csrf_protected'] ?? false);
    header("Location: ?page=csrf");
    exit;
}
$csrf_protected = $_SESSION['csrf_protected'] ?? false;

// ── TOGGLE IDOR PROTECTION ──
if (isset($_POST['toggle_idor'])) {
    $_SESSION['idor_protected'] = !($_SESSION['idor_protected'] ?? false);
    $redirect_user_id = isset($_SESSION['user_id']) ? '&user_id=' . (int)$_SESSION['user_id'] : '';
    header("Location: ?page=bac" . $redirect_user_id);
    exit;
}
$idor_protected = $_SESSION['idor_protected'] ?? false;

$active_page = $_GET['page'] ?? 'xss';
$allowed_pages = ['xss', 'csrf', 'bac'];
if (!in_array($active_page, $allowed_pages, true)) {
    $active_page = 'xss';
}


// ── CSRF: Chuyển tiền ──
$csrf_msg = '';
$csrf_err = '';

if (isset($_POST['transfer']) && isset($_SESSION['user_id'])) {
    $from   = $_SESSION['user_id'];
    $to     = $_POST['to'];
    $amount = $_POST['amount'];

    // Bật: kiểm tra CSRF token | Tắt: bỏ qua kiểm tra
    if ($csrf_protected && (!isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
        $csrf_err = '🛡️ Yêu cầu bị chặn! CSRF token không hợp lệ.';
    } else {
        if (!is_numeric($amount) || $amount <= 0) {
            $csrf_err = 'Số tiền không hợp lệ!';
        } else {
            $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $to");
            if (mysqli_num_rows($check) === 0) {
                $csrf_err = 'Người nhận không tồn tại!';
            } else {
                $res_balance = mysqli_query($conn, "SELECT balance FROM users WHERE id = $from");
                $sender      = mysqli_fetch_assoc($res_balance);
                if ($sender['balance'] < $amount) {
                    $csrf_err = 'Số dư không đủ! Hiện có: ' . $sender['balance'] . '$';
                } else {
                    mysqli_query($conn, "UPDATE users SET balance = balance - $amount WHERE id = $from");
                    mysqli_query($conn, "UPDATE users SET balance = balance + $amount WHERE id = $to");
                    $csrf_msg = 'Chuyển tiền thành công!';
                }
            }
        }
    }
}

// ── BAC / IDOR ──
$idor_user = null;
$current_session_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$requested_view_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$view_id = $requested_view_id ?? $current_session_id;
$show_idor_alert = false;

if ($active_page === 'bac' && $idor_protected && $current_session_id && $requested_view_id !== null && $requested_view_id !== $current_session_id) {
    $show_idor_alert = true;
    $view_id = $current_session_id;
}

if ($view_id) {
    $res_idor  = mysqli_query($conn, "SELECT * FROM users WHERE id = " . (int)$view_id);
    $idor_user = mysqli_fetch_assoc($res_idor);
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecurePanel — Admin Dashboard</title>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php if ($show_idor_alert): ?>
        <script>
            // Sử dụng setTimeout để đảm bảo trang đã load xong mới hiện alert
            window.onload = function() {
                alert('Cảnh báo bảo mật: Bạn không có quyền xem thông tin của user khác!');
                // Sau khi alert xong, có thể điều hướng về đúng URL an toàn để thanh địa chỉ trông sạch sẽ
                window.location.href = "?page=bac&user_id=<?= $current_session_id ?>";
            };
        </script>
    <?php endif; ?>
    <!-- HEADER -->
    <header>
        <div class="logo">
            <div class="logo-icon">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
            </div>
            <span class="logo-text">Secure<span>Panel</span></span>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:13.5px;color:var(--slate-500);">👤
                    <strong><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></span>
                <a href="?logout=1"
                    style="background:var(--slate-100);color:var(--slate-600);border:1px solid var(--slate-200);padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;">Đăng
                    xuất</a>
            </div>
        <?php else: ?>
            <button class="btn-login" onclick="document.getElementById('modal').classList.add('show')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                    <polyline points="10,17 15,12 10,7" />
                    <line x1="15" y1="12" x2="3" y2="12" />
                </svg>
                Login
            </button>
        <?php endif; ?>
    </header>

    <!-- LAYOUT -->
    <div class="layout">

        <!-- SIDEBAR -->
        <aside>
            <div class="section-label">Security Modules</div>
            <a class="nav-item <?= $active_page === 'xss'  ? 'active' : '' ?>" href="?page=xss">🛡️ &nbsp;Cross-Site
                Scripting</a>
            <a class="nav-item <?= $active_page === 'csrf' ? 'active' : '' ?>" href="?page=csrf">🔒 &nbsp;Cross-Site
                Request Forgery</a>

            <a class="nav-item <?= $active_page === 'bac'  ? 'active' : '' ?>"
                href="?page=bac<?= isset($_SESSION['user_id']) ? "&user_id=" . $_SESSION['user_id'] : '' ?>">🔑
                &nbsp;IDOR</a>

        </aside>


        <!-- PAGE: XSS -->
        <main <?= $active_page === 'xss' ? 'class="active"' : '' ?>>
            <div class="breadcrumb">Security › <span>Cross-Site Scripting</span></div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:4px;">
                <h1 class="page-title" style="margin:0;">
                    Cross-Site Scripting
                    <span class="severity-badge badge-high">⚠ HIGH RISK</span>
                </h1>

                <form method="POST" action="?page=xss" style="flex-shrink:0;">
                    <input type="hidden" name="toggle_xss" value="1">
                    <button type="submit" style="
                        display:flex;align-items:center;gap:8px;
                        padding:8px 16px;border-radius:8px;border:none;cursor:pointer;
                        font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif;
                        background:<?= $xss_protected ? '#f0fdf4' : '#fef2f2' ?>;
                        color:<?= $xss_protected ? '#15803d' : '#dc2626' ?>;
                        border:1.5px solid <?= $xss_protected ? 'rgba(34,197,94,.3)' : 'rgba(239,68,68,.3)' ?>;">
                        <span
                            style="width:10px;height:10px;border-radius:50%;background:<?= $xss_protected ? '#22c55e' : '#ef4444' ?>;display:inline-block;"></span>
                        <?= $xss_protected ? '🛡️ Đang bảo vệ' : '⚠️ Đang bị tấn công' ?>
                    </button>
                </form>
            </div>
            <p class="page-subtitle">
                <?= $xss_protected
                    ? '✅ Đang dùng htmlspecialchars() khi hiển thị bình luận. Script sẽ được hiển thị như văn bản.'
                    : '❌ Chưa mã hóa đầu ra. Script có thể chạy khi bình luận được hiển thị.' ?>
            </p>

            <div class="card">
                <div class="card-header">💬 Gửi bình luận</div>
                <div class="card-body">

                    <?php if (isset($_SESSION['user_id'])): ?>

                        <?php if ($xss_msg): ?>
                            <p style="color:green;font-weight:600;font-size:13.5px;margin-bottom:14px;">✅
                                <?= htmlspecialchars($xss_msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>

                        <form method="POST" action="?page=xss">
                            <label>Nội dung bình luận</label>
                            <textarea name="comment" placeholder="Thử nhập: <script>alert('XSS!')</script>"></textarea>
                            <button type="submit" name="send_comment">Gửi bình luận</button>
                        </form>

                        <hr>

                        <h3 style="font-size:13.5px;font-weight:700;color:var(--slate-700);margin-bottom:12px;">Các bình
                            luận:</h3>
                        <?php
                        $comments = mysqli_query($conn, "SELECT * FROM comments ORDER BY id DESC");
                        while ($row = mysqli_fetch_assoc($comments)) {
                            $content = $xss_protected
                                ? htmlspecialchars($row['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                                : $row['content'];
                            echo "<div class='comment-item'>" . $content . "</div>";
                        }
                        ?>

                    <?php else: ?>
                        <p style="text-align:center;padding:24px 0;font-size:14px;color:var(--slate-400);">
                            🔒 <a href="#" onclick="document.getElementById('modal').classList.add('show')"
                                style="color:var(--sky);font-weight:600;text-decoration:none;">Đăng nhập</a> để gửi bình
                            luận
                        </p>
                    <?php endif; ?>

                </div>
            </div>
        </main>


        <!-- PAGE: CSRF -->
        <main <?= $active_page === 'csrf' ? 'class="active"' : '' ?>>
            <div class="breadcrumb">Security › <span>Cross-Site Request Forgery</span></div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h1 class="page-title">
                    Cross-Site Request Forgery
                    <!-- <span class="severity-badge badge-medium">⚡ MEDIUM RISK</span> -->
                </h1>

                <!-- Toggle bảo vệ CSRF -->
                <form method="POST" action="?page=csrf" style="flex-shrink:0;">
                    <input type="hidden" name="toggle_csrf" value="1">
                    <button type="submit" style="
                        display:flex;align-items:center;gap:8px;
                        padding:8px 16px;border-radius:8px;border:none;cursor:pointer;
                        font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif;
                        background:<?= $csrf_protected ? '#f0fdf4' : '#fef2f2' ?>;
                        color:<?= $csrf_protected ? '#15803d' : '#dc2626' ?>;
                        border:1.5px solid <?= $csrf_protected ? 'rgba(34,197,94,.3)' : 'rgba(239,68,68,.3)' ?>;">
                        <span
                            style="width:10px;height:10px;border-radius:50%;background:<?= $csrf_protected ? '#22c55e' : '#ef4444' ?>;display:inline-block;"></span>
                        <?= $csrf_protected ? '🛡️ Đang bảo vệ' : '⚠️ Đang bị tấn công' ?>
                    </button>
                </form>
            </div>

            <p class="page-subtitle" style="margin-bottom:50px;">
                <?= $csrf_protected
                    ? '✅ CSRF token đang bật — evil.html sẽ bị chặn'
                    : '❌ Không có CSRF token — dễ bị tấn công' ?>
            </p>

            <div class="card" style="max-width:460px;">
                <div class="card-header">💸 Chuyển tiền</div>

                <?php if ($csrf_err): ?>
                    <p
                        style="color:<?= str_contains($csrf_err, 'CSRF') ? '#dc2626' : 'red' ?>;font-size:13.5px;margin:16px 20px 0;
                    <?= str_contains($csrf_err, 'CSRF') ? 'background:#fef2f2;padding:10px 14px;border-radius:7px;border:1px solid rgba(239,68,68,.2);' : '' ?>">
                        ⚠️ <?= $csrf_err ?></p>
                <?php endif; ?>

                <div class="card-body">
                    <?php if (isset($_SESSION['user_id'])): ?>

                        <?php if ($csrf_msg): ?>
                            <p style="color:green;font-weight:600;font-size:13.5px;margin-bottom:14px;">✅ <?= $csrf_msg ?></p>
                        <?php endif; ?>

                        <p style="font-size:13px;color:var(--slate-500);margin-bottom:16px;">
                            Đang chuyển từ tài khoản: <strong><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                        </p>

                        <form method="POST" action="?page=csrf">
                            <?php if ($csrf_protected): ?>
                                <!-- Token được nhúng vào form khi bật bảo vệ -->
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <?php endif; ?>
                            <label>Tới user ID</label>
                            <input type="number" name="to" placeholder="ID người nhận">
                            <label>Số tiền ($)</label>
                            <input type="number" name="amount" placeholder="Nhập số tiền">
                            <button type="submit" name="transfer">Chuyển tiền</button>
                        </form>

                    <?php else: ?>
                        <p style="text-align:center;padding:24px 0;font-size:14px;color:var(--slate-400);">
                            🔒 <a href="#" onclick="document.getElementById('modal').classList.add('show')"
                                style="color:var(--sky);font-weight:600;text-decoration:none;">Đăng nhập</a> để chuyển tiền
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        </main>

        <!-- PAGE: BAC -->
        <main <?= $active_page === 'bac' ? 'class="active"' : '' ?>>
            <div class="breadcrumb">Security › <span>Insecure Direct Object Reference</span></div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:4px;">
                <h1 class="page-title" style="margin:0;">
                    Insecure Direct Object Reference
                </h1>

                <form method="POST" action="?page=bac<?= isset($_SESSION['user_id']) ? '&user_id=' . (int)$_SESSION['user_id'] : '' ?>" style="flex-shrink:0;">
                    <input type="hidden" name="toggle_idor" value="1">
                    <button type="submit" style="
                        display:flex;align-items:center;gap:8px;
                        padding:8px 16px;border-radius:8px;border:none;cursor:pointer;
                        font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif;
                        background:<?= $idor_protected ? '#f0fdf4' : '#fef2f2' ?>;
                        color:<?= $idor_protected ? '#15803d' : '#dc2626' ?>;
                        border:1.5px solid <?= $idor_protected ? 'rgba(34,197,94,.3)' : 'rgba(239,68,68,.3)' ?>;">
                        <span
                            style="width:10px;height:10px;border-radius:50%;background:<?= $idor_protected ? '#22c55e' : '#ef4444' ?>;display:inline-block;"></span>
                        <?= $idor_protected ? '🛡️ Đang bảo vệ' : '⚠️ Đang bị tấn công' ?>
                    </button>
                </form>
            </div>
            <p class="page-subtitle">
                <?= $idor_protected
                    ? '✅ Đang khóa truy cập theo ID URL. Khi nhập user_id khác, hệ thống sẽ trả về tài khoản của chính bạn.'
                    : '❌ Cho phép xem hồ sơ theo user_id trên URL để mô phỏng IDOR.' ?>
            </p>

            <div class="card">
                <div class="card-header">🛠️ Quản lý tài khoản cá nhân</div>

                <div class="card-body">
                    <?php if (isset($_SESSION['user_id'])): ?>

                        <?php if ($idor_user): ?>
                            <!-- PHẦN 1: Hiển thị thông tin tĩnh -->
                            <div id="info-display">
                                <p style="font-size:13.5px;margin-bottom:8px;"><strong>Username:</strong>
                                    <?= htmlspecialchars($idor_user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                                <p style="font-size:13.5px;margin-bottom:8px;"><strong>CCCD:</strong>
                                    <?= !empty($idor_user['citizen_id']) ? htmlspecialchars($idor_user['citizen_id']) : 'Chua cap nhat' ?>
                                </p>
                                <p style="font-size:13.5px;margin-bottom:8px;"><strong>So dien thoai:</strong>
                                    <?= !empty($idor_user['phone']) ? htmlspecialchars($idor_user['phone']) : 'Chua cap nhat' ?>
                                </p>
                                <p style="font-size:13.5px;margin-bottom:8px;"><strong>Dia chi nha:</strong>
                                    <?= !empty($idor_user['home_address']) ? htmlspecialchars($idor_user['home_address']) : 'Chua cap nhat' ?>
                                </p>
                                <p style="font-size:13.5px;margin-bottom:16px;"><strong>Số dư ví:</strong>
                                    <span
                                        style="color:var(--emerald); font-weight:bold;"><?= number_format($idor_user['balance']) ?>
                                        $</span>
                                </p>

                                <button type="button" onclick="showEditForm()"
                                    style="background:var(--slate-800); color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600; margin-bottom: 20px;">
                                    ✏️ Chỉnh sửa thông tin
                                </button>
                            </div>
                            <!-- PHẦN 2: Form cập nhật thông tin (Mặc định ẩn) -->
                            <div id="edit-form" style="display: none;margin-top: 7px;">
                                <h4 style="font-size: 14px; margin: 15px 0 10px 0;">Cập nhật hồ sơ</h4>
                                <form method="POST" action="handle/update_profile.php?user_id=<?= (int)($requested_view_id ?? $current_session_id) ?>"
                                    style="margin-bottom: 20px;">
                                    <div style="margin-bottom: 12px;">
                                        <label
                                            style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Username
                                            mới:</label>
                                        <input type="text" name="new_username"
                                            value="<?= htmlspecialchars($idor_user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                            style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                    </div>

                                    <div style="margin-bottom: 12px;">
                                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Can
                                            cuoc cong dan:</label>
                                        <input type="text" name="new_citizen_id"
                                            value="<?= htmlspecialchars($idor_user['citizen_id'] ?? '') ?>"
                                            style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                    </div>

                                    <div style="margin-bottom: 12px;">
                                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">So
                                            dien thoai:</label>
                                        <input type="text" name="new_phone"
                                            value="<?= htmlspecialchars($idor_user['phone'] ?? '') ?>"
                                            style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                    </div>

                                    <div style="margin-bottom: 12px;">
                                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Dia
                                            chi nha:</label>
                                        <input type="text" name="new_address"
                                            value="<?= htmlspecialchars($idor_user['home_address'] ?? '') ?>"
                                            style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                    </div>

                                    <div style="margin-bottom: 12px;">
                                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Mật
                                            khẩu mới:</label>
                                        <input type="password" name="new_password" placeholder="Để trống nếu không đổi"
                                            style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                    </div>

                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit"
                                            style="background:var(--sky); color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600;">
                                            Lưu thay đổi
                                        </button>
                                        <button type="button" onclick="hideEditForm()"
                                            style="background:var(--slate-200); color:var(--slate-700); border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600;">
                                            Hủy
                                        </button>
                                    </div>
                                </form>
                            </div>

                        <?php else: ?>
                            <p style="color:red; font-size:13.5px; margin-bottom:16px;">❌ Không tìm thấy user.</p>
                        <?php endif; ?>

                    <?php else: ?>
                        <p style="text-align:center; padding:24px 0; font-size:14px; color:var(--slate-400);">
                            🔒 <a href="#" onclick="document.getElementById('modal').classList.add('show')"
                                style="color:var(--sky); font-weight:600; text-decoration:none;">Đăng nhập</a> để quản lý hồ
                            sơ
                        </p>
                    <?php endif; ?>
                </div>
            </div>
    </div>
    </main>

    </div>

    <!-- LOGIN MODAL -->
    <div class="modal-overlay" id="modal" onclick="if(event.target===this)this.classList.remove('show')">
        <div class="modal">
            <button class="modal-close" onclick="document.getElementById('modal').classList.remove('show')">✕</button>

            <div class="modal-header">
                <div class="logo-icon" style="width:32px;height:32px;border-radius:8px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                </div>
                <div>
                    <div class="modal-title" id="modal-title">Đăng nhập</div>
                    <div class="modal-sub">Tài khoản hệ thống</div>
                </div>
            </div>

            <!-- Tab switcher -->
            <div
                style="display:flex;gap:0;margin-bottom:20px;border:1px solid var(--slate-200);border-radius:8px;overflow:hidden;">
                <button type="button" id="tab-login" onclick="switchTab('login')"
                    style="flex:1;padding:9px;font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;border:none;background:var(--sky);color:white;transition:all .15s;">
                    Đăng nhập
                </button>
                <button type="button" id="tab-register" onclick="switchTab('register')"
                    style="flex:1;padding:9px;font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;border:none;background:var(--slate-100);color:var(--slate-500);transition:all .15s;">
                    Tạo tài khoản
                </button>
            </div>

            <!-- FORM ĐĂNG NHẬP -->
            <div id="form-login">
                <?php if ($login_error): ?>
                    <p
                        style="color:var(--red);font-size:13px;margin-bottom:12px;background:var(--red-pale);padding:10px 14px;border-radius:7px;">
                        ⚠️ <?= $login_error ?></p>
                <?php endif; ?>
                <form method="POST" action="?page=<?= $active_page ?>" id="frmLogin">
                    <label
                        style="text-transform:uppercase;font-size:11.5px;letter-spacing:.6px;font-family:'Space Mono',monospace;">Username</label>
                    <input type="text" name="username" placeholder="Nhập tên đăng nhập" required>
                    <label
                        style="text-transform:uppercase;font-size:11.5px;letter-spacing:.6px;font-family:'Space Mono',monospace;">Password</label>
                    <div style="position: relative">
                        <input type="password" name="password" id="password" placeholder="••••••••••" required>
                        <span onclick="togglePassword()"
                            style="position:absolute;right:17px;top:40%;transform:translateY(-50%);cursor:pointer;">👁️
                        </span>
                    </div>
                    <button type="submit" name="do_login"
                        style="width:100%;padding:12px;font-size:14.5px;margin-top:4px;">🔐 Đăng nhập</button>
                </form>
            </div>

            <!-- FORM ĐĂNG KÝ -->
            <div id="form-register" style="display:none;">
                <?php if ($register_error): ?>
                    <p
                        style="color:var(--red);font-size:13px;margin-bottom:12px;background:var(--red-pale);padding:10px 14px;border-radius:7px;">
                        ⚠️ <?= $register_error ?></p>
                <?php endif; ?>
                <?php if ($register_ok): ?>
                    <p
                        style="color:green;font-size:13px;margin-bottom:12px;background:var(--green-pale);padding:10px 14px;border-radius:7px;">
                        ✅ <?= $register_ok ?></p>
                <?php endif; ?>
                <form method="POST" action="?page=<?= $active_page ?>">
                    <label
                        style="text-transform:uppercase;font-size:11.5px;letter-spacing:.6px;font-family:'Space Mono',monospace;">Username</label>
                    <input type="text" name="reg_username" placeholder="Tên đăng nhập mới" required>
                    <label
                        style="text-transform:uppercase;font-size:11.5px;letter-spacing:.6px;font-family:'Space Mono',monospace;">Password</label>
                    <input type="password" name="reg_password" placeholder="Mật khẩu" required>
                    <button type="submit" name="do_register"
                        style="width:100%;padding:12px;font-size:14.5px;margin-top:4px;">📝 Tạo tài khoản</button>
                </form>
            </div>

        </div>
    </div>

    <script>
        function switchTab(tab) {
            const isLogin = tab === 'login';
            document.getElementById('form-login').style.display = isLogin ? 'block' : 'none';
            document.getElementById('form-register').style.display = isLogin ? 'none' : 'block';
            document.getElementById('tab-login').style.background = isLogin ? 'var(--sky)' : 'var(--slate-100)';
            document.getElementById('tab-login').style.color = isLogin ? 'white' : 'var(--slate-500)';
            document.getElementById('tab-register').style.background = isLogin ? 'var(--slate-100)' : 'var(--sky)';
            document.getElementById('tab-register').style.color = isLogin ? 'var(--slate-500)' : 'white';
        }
        <?php if ($register_error || $register_ok): ?>
            switchTab('register');
        <?php endif; ?>

        document.getElementById("frmLogin").addEventListener("submit", function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append("do_login", "1");
            fetch("?page=<?= $active_page ?>", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = "?page=xss";
                    } else {
                        alert(data.message);
                    }
                })

        });

        function togglePassword() {
            const ele = document.getElementById("password");
            if (ele.type === "password") {
                ele.type = "text";
            } else {
                ele.type = "password";
            }
        }

        function showEditForm() {
            document.getElementById('info-display').style.display = 'none';
            document.getElementById('edit-form').style.display = 'block';
        }

        function hideEditForm() {
            document.getElementById('info-display').style.display = 'block';
            document.getElementById('edit-form').style.display = 'none';
        }
    </script>

</body>

</html>