<?php
include 'db_connect.php';

$message = ""; // Biến để lưu thông báo

// Lấy danh sách các thể loại từ cơ sở dữ liệu
$the_loai_list = [];
$sql_the_loai = "SELECT id, ten_the_loai FROM the_loai ORDER BY ten_the_loai ASC";
$result_the_loai = $conn->query($sql_the_loai);
if ($result_the_loai->num_rows > 0) {
    while($row = $result_the_loai->fetch_assoc()) {
        $the_loai_list[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ten_truyen = $_POST['ten_truyen'];
    $ten_tac_gia = $_POST['ten_tac_gia'];
    $mo_ta = $_POST['mo_ta'];
    $selected_the_loai_ids = isset($_POST['the_loai_ids']) ? $_POST['the_loai_ids'] : []; // Lấy mảng ID thể loại đã chọn
    $anh_bia = ""; // Mặc định là rỗng

    // Xử lý tải ảnh bìa
    if (isset($_FILES['anh_bia']) && $_FILES['anh_bia']['error'] == 0) {
        $target_dir = "uploads/"; // Thư mục lưu ảnh bìa. Cần tạo thư mục này!
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES['anh_bia']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . "." . $file_extension; // Tạo tên file duy nhất
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower($file_extension);

        // Kiểm tra loại file
        $check = getimagesize($_FILES['anh_bia']['tmp_name']);
        if ($check !== false) {
            // Kiểm tra kích thước file (ví dụ: không quá 5MB)
            if ($_FILES['anh_bia']['size'] < 5000000) {
                // Cho phép các định dạng ảnh nhất định
                if ($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg" || $imageFileType == "gif") {
                    if (move_uploaded_file($_FILES['anh_bia']['tmp_name'], $target_file)) {
                        $anh_bia = $target_file;
                        $message .= "Ảnh bìa đã được tải lên thành công. ";
                    } else {
                        $message .= "Có lỗi khi tải ảnh lên. ";
                    }
                } else {
                    $message .= "Chỉ cho phép file JPG, JPEG, PNG & GIF. ";
                }
            } else {
                $message .= "Kích thước ảnh quá lớn. ";
            }
        } else {
            $message .= "File không phải là ảnh. ";
        }
    }

    // Bắt đầu giao dịch (transaction) để đảm bảo dữ liệu nhất quán
    $conn->begin_transaction();
    $success = true;

    // Thêm truyện vào bảng truyen
    $stmt_truyen = $conn->prepare("INSERT INTO truyen (ten_truyen, ten_tac_gia, anh_bia, mo_ta) VALUES (?, ?, ?, ?)");
    $stmt_truyen->bind_param("ssss", $ten_truyen, $ten_tac_gia, $anh_bia, $mo_ta);

    if ($stmt_truyen->execute()) {
        $new_truyen_id = $conn->insert_id; // Lấy ID của truyện vừa thêm

        // Thêm các thể loại đã chọn vào bảng truyen_the_loai
        if (!empty($selected_the_loai_ids)) {
            $stmt_insert_tl = $conn->prepare("INSERT INTO truyen_the_loai (truyen_id, the_loai_id) VALUES (?, ?)");
            foreach ($selected_the_loai_ids as $tl_id) {
                $stmt_insert_tl->bind_param("ii", $new_truyen_id, $tl_id);
                if (!$stmt_insert_tl->execute()) {
                    $message .= "Lỗi khi thêm thể loại: " . $stmt_insert_tl->error;
                    $success = false;
                    break;
                }
            }
            $stmt_insert_tl->close();
        }
    } else {
        $message .= "Lỗi khi thêm truyện: " . $stmt_truyen->error;
        $success = false;
    }
    $stmt_truyen->close();

    if ($success) {
        $conn->commit(); // Cam kết giao dịch nếu tất cả thành công
        $message = "Truyện đã được thêm thành công! Bạn có thể thêm chương ngay bây giờ.";
    } else {
        $conn->rollback(); // Hoàn tác giao dịch nếu có lỗi
        $message = "Có lỗi xảy ra khi thêm truyện hoặc thể loại. Vui lòng thử lại. " . $message;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng truyện mới</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Đăng truyện mới</h1>
        <p><a href="index.php" class="button">Về trang chủ</a></p>

        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
            <?php if (isset($new_truyen_id)): ?>
                <p><a href="chi_tiet_truyen.php?id=<?php echo $new_truyen_id; ?>" class="button">Xem truyện và thêm chương</a></p>
            <?php endif; ?>
        <?php endif; ?>

        <form action="them_truyen.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="ten_truyen">Tên truyện:</label>
                <input type="text" id="ten_truyen" name="ten_truyen" required>
            </div>
            <div class="form-group">
                <label for="ten_tac_gia">Tên tác giả:</label>
                <input type="text" id="ten_tac_gia" name="ten_tac_gia" required>
            </div>
            <div class="form-group">
                <label>Thể loại:</label>
                <div class="form-group checkbox-group">
                    <?php if (!empty($the_loai_list)): ?>
                        <?php foreach ($the_loai_list as $tl): ?>
                            <label>
                                <input type="checkbox" name="the_loai_ids[]" value="<?php echo htmlspecialchars($tl['id']); ?>">
                                <?php echo htmlspecialchars($tl['ten_the_loai']); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Chưa có thể loại nào được thêm. Vui lòng thêm thể loại vào cơ sở dữ liệu.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="anh_bia">Ảnh bìa:</label>
                <input type="file" id="anh_bia" name="anh_bia" accept="image/*">
            </div>
            <div class="form-group">
                <label for="mo_ta">Mô tả truyện:</label>
                <textarea id="mo_ta" name="mo_ta" rows="5"></textarea>
            </div>
            <button type="submit" class="button">Đăng truyện</button>
        </form>
    </div>
</body>
</html>
<?php
$conn->close();
?>