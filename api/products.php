<?php
require_once __DIR__ . "/_helpers.php";

$method = method();
$action = action();

// Lấy và kiểm tra các trường thông tin xe từ body (chỉ nhận các trường trong whitelist).
// $partial = true khi cập nhật: chỉ validate các trường được gửi lên.
function car_fields($data, $partial) {
    $out = [];
    if (!$partial || array_key_exists("name", $data)) {
        $out["name"] = clean_str($data["name"] ?? "", 100);
        if ($out["name"] === "") fail("Tên xe không được để trống");
    }
    if (!$partial || array_key_exists("brand", $data)) {
        $out["brand"] = clean_str($data["brand"] ?? "", 50);
    }
    if (!$partial || array_key_exists("type", $data)) {
        $out["type"] = require_enum($data["type"] ?? "Sedan", CAR_TYPES, "Loại xe");
    }
    if (!$partial || array_key_exists("seats", $data)) {
        $out["seats"] = (int)($data["seats"] ?? 5);
        if ($out["seats"] < 1 || $out["seats"] > 50) fail("Số chỗ không hợp lệ");
    }
    if (!$partial || array_key_exists("price", $data)) {
        $out["price"] = (int)($data["price"] ?? 0);
        if ($out["price"] <= 0 || $out["price"] > 1000000000) fail("Giá thuê không hợp lệ");
    }
    if (!$partial || array_key_exists("location", $data)) {
        $out["location"] = clean_str($data["location"] ?? "Hà Nội", 100);
        if ($out["location"] === "") fail("Địa điểm không được để trống");
    }
    if (!$partial || array_key_exists("rating", $data)) {
        $out["rating"] = max(0, min(5, (float)($data["rating"] ?? 4.8)));
    }
    if (!$partial || array_key_exists("featured", $data)) {
        $out["featured"] = !empty($data["featured"]);
    }
    if (!$partial || array_key_exists("image", $data)) {
        $out["image"] = clean_image_url($data["image"] ?? "");
    }
    // Màu sơn của hình minh họa khi xe chưa có ảnh ("" = tự chọn theo tên xe)
    if (!$partial || array_key_exists("color", $data)) {
        $out["color"] = require_enum((string)($data["color"] ?? ""), CAR_COLORS, "Màu xe");
    }
    return $out;
}

// Cột trong bảng cars tương ứng với các khóa JSON frontend gửi lên
const CAR_COLUMNS = [
    "name" => "name", "brand" => "brand", "type" => "type", "seats" => "seats", "price" => "price",
    "location" => "location", "rating" => "rating", "featured" => "featured", "image" => "image", "color" => "color"
];

function car_find($id) {
    $row = db_one("SELECT * FROM cars WHERE id = ?", [$id]);
    if (!$row) fail("Không tìm thấy xe", 404);
    return $row;
}

if ($method === "GET") {
    // Lọc bằng WHERE trong SQL thay vì array_filter trong PHP:
    // chỉ ghép những điều kiện được gửi lên, giá trị luôn đi qua dấu ?
    $where = [];
    $params = [];
    foreach (["status", "location", "type"] as $key) {
        $value = $_GET[$key] ?? "";
        if ($value !== "") {
            $where[] = "$key = ?";       // $key lấy từ danh sách cố định ở trên, không phải từ người dùng
            $params[] = $value;
        }
    }
    $sql = "SELECT * FROM cars" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY id";
    respond(array_map("car_out", db_all($sql, $params)));
}

if ($method === "POST") {
    require_auth();
    $fields = array_merge(
        ["rating" => 4.8, "featured" => false, "image" => ""],
        car_fields(body_json(), false)
    );
    $fields["featured"] = $fields["featured"] ? 1 : 0;
    $cols = array_keys($fields);
    $id = db_insert(
        "INSERT INTO cars (" . implode(", ", $cols) . ") VALUES (" . db_placeholders(count($cols)) . ")",
        array_values($fields)
    );   // status mặc định 'available', id do MySQL tự tăng
    respond(car_out(car_find($id)));
}

if ($method === "PUT") {
    require_auth();
    $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
    car_find($id);
    $fields = car_fields(body_json(), true);   // chỉ các trường được gửi lên
    if (array_key_exists("featured", $fields)) $fields["featured"] = $fields["featured"] ? 1 : 0;
    if ($fields) {
        $sets = array_map(fn($c) => CAR_COLUMNS[$c] . " = ?", array_keys($fields));
        db_exec("UPDATE cars SET " . implode(", ", $sets) . " WHERE id = ?",
            array_merge(array_values($fields), [$id]));
    }
    respond(car_out(car_find($id)));
}

if ($method === "PATCH" && $action === "status") {
    require_auth();
    $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
    car_find($id);
    $data = body_json();
    $status = require_enum($data["status"] ?? "", CAR_STATUSES, "Trạng thái");
    db_exec("UPDATE cars SET status = ? WHERE id = ?", [$status, $id]);
    respond(car_out(car_find($id)));
}

if ($method === "DELETE") {
    require_auth();
    $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
    // Khóa ngoại ON DELETE SET NULL: đơn đặt xe / lịch bảo dưỡng cũ của xe này vẫn còn, car_id thành NULL
    db_exec("DELETE FROM cars WHERE id = ?", [$id]);
    respond(["success" => true]);
}

fail("Không hỗ trợ yêu cầu này", 405);
