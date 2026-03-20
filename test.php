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
    $username = $_POST['username'];
    $password = $_POST['password'];
    $res  = mysqli_query($conn, "SELECT * FROM users WHERE username='$username'");
    $user = mysqli_fetch_assoc($res);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: ?page=xss");
        exit;
    } else {
        $login_error = 'Sai tài khoản hoặc mật khẩu!';
    }
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

// ── XSS: Gửi bình luận ──
if (isset($_POST['send_comment'])) {
    $comment = $_POST['comment'];
    // LỖI XSS: Không lọc dữ liệu
    mysqli_query($conn, "INSERT INTO comments (content) VALUES ('$comment')");
}

// ── TOGGLE CSRF PROTECTION ──
if (isset($_POST['toggle_csrf'])) {
    $_SESSION['csrf_protected'] = !($_SESSION['csrf_protected'] ?? false);
    header("Location: ?page=csrf");
    exit;
}
$csrf_protected = $_SESSION['csrf_protected'] ?? false;


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
$view_id   = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
$idor_user = null;
if ($view_id) {
    $res_idor  = mysqli_query($conn, "SELECT * FROM users WHERE id = $view_id");
    $idor_user = mysqli_fetch_assoc($res_idor);
}

$active_page = $_GET['page'] ?? 'xss';
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
    <link rel="stylesheet" href="style4.css">
</head>

<body>

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
                <strong><?= $_SESSION['username'] ?></strong></span>
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
            <a class="nav-item <?= $active_page === 'bac'  ? 'active' : '' ?>" href="?page=bac">🔑 &nbsp;Broken Access
                Control</a>
        </aside>


        <!-- PAGE: XSS -->
        <main <?= $active_page === 'xss' ? 'class="active"' : '' ?>>
            <div class="breadcrumb">Security › <span>Cross-Site Scripting</span></div>
            <h1 class="page-title">
                Cross-Site Scripting
                <span class="severity-badge badge-high">⚠ HIGH RISK</span>
            </h1>
            <p class="page-subtitle">Nhập script vào ô bình luận để quan sát lỗ hổng XSS</p>

            <div class="card">
                <div class="card-header">💬 Gửi bình luận</div>
                <div class="card-body">

                    <?php if (isset($_SESSION['user_id'])): ?>

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
              // LỖI XSS: In trực tiếp không dùng htmlspecialchars()
              echo "<div class='comment-item'>" . $row['content'] . "</div>";
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
                    <span class="severity-badge badge-medium">⚡ MEDIUM RISK</span>
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
                        Đang chuyển từ tài khoản: <strong><?= $_SESSION['username'] ?></strong>
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
            <div class="breadcrumb">Security › <span>Broken Access Control</span></div>
            <h1 class="page-title">
                Broken Access Control
                <span class="severity-badge badge-critical">🚨 CRITICAL</span>
            </h1>
            <p class="page-subtitle">Xem số dư ví của bất kỳ user nào qua URL</p>

            <div class="card">
                <div class="card-header">🔍 Xem số dư ví</div>
                <div class="card-body">

                    <?php if (isset($_SESSION['user_id'])): ?>

                    <?php if ($idor_user): ?>
                    <p style="font-size:13.5px;margin-bottom:8px;"><strong>Username:</strong>
                        <?= $idor_user['username'] ?></p>
                    <p style="font-size:13.5px;margin-bottom:16px;"><strong>Số dư ví:</strong>
                        <?= number_format($idor_user['balance']) ?> $</p>
                    <?php else: ?>
                    <p style="color:red;font-size:13.5px;margin-bottom:16px;">Không tìm thấy user.</p>
                    <?php endif; ?>

                    <p
                        style="font-size:13px;color:var(--orange);font-weight:600;background:var(--orange-pale);border:1px solid rgba(249,115,22,.2);border-radius:8px;padding:12px 14px;">
                        💡 Hãy thay đổi <code>user_id</code> trên URL để xem lỗ hổng IDOR!<br>
                        <span style="font-weight:400;font-size:12px;">Ví dụ: <code>?page=bac&user_id=1</code> để xem tài
                            khoản Admin</span>
                    </p>

                    <?php else: ?>
                    <p style="text-align:center;padding:24px 0;font-size:14px;color:var(--slate-400);">
                        🔒 <a href="#" onclick="document.getElementById('modal').classList.add('show')"
                            style="color:var(--sky);font-weight:600;text-decoration:none;">Đăng nhập</a> để xem số dư
                    </p>
                    <?php endif; ?>

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
                <form method="POST" action="?page=<?= $active_page ?>">
                    <label
                        style="text-transform:uppercase;font-size:11.5px;letter-spacing:.6px;font-family:'Space Mono',monospace;">Username</label>
                    <input type="text" name="username" placeholder="Nhập tên đăng nhập" required>
                    <label
                        style="text-transform:uppercase;font-size:11.5px;letter-spacing:.6px;font-family:'Space Mono',monospace;">Password</label>
                    <input type="password" name="password" placeholder="••••••••••" required>
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
    </script>

</body>

</html>