<?php
include 'db_connect.php';

// Lấy danh sách các thể loại để hiển thị bộ lọc
$the_loai_filter_list = [];
$sql_filter_the_loai = "SELECT id, ten_the_loai FROM the_loai ORDER BY ten_the_loai ASC";
$result_filter_the_loai = $conn->query($sql_filter_the_loai);
if ($result_filter_the_loai->num_rows > 0) {
    while($row = $result_filter_the_loai->fetch_assoc()) {
        $the_loai_filter_list[] = $row;
    }
}

// Biến để lưu trữ ID thể loại được chọn từ bộ lọc và trạng thái truyện
$selected_filter_the_loai_id = null;
if (isset($_GET['filter_the_loai']) && $_GET['filter_the_loai'] != '') {
    $selected_filter_the_loai_id = (int)$_GET['filter_the_loai'];
}

$selected_filter_trang_thai = null;
if (isset($_GET['filter_trang_thai']) && $_GET['filter_trang_thai'] != '') {
    $selected_filter_trang_thai = $_GET['filter_trang_thai'];
}


// Xây dựng câu truy vấn SQL chính để lấy danh sách truyện
// Thêm cột trang_thai vào SELECT
$sql = "SELECT t.id, t.ten_truyen, t.ten_tac_gia, t.anh_bia, t.mo_ta, t.trang_thai,
                GROUP_CONCAT(tl.ten_the_loai ORDER BY tl.ten_the_loai ASC SEPARATOR ', ') AS the_loai_str
        FROM truyen t
        LEFT JOIN truyen_the_loai ttl ON t.id = ttl.truyen_id
        LEFT JOIN the_loai tl ON ttl.the_loai_id = tl.id";

$where_clauses = [];
$param_types = "";
$param_values = [];

if ($selected_filter_the_loai_id !== null) {
    $where_clauses[] = "t.id IN (SELECT truyen_id FROM truyen_the_loai WHERE the_loai_id = ?)";
    $param_types .= "i";
    $param_values[] = $selected_filter_the_loai_id;
}

if ($selected_filter_trang_thai !== null) {
    $where_clauses[] = "t.trang_thai = ?";
    $param_types .= "s";
    $param_values[] = $selected_filter_trang_thai;
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " GROUP BY t.id
          ORDER BY t.ngay_dang DESC";

// Chuẩn bị và thực thi câu lệnh SQL (sử dụng prepared statements nếu có lọc)
if (!empty($param_values)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$param_values);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ - Truyện Online</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    /* ... (Giữ nguyên các phần CSS phía trên của bạn như body, container, h1, a, button, message, form-group, v.v.) ... */

    /* Thêm style cho trạng thái truyện */
    .story-status {
        font-weight: bold;
        padding: 3px 8px;
        border-radius: 4px;
        color: white;
        display: inline-block;
        margin-left: 5px; /* Giảm nhẹ margin-left để gọn hơn */
        font-size: 0.85em; /* Giảm cỡ chữ để gọn hơn */
    }
    .status-dang-ra {
        background-color: #ffc107; /* Màu vàng */
    }
    .status-hoan-thanh {
        background-color: #28a745; /* Màu xanh lá cây */
    }

    /* CSS mới cho bố cục item (dùng Flexbox) */
    .truyen-list {
        display: flex; /* Kích hoạt Flexbox */
        flex-wrap: wrap; /* Cho phép các item xuống dòng */
        gap: 20px; /* Khoảng cách giữa các truyện */
        justify-content: flex-start; /* Căn trái các item */
        align-items: stretch; /* Rất quan trọng: Giúp các item có chiều cao bằng nhau */
        /* Đảm bảo không có padding thừa đẩy kích thước tổng của .truyen-list */
        padding: 0;
        margin: 0;
    }

    .truyen-item {
        border: 1px solid #ddd;
        padding: 15px; /* Giữ nguyên padding bên trong item */
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        background-color: #fff;
        /* Tính toán chiều rộng cho 3 cột với gap 20px */
        /* (Tổng chiều rộng container - 2*gap) / 3 = (1200 - 40) / 3 = 1160 / 3 = ~386.66px */
        /* width: calc((100% - 40px) / 3); */
        /* Hoặc để đơn giản và ổn định hơn, tính toán dựa trên % và điều chỉnh */
        width: calc(33.33% - 13.33px); /* 100%/3 = 33.33%, 20px * 2 / 3 = 13.33px. Điều chỉnh nếu gap thay đổi */

        display: flex; /* Kích hoạt Flexbox bên trong item */
        flex-direction: column; /* Sắp xếp nội dung theo cột */
        /*justify-content: space-between; // Cái này có thể gây ra khoảng trống lớn nếu nội dung ít, thử bỏ */
    }

    /* Đảm bảo box-sizing cho tất cả các phần tử để tính toán chính xác */
    * {
        box-sizing: border-box;
    }

    .truyen-item .truyen-cover {
        width: 100%;
        height: 200px; /* Giảm nhẹ chiều cao ảnh bìa để item gọn hơn */
        object-fit: cover;
        border-radius: 4px;
        margin-bottom: 10px;
    }
    .truyen-item h2 {
        margin-top: 0;
        margin-bottom: 8px; /* Giảm nhẹ margin */
        font-size: 1.25em; /* Giảm nhẹ cỡ chữ */
        line-height: 1.3;
    }
    .truyen-item h2 a {
        color: #333;
        text-decoration: none;
    }
    .truyen-item h2 a:hover {
        color: #007bff;
    }
    .truyen-item p {
        font-size: 0.9em; /* Giữ nguyên cỡ chữ để dễ đọc */
        color: #555;
        margin-bottom: 8px; /* Giảm nhẹ margin */
    }
    .truyen-item .truyen-mo-ta { /* Thêm class này cho p mô tả */
        flex-grow: 1; /* Cho phép mô tả chiếm không gian còn lại */
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 4; /* Giới hạn 4 dòng */
        -webkit-box-orient: vertical;
        text-overflow: ellipsis;
        margin-bottom: 10px; /* Khoảng cách với các nút */
    }
    /* Các nút "Sửa Truyện" và "Xóa Truyện" */
    .actions-row { /* Đây là class bạn đã thêm để bọc các nút */
        display: flex;
        flex-wrap: wrap; /* Cho phép các nút xuống dòng nếu cần */
        gap: 8px; /* Khoảng cách giữa các nút */
        margin-top: auto; /* Đẩy hàng nút xuống dưới cùng của truyen-item */
        padding-top: 5px; /* Khoảng cách giữa mô tả và nút */
        justify-content: flex-start; /* Căn trái các nút */
    }

    .actions-row .button {
        padding: 6px 10px; /* Kích thước nhỏ gọn cho nút */
        font-size: 0.8em; /* Cỡ chữ nhỏ cho nút */
        margin-bottom: 0; /* Đảm bảo không có margin-bottom không cần thiết */
    }

    /* ... (giữ nguyên các phần CSS phía dưới không liên quan) ... */
</style>
</head>
<body>
    <div class="container">
        <h1>Danh sách truyện</h1>
        <p><a href="them_truyen.php" class="button">Đăng truyện mới</a></p>

        <div class="filter-section">
            <form id="filter-form">
                <label for="filter_the_loai">Lọc theo thể loại:</label>
                <select id="filter_the_loai_dropdown" name="filter_the_loai">
                    <option value="">Tất cả thể loại</option>
                    <?php foreach ($the_loai_filter_list as $tl): ?>
                        <option value="<?php echo htmlspecialchars($tl['id']); ?>"
                            <?php echo ($selected_filter_the_loai_id == $tl['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tl['ten_the_loai']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="filter_trang_thai">Lọc theo trạng thái:</label>
                <select id="filter_trang_thai_dropdown" name="filter_trang_thai">
                    <option value="">Tất cả trạng thái</option>
                    <option value="Đang ra" <?php echo ($selected_filter_trang_thai == 'Đang ra') ? 'selected' : ''; ?>>Đang ra</option>
                    <option value="Hoàn thành" <?php echo ($selected_filter_trang_thai == 'Hoàn thành') ? 'selected' : ''; ?>>Hoàn thành</option>
                </select>
                <button type="submit">Lọc</button> </form>
        </div>

        <div class="truyen-list" id="story-list-container">
            <p>Đang tải truyện...</p>
        </div>

    </div>
<?php include 'standalone-footer/footer.html'; ?>
<script src='standalone-footer/footer.js'></script>

</body>
</html>
<?php
// Đóng statement nếu nó đã được mở
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>