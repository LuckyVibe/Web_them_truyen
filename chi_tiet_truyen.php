<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($truyen['ten_truyen']); ?> - Truyện Online</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS cho bố cục trang chi tiết */
        .truyen-detail {
            display: flex;
            gap: 20px; /* Khoảng cách giữa ảnh bìa và thông tin */
            margin-bottom: 20px;
            align-items: flex-start; /* Căn trên cùng */
        }
        .detail-anh-bia {
            width: 200px; /* Kích thước cố định cho ảnh bìa */
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .detail-info {
            flex-grow: 1;
        }
        .detail-info h1 {
            margin-top: 0;
            font-size: 2em;
            color: #333;
        }
        .detail-info p {
            margin-bottom: 10px;
        }
        .detail-info .button {
            margin-bottom: 15px; /* Để nút không dính vào tiêu đề */
        }
        .chuong-list ul {
            list-style: none;
            padding: 0;
        }
        .chuong-list li {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chuong-list li a {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }
        .chuong-list li a:hover {
            text-decoration: underline;
        }
        .chuong-list .button {
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        include 'db_connect.php'; // Kết nối đến cơ sở dữ liệu

        $truyen = null;
        if (isset($_GET['id'])) {
            $truyen_id = $_GET['id'];

            // Lấy thông tin truyện
            $stmt = $conn->prepare("SELECT t.id, t.ten_truyen, t.ten_tac_gia, t.anh_bia, t.mo_ta, GROUP_CONCAT(tl.ten_the_loai SEPARATOR ', ') AS the_loai
                                    FROM truyen t
                                    LEFT JOIN truyen_the_loai ttl ON t.id = ttl.truyen_id
                                    LEFT JOIN the_loai tl ON ttl.the_loai_id = tl.id
                                    WHERE t.id = ?
                                    GROUP BY t.id");
            $stmt->bind_param("i", $truyen_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $truyen = $result->fetch_assoc();
            }
            $stmt->close();
        }

        if (!$truyen) {
            echo "<p class='error-message'>Không tìm thấy truyện.</p>";
            echo '<p><a href="index.php" class="button">Về trang chủ</a></p>';
        } else {
        ?>
            <p><a href="index.php" class="button">Về trang chủ</a></p>

            <div class="truyen-detail">
                <img src="<?php echo htmlspecialchars($truyen['anh_bia']); ?>" alt="<?php echo htmlspecialchars($truyen['ten_truyen']); ?>" class="detail-anh-bia">
                <div class="detail-info">
                    <p><a href="sua_truyen.php?id=<?php echo htmlspecialchars($truyen['id']); ?>" class="button small-button edit-story-button">Sửa thông tin truyện</a></p>
                    <h1><?php echo htmlspecialchars($truyen['ten_truyen']); ?></h1>
                    <p><strong>Tác giả:</strong> <?php echo htmlspecialchars($truyen['ten_tac_gia']); ?></p>
                    <p><strong>Thể loại:</strong> <?php echo htmlspecialchars($truyen['the_loai'] ? $truyen['the_loai'] : 'Chưa có thể loại'); ?></p>
                    <p><strong>Mô tả:</strong> <?php echo nl2br(htmlspecialchars($truyen['mo_ta'])); ?></p>
                </div>
            </div>

            <h2>Danh sách chương</h2>
            <div class="chuong-list">
                <?php
                // Lấy danh sách chương của truyện
                $stmt_chuong = $conn->prepare("SELECT id, so_chuong, tieu_de FROM chuong WHERE truyen_id = ? ORDER BY so_chuong ASC");
                $stmt_chuong->bind_param("i", $truyen_id);
                $stmt_chuong->execute();
                $result_chuong = $stmt_chuong->get_result();

                if ($result_chuong->num_rows > 0) {
                    echo '<ul>';
                    while ($chuong = $result_chuong->fetch_assoc()) {
                        echo '<li>';
                        echo '<a href="doc_chuong.php?id=' . htmlspecialchars($chuong['id']) . '">Chương ' . htmlspecialchars($chuong['so_chuong']) . ': ' . htmlspecialchars($chuong['tieu_de']) . '</a>';
                        echo '<a href="sua_chuong.php?id=' . htmlspecialchars($chuong['id']) . '" class="button small-button">Sửa</a>';
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>Chưa có chương nào cho truyện này.</p>';
                }
                $stmt_chuong->close();
                ?>
            </div>

            <h3>Thêm chương mới</h3>
            <form action="them_chuong.php" method="post">
                <input type="hidden" name="truyen_id" value="<?php echo htmlspecialchars($truyen['id']); ?>">
                <div class="form-group">
                    <label for="so_chuong">Số chương:</label>
                    <input type="number" id="so_chuong" name="so_chuong" required min="1">
                </div>
                <div class="form-group">
                    <label for="tieu_de">Tiêu đề chương:</label>
                    <input type="text" id="tieu_de" name="tieu_de" required>
                </div>
                <div class="form-group">
                    <label for="noi_dung">Nội dung chương:</label>
                    <textarea id="noi_dung" name="noi_dung" rows="10" required></textarea>
                </div>
                <button type="submit" class="button">Thêm chương</button>
            </form>
        <?php
        }
        $conn->close();
        ?>
    </div>
</body>
</html>