<?php
include 'db_connect.php';

$chapter = null;
$message = "";

if (isset($_GET['id'])) {
    $chapter_id = $_GET['id'];

    // Lấy thông tin chương và thông tin truyện liên quan
    $stmt = $conn->prepare("SELECT c.tieu_de, c.noi_dung, c.so_chuong, t.ten_truyen, t.id as truyen_id
                            FROM chuong c
                            JOIN truyen t ON c.truyen_id = t.id
                            WHERE c.id = ?");
    $stmt->bind_param("i", $chapter_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $chapter = $result->fetch_assoc();
    } else {
        $message = "Không tìm thấy chương.";
    }
    $stmt->close();
} else {
    $message = "Không có ID chương được cung cấp.";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $chapter ? htmlspecialchars($chapter['tieu_de']) : 'Đọc chương'; ?> - Truyện Online</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php if ($chapter): ?>
            <p><a href="chi_tiet_truyen.php?id=<?php echo $chapter['truyen_id']; ?>" class="button">Quay lại truyện <?php echo htmlspecialchars($chapter['ten_truyen']); ?></a></p>
            <h1><?php echo htmlspecialchars($chapter['tieu_de']); ?></h1>
            <div class="chapter-content">
                <?php echo nl2br(htmlspecialchars($chapter['noi_dung'])); // Hiển thị nội dung, giữ định dạng xuống dòng ?>
            </div>
        <?php else: ?>
            <p class="message"><?php echo $message; ?></p>
            <p><a href="index.php" class="button">Về trang chủ</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>