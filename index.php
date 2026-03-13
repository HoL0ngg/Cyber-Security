<?php
$conn = mysqli_connect("localhost", "root", "", "anm");

// Xử lý gửi bình luận (LỖI XSS: Không lọc dữ liệu)
if (isset($_POST['send_comment'])) {
    $comment = $_POST['comment'];
    mysqli_query($conn, "INSERT INTO comments (content) VALUES ('$comment')");
}

// Giả lập IDOR: Lấy thông tin user dựa trên ID từ URL (ví dụ: index.php?user_id=1)
$user_data = null;
if (isset($_GET['user_id'])) {
    $id = $_GET['user_id'];
    // LỖI IDOR: Không kiểm tra session, chỉ lấy theo ID từ URL
    $res = mysqli_query($conn, "SELECT * FROM users WHERE id = $id");
    $user_data = mysqli_fetch_assoc($res);
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab An Ninh Mạng</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #64748b;
            --border: #dce3ef;
            --primary: #0f766e;
            --primary-dark: #115e59;
            --danger: #b91c1c;
            --shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text);
            background:
                radial-gradient(circle at 20% 15%, #e3f5f3 0%, rgba(227, 245, 243, 0) 42%),
                radial-gradient(circle at 80% 0%, #fef0df 0%, rgba(254, 240, 223, 0) 33%),
                var(--bg);
        }

        .page {
            width: min(1080px, calc(100% - 2rem));
            margin: 2.25rem auto;
        }

        .hero {
            background: linear-gradient(120deg, #134e4a 0%, #0f766e 60%, #0a5f59 100%);
            color: #f8fafc;
            border-radius: 18px;
            padding: 1.8rem 1.8rem 1.55rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.3rem;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.2rem 0.75rem;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.18);
            margin-bottom: 0.8rem;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(1.3rem, 3vw, 2rem);
            line-height: 1.25;
        }

        .hero p {
            margin: 0.55rem 0 0;
            color: #e2f7f4;
            max-width: 720px;
            font-size: 0.97rem;
        }

        .layout {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .box {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.15rem;
            box-shadow: var(--shadow);
        }

        .box h2 {
            margin: 0;
            font-size: 1.1rem;
            color: #0f172a;
        }

        .box-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            margin-bottom: 0.7rem;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.16rem 0.62rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            background: #fff4f4;
            color: var(--danger);
            border: 1px solid #ffd5d5;
        }

        form {
            margin-top: 0.85rem;
        }

        textarea {
            width: 100%;
            min-height: 110px;
            resize: vertical;
            border: 1px solid #ced8e6;
            border-radius: 12px;
            padding: 0.72rem 0.8rem;
            font: inherit;
            color: var(--text);
            background: #fbfcff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.14);
        }

        button {
            margin-top: 0.6rem;
            border: 0;
            border-radius: 10px;
            padding: 0.6rem 1rem;
            font: inherit;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            cursor: pointer;
            transition: transform 0.15s ease, filter 0.2s ease;
        }

        button:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }

        button:active {
            transform: translateY(0);
        }

        hr {
            border: 0;
            border-top: 1px dashed #d4dcec;
            margin: 1rem 0;
        }

        h3 {
            margin: 0 0 0.45rem;
            color: #0f172a;
            font-size: 0.98rem;
        }

        .comment-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 0.55rem;
        }

        .comment-item {
            margin: 0;
            background: #f8fafc;
            border: 1px solid #e4ebf5;
            border-radius: 10px;
            padding: 0.52rem 0.7rem;
            word-break: break-word;
        }

        .muted {
            color: var(--muted);
            font-size: 0.95rem;
            margin: 0.2rem 0 0.65rem;
        }

        .profile {
            border: 1px solid #d6e4f4;
            background: linear-gradient(160deg, #f8fbff, #eef6ff);
            border-radius: 12px;
            padding: 0.85rem;
            margin: 0.7rem 0;
        }

        .profile p {
            margin: 0.25rem 0;
        }

        a {
            color: #0a66c2;
            text-decoration: none;
            font-weight: 600;
        }

        a:hover {
            text-decoration: underline;
        }

        .hint {
            margin-top: 0.9rem;
            padding: 0.62rem 0.72rem;
            border-left: 4px solid #f59e0b;
            background: #fff9ec;
            border-radius: 8px;
            color: #7c5512;
            font-style: italic;
        }

        @media (max-width: 640px) {
            .page {
                width: calc(100% - 1rem);
                margin: 1rem auto;
            }

            .hero {
                padding: 1.2rem;
                border-radius: 14px;
            }

            .box {
                border-radius: 13px;
            }
        }
    </style>
</head>

<body>
    <main class="page">
        <section class="hero">
            <span class="hero-badge">Security Demo</span>
            <h1>Lab An Ninh Mạng: XSS và Broken Access Control</h1>
            <p>
                Mô hình thực hành minh họa các lỗ hổng web phổ biến. Giao diện được thiết kế để trực quan hơn,
                giúp bạn dễ quan sát dữ liệu đầu vào và hành vi của hệ thống.
            </p>
        </section>

        <section class="layout">
            <div class="box">
                <div class="box-title">
                    <h2>1. Lỗ hổng Cross-Site Scripting (XSS)</h2>
                    <span class="tag">Nguy cơ cao</span>
                </div>

                <p class="muted">Nhập nội dung bình luận để xem cách dữ liệu được render trực tiếp ra giao diện.</p>

                <form method="POST">
                    <textarea name="comment" placeholder="Nhập bình luận của bạn..."></textarea>
                    <button type="submit" name="send_comment">Gửi bình luận</button>
                </form>

                <hr>

                <h3>Các bình luận:</h3>
                <div class="comment-list">
                    <?php
                    $comments = mysqli_query($conn, "SELECT * FROM comments");
                    while ($row = mysqli_fetch_assoc($comments)) {
                        // LỖI: In trực tiếp ra màn hình mà không dùng htmlspecialchars()
                        echo "<p class='comment-item'>" . $row['content'] . "</p>";
                    }
                    ?>
                </div>
            </div>
            <!-- <script>alert('Máy bạn đã bị hack bởi Long!');</script> -->

            <div class="box">
                <div class="box-title">
                    <h2>2. Lỗ hổng Broken Access Control (IDOR)</h2>
                    <span class="tag">Mất kiểm soát truy cập</span>
                </div>

                <p class="muted">Bạn đang xem hồ sơ của ID: <strong><?php echo $_GET['user_id'] ?? 'Chưa chọn'; ?></strong></p>

                <?php if ($user_data): ?>
                    <div class="profile">
                        <p><strong>Username:</strong> <?php echo $user_data['username']; ?></p>
                        <p><strong>Số dư ví:</strong> <?php echo $user_data['balance']; ?>$</p>
                    </div>
                <?php else: ?>
                    <p><a href="?user_id=2">Xem ví của tôi (ID: 2)</a></p>
                <?php endif; ?>

                <p class="hint">Thử thách: Hãy đổi số trên URL thành 1 để xem ví của Admin!</p>
            </div>
        </section>
    </main>

</body>

</html>