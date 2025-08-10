<?php
$servername = "sql201.infinityfree.com"; // Tên máy chủ "localhost"
$username = "if0_39405731"; // Tên người dùng MySQL mặc định của XAMPP "root"
$password = "HiHikc012"; // Mật khẩu MySQL mặc định của XAMPP (trống) ""
$dbname = "if0_39405731_my_truyen"; // Tên cơ sở dữ liệu bạn đã tạo "truyen online"

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Đặt bộ ký tự cho kết nối
$conn->set_charset("utf8mb4");

?>
