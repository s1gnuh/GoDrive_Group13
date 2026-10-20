<?php
// =====================================================================
// Kết nối MySQL bằng PDO + các hàm truy vấn dùng chung.
// Thay cho read_json() / write_json() / acquire_write_lock() của bản cũ.
// =====================================================================

// Trả về 1 kết nối PDO duy nhất cho cả request (tạo ở lần gọi đầu tiên)
function db() {
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = require __DIR__ . "/_config.php";
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $cfg["username"], $cfg["password"], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // lỗi SQL -> ném exception, không im lặng
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // mỗi dòng là mảng ["cột" => giá trị]
            PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statement thật ở phía MySQL
            PDO::ATTR_STRINGIFY_FETCHES  => false,                  // số trả về là số, không phải chuỗi
        ]);
    } catch (PDOException $e) {
        error_log("GoDrive DB connect: " . $e->getMessage());
        fail("Không kết nối được database. Kiểm tra MySQL đã bật và api/_config.php", 500);
    }
    // Lưu và đọc giờ theo giờ Việt Nam, khớp với date_default_timezone_set ở _helpers.php
    $pdo->exec("SET time_zone = '+07:00'");
    return $pdo;
}

// Chạy câu SQL có tham số (dấu ?). Giá trị KHÔNG BAO GIỜ được nối thẳng vào chuỗi SQL
// -> chống SQL injection.
function db_query($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values($params));
    return $stmt;
}

// Lấy tất cả dòng
function db_all($sql, $params = []) {
    return db_query($sql, $params)->fetchAll();
}

// Lấy 1 dòng (hoặc null)
function db_one($sql, $params = []) {
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

// Lấy 1 giá trị (ô đầu tiên của dòng đầu tiên)
function db_value($sql, $params = []) {
    return db_query($sql, $params)->fetchColumn();
}

// INSERT/UPDATE/DELETE: trả về số dòng bị ảnh hưởng
function db_exec($sql, $params = []) {
    return db_query($sql, $params)->rowCount();
}

// INSERT rồi trả về id tự tăng vừa tạo
function db_insert($sql, $params = []) {
    db_query($sql, $params);
    return (int)db()->lastInsertId();
}

// Transaction: chạy $fn, mọi câu lệnh bên trong hoặc thành công HẾT, hoặc hủy HẾT.
// Nếu $fn gọi fail() (exit) hoặc ném lỗi giữa chừng, PDO tự ROLLBACK khi script kết thúc,
// nên dữ liệu không bao giờ bị ghi dở dang.
// Lưu ý: KHÔNG gọi respond() bên trong $fn — hãy return kết quả rồi respond() sau khi commit.
function db_tx($fn) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// Tạo chuỗi "?, ?, ?" cho câu IN (...)
function db_placeholders($count) {
    return implode(", ", array_fill(0, $count, "?"));
}

// Lỗi SQL không lường trước: ghi log chi tiết ở server, chỉ báo chung chung cho trình duyệt
// (không để lộ cấu trúc bảng / câu SQL ra ngoài).
set_exception_handler(function ($e) {
    error_log("GoDrive error: " . $e->getMessage());
    if ($e instanceof PDOException && $e->getCode() === "23000") {
        fail("Dữ liệu bị trùng hoặc vi phạm ràng buộc", 409);
    }
    fail("Lỗi máy chủ", 500);
});

// ---------------------------------------------------------------------
// Định dạng ngày giờ trả về cho frontend (giữ nguyên như bản JSON cũ)
// ---------------------------------------------------------------------
function fmt_iso($dt) {           // "2026-10-17T19:59:49+07:00"
    return $dt ? date("c", strtotime($dt)) : "";
}
function fmt_minute($dt) {        // "2026-10-17 19:59"
    return $dt ? date("Y-m-d H:i", strtotime($dt)) : "";
}
