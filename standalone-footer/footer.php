<?php
/*
 * File: footer.php
 * Mô tả: Xử lý yêu cầu AJAX từ JavaScript để lấy danh sách truyện.
 * Truy vấn cơ sở dữ liệu dựa trên ID thể loại được cung cấp
 * và trả về dữ liệu truyện dưới dạng JSON.
 */

// Đảm bảo chỉ trả về JSON và không có ký tự thừa nào.
header('Content-Type: application/json');

// --- Kết nối Cơ sở dữ liệu ---
// Đường dẫn tới db_connect.php (giả sử nó nằm ở thư mục gốc của dự án)
// standalone-footer/footer.php

include '../db_connect.php'; // Thay đổi đường dẫn nếu cần, ví dụ: '../../db_connect.php'

header('Content-Type: application/json'); // Luôn trả về JSON

$category_id = null;
if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
    $category_id = (int)$_GET['category_id'];
}

$trang_thai = null;
if (isset($_GET['trang_thai']) && $_GET['trang_thai'] !== '') {
    $trang_thai = $_GET['trang_thai'];
}

// Xây dựng câu truy vấn SQL chính để lấy danh sách truyện
$sql = "SELECT t.id, t.ten_truyen, t.ten_tac_gia, t.anh_bia, t.mo_ta, t.trang_thai,
                GROUP_CONCAT(tl.ten_the_loai ORDER BY tl.ten_the_loai ASC SEPARATOR ', ') AS the_loai_str
        FROM truyen t
        LEFT JOIN truyen_the_loai ttl ON t.id = ttl.truyen_id
        LEFT JOIN the_loai tl ON ttl.the_loai_id = tl.id";

$where_clauses = [];
$param_types = "";
$param_values = [];

if ($category_id !== null) {
    $where_clauses[] = "t.id IN (SELECT truyen_id FROM truyen_the_loai WHERE the_loai_id = ?)";
    $param_types .= "i";
    $param_values[] = $category_id;
}

if ($trang_thai !== null) {
    $where_clauses[] = "t.trang_thai = ?";
    $param_types .= "s";
    $param_values[] = $trang_thai;
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " GROUP BY t.id
          ORDER BY t.ngay_dang DESC";

$stories = [];

// Chuẩn bị và thực thi câu lệnh SQL (sử dụng prepared statements nếu có lọc)
if (!empty($param_values)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($param_types, ...$param_values);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $stories[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        echo json_encode(['error' => 'Database query preparation failed.']);
        $conn->close();
        exit();
    }
} else {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stories[] = $row;
        }
    } else {
        error_log("Query failed: (" . $conn->errno . ") " . $conn->error);
        echo json_encode(['error' => 'Database query failed.']);
        $conn->close();
        exit();
    }
}

$conn->close();

echo json_encode($stories); // Trả về mảng truyện dưới dạng JSON
exit();
?>