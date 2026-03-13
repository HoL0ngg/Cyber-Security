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
    <title>Lab An Ninh Mạng</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px;
            line-height: 1.6;
        }

        .box {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        h2 {
            color: #d9534f;
        }
    </style>
</head>

<body>
    <h1>Siuuuuu</h1>

    <div class="box">
        <h2>1. Lỗ hổng Cross-Site Scripting (XSS)</h2>
        <form method="POST">
            <textarea name="comment" placeholder="Nhập bình luận của bạn..."></textarea><br>
            <button type="submit" name="send_comment">Gửi bình luận</button>
        </form>
        <hr>
        <h3>Các bình luận:</h3>
        <?php
        $comments = mysqli_query($conn, "SELECT * FROM comments");
        while ($row = mysqli_fetch_assoc($comments)) {
            // LỖI: In trực tiếp ra màn hình mà không dùng htmlspecialchars()
            echo "<p>" . $row['content'] . "</p>";
        }
        ?>
    </div>
    <!-- <script>alert('Máy bạn đã bị hack bởi Long!');</script> -->

    <div class="box">
        <h2>2. Lỗ hổng Broken Access Control (IDOR)</h2>
        <p>Bạn đang xem hồ sơ của ID: <strong><?php echo $_GET['user_id'] ?? 'Chưa chọn'; ?></strong></p>
        <?php if ($user_data): ?>
            <div style="background: #f4f4f4; padding: 10px;">
                <p>Username: <?php echo $user_data['username']; ?></p>
                <p>Số dư ví: <?php echo $user_data['balance']; ?>$</p>
            </div>
        <?php else: ?>
            <p><a href="?user_id=2">Xem ví của tôi (ID: 2)</a></p>
        <?php endif; ?>
        <p><i>Thử thách: Hãy đổi số trên URL thành 1 để xem ví của Admin!</i></p>
    </div>

</body>

</html>