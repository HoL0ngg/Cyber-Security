    <?php
        $conn = mysqli_connect("localhost", "root", "", "anm");
      if($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['user_id'])) {
        $target_id = $_GET['user_id'];
        $session_id = $_SESSION['user_id'];
        $new_name = $_POST['new_username'];
        $new_password = !empty($_POST['new_password']) ? $_POST['new_password'] : null;

        // if($target_id != $session_id){
        //     die("🚨 Hành động bị chặn: Bạn không thể sửa đổi dữ liệu của người khác!");
        // }
        $sql = 'UPDATE users set username = ?';
        $params  = [$new_name];   
        $types = "s";     

        if($new_password){
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $sql .= ' ,password = ?';
            $params[] = $hashed;
            $types .= "s";
        }

        $sql .= ' where id = ?';
        $params[] = $target_id;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if($stmt->execute()){
            header("Location: /test.php?page=bac&user_id=" . $target_id . "&success=1");
            echo "alert('Thành công')";
            exit();
        }else{
            echo "Co loi khi update " . $conn->error; 
        }

      } 
    ?>

    

