<?php
include 'db_connect.php'; // Đảm bảo db_connect.php được include đúng cách

$message = "";
$truyen = null;
$selected_the_loai_ids = []; // Mảng chứa ID thể loại đã chọn của truyện hiện tại

// Lấy danh sách các thể loại để hiển thị checkbox
$the_loai_list = [];
$sql_the_loai = "SELECT id, ten_the_loai FROM the_loai ORDER BY ten_the_loai ASC";
$result_the_loai = $conn->query($sql_the_loai);
if ($result_the_loai->num_rows > 0) {
    while($row = $result_the_loai->fetch_assoc()) {
        $the_loai_list[] = $row;
    }
}

// Xử lý khi form được gửi đi (phương thức POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_truyen'])) {
    $truyen_id = $_POST['truyen_id'];
    $ten_truyen = $_POST['ten_truyen'];
    $ten_tac_gia = $_POST['ten_tac_gia'];
    $mo_ta = $_POST['mo_ta'];
    $current_anh_bia_path = $_POST['current_anh_bia']; // Đường dẫn ảnh bìa HIỆN TẠI từ input hidden
    $new_selected_the_loai_ids = isset($_POST['the_loai_ids']) ? $_POST['the_loai_ids'] : [];

    $anh_bia_to_save = $current_anh_bia_path; // Mặc định giữ đường dẫn ảnh bìa cũ

    // === Xử lý tải ảnh bìa MỚI (nếu có file được chọn) ===
    if (isset($_FILES['anh_bia']) && $_FILES['anh_bia']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        // Đảm bảo thư mục uploads tồn tại
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = $_FILES['anh_bia']['name'];
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_unique_file_name = uniqid() . "." . $file_extension; // Tạo tên file duy nhất
        $target_file = $target_dir . $new_unique_file_name;
        $imageFileType = strtolower($file_extension);

        // Kiểm tra loại file và kích thước
        $allowed_types = array("jpg", "png", "jpeg", "gif");
        $file_size_limit = 5000000; // 5MB

        $is_valid_image = getimagesize($_FILES['anh_bia']['tmp_name']);

        if ($is_valid_image !== false) { // Đảm bảo đây thực sự là một hình ảnh
            if ($_FILES['anh_bia']['size'] < $file_size_limit) { // Kiểm tra kích thước
                if (in_array($imageFileType, $allowed_types)) { // Kiểm tra định dạng
                    // Mọi thứ OK, di chuyển file tải lên
                    if (move_uploaded_file($_FILES['anh_bia']['tmp_name'], $target_file)) {
                        $anh_bia_to_save = $target_file; // Cập nhật đường dẫn ảnh mới

                        // Tùy chọn: Xóa ảnh bìa cũ nếu có và không phải là ảnh mặc định/trống
                        // Chỉ xóa nếu ảnh cũ không phải là ảnh mặc định và tồn tại trên server
                        if (!empty($current_anh_bia_path) && file_exists($current_anh_bia_path) && $current_anh_bia_path !== 'duong/dan/anh/mac_dinh.jpg') { // Thay 'duong/dan/anh/mac_dinh.jpg' bằng đường dẫn ảnh mặc định nếu có
                            unlink($current_anh_bia_path);
                        }
                        $message .= "Ảnh bìa đã được cập nhật thành công. ";
                    } else {
                        $message .= "Có lỗi khi tải lên ảnh bìa mới. Vui lòng thử lại. ";
                        // Giữ lại ảnh cũ nếu có lỗi di chuyển file mới
                        $anh_bia_to_save = $current_anh_bia_path;
                    }
                } else {
                    $message .= "Chỉ cho phép tải lên file JPG, JPEG, PNG & GIF. ";
                    // Giữ lại ảnh cũ nếu sai định dạng
                    $anh_bia_to_save = $current_anh_bia_path;
                }
            } else {
                $message .= "Kích thước ảnh bìa mới quá lớn (tối đa 5MB). ";
                // Giữ lại ảnh cũ nếu quá kích thước
                $anh_bia_to_save = $current_anh_bia_path;
            }
        } else {
            $message .= "File tải lên không phải là ảnh hợp lệ. ";
            // Giữ lại ảnh cũ nếu không phải ảnh
            $anh_bia_to_save = $current_anh_bia_path;
        }
    }
    // === Kết thúc xử lý ảnh bìa MỚI ===

    // Bắt đầu giao dịch để đảm bảo tính toàn vẹn dữ liệu
    $conn->begin_transaction();
    $success = true;

    // Cập nhật thông tin truyện trong bảng truyen
    // Sửa lại bind_param: ssssi (string, string, string, string, integer) cho đủ 5 tham số
    $stmt_update_truyen = $conn->prepare("UPDATE truyen SET ten_truyen = ?, ten_tac_gia = ?, anh_bia = ?, mo_ta = ? WHERE id = ?");
    $stmt_update_truyen->bind_param("ssssi", $ten_truyen, $ten_tac_gia, $anh_bia_to_save, $mo_ta, $truyen_id);

    if ($stmt_update_truyen->execute()) {
        // Cập nhật thể loại: Xóa tất cả các thể loại cũ và thêm lại các thể loại mới
        $stmt_delete_tl = $conn->prepare("DELETE FROM truyen_the_loai WHERE truyen_id = ?");
        $stmt_delete_tl->bind_param("i", $truyen_id);
        if (!$stmt_delete_tl->execute()) {
            $message .= "Lỗi khi xóa thể loại cũ: " . $stmt_delete_tl->error;
            $success = false;
        }
        $stmt_delete_tl->close();

        if ($success && !empty($new_selected_the_loai_ids)) {
            $stmt_insert_tl = $conn->prepare("INSERT INTO truyen_the_loai (truyen_id, the_loai_id) VALUES (?, ?)");
            foreach ($new_selected_the_loai_ids as $tl_id) {
                // Đảm bảo $tl_id là số nguyên
                $tl_id = (int)$tl_id;
                $stmt_insert_tl->bind_param("ii", $truyen_id, $tl_id);
                if (!$stmt_insert_tl->execute()) {
                    $message .= "Lỗi khi thêm thể loại mới: " . $stmt_insert_tl->error;
                    $success = false;
                    break;
                }
            }
            $stmt_insert_tl->close();
        }
    } else {
        $message .= "Lỗi khi cập nhật truyện: " . $stmt_update_truyen->error;
        $success = false;
    }
    $stmt_update_truyen->close();

    if ($success) {
        $conn->commit();
        $message .= "Truyện đã được cập nhật thành công!";
        // Tải lại thông tin truyện để hiển thị trên form sau khi cập nhật
        $stmt_truyen = $conn->prepare("SELECT id, ten_truyen, ten_tac_gia, anh_bia, mo_ta FROM truyen WHERE id = ?");
        $stmt_truyen->bind_param("i", $truyen_id);
        $stmt_truyen->execute();
        $result_truyen = $stmt_truyen->get_result();
        if ($result_truyen->num_rows > 0) {
            $truyen = $result_truyen->fetch_assoc();
            // Cập nhật lại danh sách thể loại đã chọn
            $selected_the_loai_ids = [];
            $stmt_current_tl = $conn->prepare("SELECT the_loai_id FROM truyen_the_loai WHERE truyen_id = ?");
            $stmt_current_tl->bind_param("i", $truyen_id);
            $stmt_current_tl->execute();
            $result_current_tl = $stmt_current_tl->get_result();
            while ($row = $result_current_tl->fetch_assoc()) {
                $selected_the_loai_ids[] = $row['the_loai_id'];
            }
            $stmt_current_tl->close();
        }
    } else {
        $conn->rollback();
        $message .= "Có lỗi xảy ra khi cập nhật truyện. Vui lòng thử lại. ";
    }

}
// Phần này xử lý khi trang được tải lần đầu với ID truyện (GET request)
elseif (isset($_GET['id'])) {
    $truyen_id = $_GET['id'];

    // Lấy thông tin truyện hiện tại
    $stmt_truyen = $conn->prepare("SELECT id, ten_truyen, ten_tac_gia, anh_bia, mo_ta FROM truyen WHERE id = ?");
    $stmt_truyen->bind_param("i", $truyen_id);
    $stmt_truyen->execute();
    $result_truyen = $stmt_truyen->get_result();
    if ($result_truyen->num_rows > 0) {
        $truyen = $result_truyen->fetch_assoc();
    } else {
        $message = "Không tìm thấy truyện.";
    }
    $stmt_truyen->close();

    // Lấy các thể loại hiện tại của truyện
    if ($truyen) {
        $stmt_current_tl = $conn->prepare("SELECT the_loai_id FROM truyen_the_loai WHERE truyen_id = ?");
        $stmt_current_tl->bind_param("i", $truyen_id);
        $stmt_current_tl->execute();
        $result_current_tl = $stmt_current_tl->get_result();
        while ($row = $result_current_tl->fetch_assoc()) {
            $selected_the_loai_ids[] = $row['the_loai_id'];
        }
        $stmt_current_tl->close();
    }

} else {
    // Nếu không có ID được cung cấp hoặc không phải POST, thông báo lỗi
    $message = "Không có ID truyện được cung cấp để chỉnh sửa.";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $truyen ? 'Sửa Truyện: ' . htmlspecialchars($truyen['ten_truyen']) : 'Sửa Truyện'; ?> - Truyện Online</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-group.checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
        }
        .form-group.checkbox-group label {
            display: inline-flex;
            align-items: center;
            margin-right: 15px;
            font-weight: normal; /* Override default bold for checkbox labels */
        }
        .form-group.checkbox-group input[type="checkbox"] {
            margin-right: 5px;
            width: auto;
            display: inline-block;
        }
        .current-anh-bia {
            margin-top: 10px;
            max-width: 150px;
            height: auto;
            border: 1px solid #ddd;
            padding: 5px;
            display: block; /* Để ảnh nằm trên dòng riêng */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sửa Truyện</h1>
        <?php if ($truyen): ?>
            <p><a href="chi_tiet_truyen.php?id=<?php echo htmlspecialchars($truyen['id']); ?>" class="button">Về chi tiết truyện</a></p>
        <?php else: ?>
            <p><a href="index.php" class="button">Về trang chủ</a></p>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <?php if ($truyen): ?>
            <form action="sua_truyen.php?id=<?php echo htmlspecialchars($truyen['id']); ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="truyen_id" value="<?php echo htmlspecialchars($truyen['id']); ?>">
                <input type="hidden" name="update_truyen" value="1">
                <input type="hidden" name="current_anh_bia" value="<?php echo htmlspecialchars($truyen['anh_bia']); ?>">

                <div class="form-group">
                    <label for="ten_truyen">Tên truyện:</label>
                    <input type="text" id="ten_truyen" name="ten_truyen" value="<?php echo htmlspecialchars($truyen['ten_truyen']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="ten_tac_gia">Tên tác giả:</label>
                    <input type="text" id="ten_tac_gia" name="ten_tac_gia" value="<?php echo htmlspecialchars($truyen['ten_tac_gia']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Thể loại:</label>
                    <div class="form-group checkbox-group">
                        <?php if (!empty($the_loai_list)): ?>
                            <?php foreach ($the_loai_list as $tl): ?>
                                <label>
                                    <input type="checkbox" name="the_loai_ids[]" value="<?php echo htmlspecialchars($tl['id']); ?>"
                                        <?php echo in_array($tl['id'], $selected_the_loai_ids) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($tl['ten_the_loai']); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Chưa có thể loại nào được thêm. Vui lòng thêm thể loại vào cơ sở dữ liệu.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="anh_bia">Ảnh bìa hiện tại:</label>
                    <?php if (!empty($truyen['anh_bia']) && file_exists($truyen['anh_bia'])): ?>
                        <img src="<?php echo htmlspecialchars($truyen['anh_bia']); ?>" alt="Ảnh bìa hiện tại" class="current-anh-bia">
                    <?php else: ?>
                        <p>Không có ảnh bìa.</p>
                    <?php endif; ?>
                    <label for="anh_bia_new" style="margin-top: 10px;">Thay đổi ảnh bìa (để trống nếu không đổi):</label>
                    <input type="file" id="anh_bia_new" name="anh_bia" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="mo_ta">Mô tả truyện:</label>
                    <textarea id="mo_ta" name="mo_ta" rows="5"><?php echo htmlspecialchars($truyen['mo_ta']); ?></textarea>
                </div>
                <button type="submit" class="button">Cập nhật truyện</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>