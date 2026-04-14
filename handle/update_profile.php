  <?php
    session_start();

    $conn = mysqli_connect("localhost", "root", "", "anm");

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['user_id'])) {
        $target_id = $_GET['user_id'];
        $session_id = $_SESSION['user_id'];
        $new_name = trim($_POST['new_username'] ?? '');
        $new_citizen_id = trim($_POST['new_citizen_id'] ?? '');
        $new_phone = trim($_POST['new_phone'] ?? '');
        $new_address = trim($_POST['new_address'] ?? '');
        $new_password = !empty($_POST['new_password']) ? $_POST['new_password'] : null;

        // if($target_id != $session_id){
        //     die("Hanh dong bi chan: Ban khong the sua doi du lieu cua nguoi khac!");
        // }
        if ($new_password) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = 'UPDATE users SET username = ?, citizen_id = ?, phone = ?, home_address = ?, password = ? WHERE id = ?';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $new_name, $new_citizen_id, $new_phone, $new_address, $hashed, $target_id);
        } else {
            $sql = 'UPDATE users SET username = ?, citizen_id = ?, phone = ?, home_address = ? WHERE id = ?';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $new_name, $new_citizen_id, $new_phone, $new_address, $target_id);
        }

        if ($stmt->execute()) {
            header("Location: /test.php?page=bac&user_id=" . $target_id . "&success=1");
            exit();
        } else {
            echo "Co loi khi update " . $conn->error;
        }
    }
    ?>

    

