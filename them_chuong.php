<?php
include 'db_connect.php'; // Kết nối đến cơ sở dữ liệu

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu từ form
    $truyen_id = $_POST['truyen_id'];
    $so_chuong = $_POST['so_chuong'];
    $tieu_de = $_POST['tieu_de'];
    $noi_dung = $_POST['noi_dung'];

    // Kiểm tra dữ liệu đầu vào
    if (empty($truyen_id) || empty($so_chuong) || empty($tieu_de) || empty($noi_dung)) {
        echo "<script>alert('Vui lòng điền đầy đủ tất cả các trường!'); window.location.href='chi_tiet_truyen.php?id=" . htmlspecialchars($truyen_id) . "';</script>";
        exit();
    }

    // Chuyển đổi số chương sang kiểu int
    $so_chuong = (int)$so_chuong;

    // Kiểm tra xem số chương đã tồn tại cho truyện này chưa
    $stmt_check = $conn->prepare("SELECT id FROM chuong WHERE truyen_id = ? AND so_chuong = ?");
    $stmt_check->bind_param("ii", $truyen_id, $so_chuong);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // Số chương đã tồn tại
        echo "<script>alert('Số chương này đã tồn tại cho truyện này. Vui lòng chọn số chương khác!'); window.location.href='chi_tiet_truyen.php?id=" . htmlspecialchars($truyen_id) . "';</script>";
        $stmt_check->close();
        $conn->close();
        exit();
    }
    $stmt_check->close();

    // Bắt đầu giao dịch
    $conn->begin_transaction();
    $success = true;

    // Chuẩn bị câu lệnh SQL để chèn chương mới vào bảng 'chuong'
    $stmt = $conn->prepare("INSERT INTO chuong (truyen_id, so_chuong, tieu_de, noi_dung) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $truyen_id, $so_chuong, $tieu_de, $noi_dung);

    // Thực thi câu lệnh
    if ($stmt->execute()) {
        // Sau khi thêm chương thành công, kiểm tra và cập nhật trạng thái truyện
        // Lấy số chương cao nhất của truyện này
        $stmt_max_chuong = $conn->prepare("SELECT MAX(so_chuong) AS max_so_chuong FROM chuong WHERE truyen_id = ?");
        $stmt_max_chuong->bind_param("i", $truyen_id);
        $stmt_max_chuong->execute();
        $result_max_chuong = $stmt_max_chuong->get_result();
        $row_max_chuong = $result_max_chuong->fetch_assoc();
        $max_so_chuong = $row_max_chuong['max_so_chuong'];
        $stmt_max_chuong->close();

        // Lấy tiêu đề chương cuối cùng
        $stmt_last_chuong_tieu_de = $conn->prepare("SELECT tieu_de FROM chuong WHERE truyen_id = ? AND so_chuong = ?");
        $stmt_last_chuong_tieu_de->bind_param("ii", $truyen_id, $max_so_chuong);
        $stmt_last_chuong_tieu_de->execute();
        $result_last_chuong_tieu_de = $stmt_last_chuong_tieu_de->get_result();
        $last_chuong_tieu_de = "";
        if ($result_last_chuong_tieu_de->num_rows > 0) {
            $last_chuong_tieu_de = $result_last_chuong_tieu_de->fetch_assoc()['tieu_de'];
        }
        $stmt_last_chuong_tieu_de->close();

        // Xác định trạng thái mới dựa trên tiêu đề chương cuối cùng
        $trang_thai_moi = (stripos($last_chuong_tieu_de, 'HẾT') !== false) ? 'Hoàn thành' : 'Đang ra';

        // Cập nhật trạng thái vào bảng truyen
        $stmt_update_truyen_status = $conn->prepare("UPDATE truyen SET trang_thai = ? WHERE id = ?");
        $stmt_update_truyen_status->bind_param("si", $trang_thai_moi, $truyen_id);
        if (!$stmt_update_truyen_status->execute()) {
            $message = "Lỗi khi cập nhật trạng thái truyện: " . $stmt_update_truyen_status->error;
            $success = false;
        }
        $stmt_update_truyen_status->close();

        if ($success) {
            $conn->commit(); // Cam kết giao dịch
            echo "<script>alert('Chương đã được thêm thành công và trạng thái truyện đã được cập nhật!'); window.location.href='chi_tiet_truyen.php?id=" . htmlspecialchars($truyen_id) . "';</script>";
        } else {
            $conn->rollback(); // Hoàn tác nếu có lỗi
            echo "<script>alert('Lỗi: " . $message . "'); window.location.href='chi_tiet_truyen.php?id=" . htmlspecialchars($truyen_id) . "';</script>";
        }

    } else {
        $conn->rollback(); // Hoàn tác nếu không thể thêm chương
        echo "<script>alert('Lỗi: " . $stmt->error . "'); window.location.href='chi_tiet_truyen.php?id=" . htmlspecialchars($truyen_id) . "';</script>";
    }

    // Đóng câu lệnh và kết nối
    $stmt->close();
    $conn->close();
} else {
    // Nếu không phải là phương thức POST, chuyển hướng về trang chủ
    header("Location: index.php");
    exit();
}
?>