<?php
include 'db_connect.php';

$message = "";
$chapter = null;
$truyen_id = null; // Để lưu ID truyện mà chương này thuộc về

if (isset($_GET['id'])) {
    $chapter_id = $_GET['id'];

    // Lấy thông tin chương hiện tại
    $stmt = $conn->prepare("SELECT c.id, c.truyen_id, c.so_chuong, c.tieu_de, c.noi_dung, t.ten_truyen
                            FROM chuong c
                            JOIN truyen t ON c.truyen_id = t.id
                            WHERE c.id = ?");
    $stmt->bind_param("i", $chapter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $chapter = $result->fetch_assoc();
        $truyen_id = $chapter['truyen_id']; // Lưu ID truyện
    } else {
        $message = "Không tìm thấy chương.";
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_chapter'])) {
    $chapter_id = $_POST['chapter_id'];
    $truyen_id_post = $_POST['truyen_id']; // Lấy lại ID truyện
    $so_chuong = $_POST['so_chuong'];
    $tieu_de = $_POST['tieu_de'];
    $noi_dung = $_POST['noi_dung'];

    // Bắt đầu giao dịch
    $conn->begin_transaction();
    $success = true;

    // Kiểm tra số chương mới có bị trùng với chương khác trong cùng truyện không
    $check_stmt = $conn->prepare("SELECT id FROM chuong WHERE truyen_id = ? AND so_chuong = ? AND id != ?");
    $check_stmt->bind_param("iii", $truyen_id_post, $so_chuong, $chapter_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = "Lỗi: Số chương này đã tồn tại cho truyện này. Vui lòng chọn số chương khác.";
        $success = false; // Đánh dấu là có lỗi
    } else {
        // Cập nhật thông tin chương
        $stmt_update = $conn->prepare("UPDATE chuong SET so_chuong = ?, tieu_de = ?, noi_dung = ? WHERE id = ?");
        $stmt_update->bind_param("issi", $so_chuong, $tieu_de, $noi_dung, $chapter_id);

        if (!$stmt_update->execute()) {
            $message = "Lỗi khi cập nhật chương: " . $stmt_update->error;
            $success = false;
        }
        $stmt_update->close();
    }
    $check_stmt->close();

    // Nếu không có lỗi nào từ việc kiểm tra số chương và cập nhật chương
    if ($success) {
        // Sau khi sửa chương thành công, kiểm tra và cập nhật trạng thái truyện
        // Lấy số chương cao nhất của truyện này
        $stmt_max_chuong = $conn->prepare("SELECT MAX(so_chuong) AS max_so_chuong FROM chuong WHERE truyen_id = ?");
        $stmt_max_chuong->bind_param("i", $truyen_id_post);
        $stmt_max_chuong->execute();
        $result_max_chuong = $stmt_max_chuong->get_result();
        $row_max_chuong = $result_max_chuong->fetch_assoc();
        $max_so_chuong = $row_max_chuong['max_so_chuong'];
        $stmt_max_chuong->close();

        // Lấy tiêu đề chương cuối cùng
        $stmt_last_chuong_tieu_de = $conn->prepare("SELECT tieu_de FROM chuong WHERE truyen_id = ? AND so_chuong = ?");
        $stmt_last_chuong_tieu_de->bind_param("ii", $truyen_id_post, $max_so_chuong);
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
        $stmt_update_truyen_status->bind_param("si", $trang_thai_moi, $truyen_id_post);
        if (!$stmt_update_truyen_status->execute()) {
            $message .= " Lỗi khi cập nhật trạng thái truyện: " . $stmt_update_truyen_status->error;
            $success = false;
        }
        $stmt_update_truyen_status->close();
    }


    if ($success) {
        $conn->commit(); // Cam kết giao dịch nếu tất cả thành công
        $message = "Chương đã được cập nhật thành công và trạng thái truyện đã được cập nhật!";
        // Cập nhật lại dữ liệu hiển thị sau khi sửa
        $chapter['so_chuong'] = $so_chuong;
        $chapter['tieu_de'] = $tieu_de;
        $chapter['noi_dung'] = $noi_dung;
    } else {
        $conn->rollback(); // Hoàn tác giao dịch nếu có lỗi
        $message = "Có lỗi xảy ra: " . $message; // Hiển thị lỗi tổng hợp
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $chapter ? 'Sửa Chương ' . htmlspecialchars($chapter['so_chuong']) . ': ' . htmlspecialchars($chapter['tieu_de']) : 'Sửa Chương'; ?> - Truyện Online</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Sửa Chương</h1>
        <?php if ($truyen_id !== null): ?>
            <p><a href="chi_tiet_truyen.php?id=<?php echo htmlspecialchars($truyen_id); ?>" class="button">Về trang chi tiết truyện <?php echo $chapter ? 'của "' . htmlspecialchars($chapter['ten_truyen']) . '"' : ''; ?></a></p>
        <?php else: ?>
            <p><a href="index.php" class="button">Về trang chủ</a></p>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <?php if ($chapter): ?>
            <form action="sua_chuong.php?id=<?php echo htmlspecialchars($chapter['id']); ?>" method="post">
                <input type="hidden" name="chapter_id" value="<?php echo htmlspecialchars($chapter['id']); ?>">
                <input type="hidden" name="truyen_id" value="<?php echo htmlspecialchars($chapter['truyen_id']); ?>">
                <input type="hidden" name="update_chapter" value="1">
                <div class="form-group">
                    <label for="so_chuong">Số chương:</label>
                    <input type="number" id="so_chuong" name="so_chuong" value="<?php echo htmlspecialchars($chapter['so_chuong']); ?>" min="1" required>
                </div>
                <div class="form-group">
                    <label for="tieu_de">Tiêu đề chương:</label>
                    <input type="text" id="tieu_de" name="tieu_de" value="<?php echo htmlspecialchars($chapter['tieu_de']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="noi_dung">Nội dung chương:</label>
                    <textarea id="noi_dung" name="noi_dung" rows="15" required><?php echo htmlspecialchars($chapter['noi_dung']); ?></textarea>
                </div>
                <button type="submit" class="button">Cập nhật chương</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>