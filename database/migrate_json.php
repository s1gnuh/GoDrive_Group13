<?php
// =====================================================================
// Chuyển dữ liệu từ data/*.json (bản cũ) vào MySQL.
// Chạy bằng dòng lệnh, SAU KHI đã import database/schema.sql:
//     D:\xampp\php\php.exe database\migrate_json.php
// Chạy lại nhiều lần được: mỗi lần sẽ xóa dữ liệu trong bảng rồi nạp lại từ file JSON.
// =====================================================================

if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Chỉ chạy bằng dòng lệnh.\n");
}
date_default_timezone_set("Asia/Ho_Chi_Minh");

$root = dirname(__DIR__);
$cfg = require $root . "/api/_config.php";

try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4",
        $cfg["username"], $cfg["password"],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    exit("Không kết nối được MySQL: " . $e->getMessage() . "\n"
       . "-> Bật MySQL trong XAMPP và import database/schema.sql trước.\n");
}
$pdo->exec("SET time_zone = '+07:00'");

function load($name) {
    global $root;
    $path = "$root/data/$name.json";
    if (!is_file($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) exit("File $name.json bị lỗi định dạng JSON\n");
    return $data;
}

// Chuỗi ngày giờ bất kỳ (ISO có Z, có +07:00, hoặc "Y-m-d H:i") -> "Y-m-d H:i:s" giờ Việt Nam
function dt($value) {
    $value = trim((string)$value);
    if ($value === "") return null;
    $ts = strtotime($value);
    return $ts === false ? null : date("Y-m-d H:i:s", $ts);
}

function insert($pdo, $table, $row) {
    $cols = array_keys($row);
    $sql = "INSERT INTO $table (" . implode(", ", $cols) . ") VALUES ("
         . implode(", ", array_fill(0, count($cols), "?")) . ")";
    $pdo->prepare($sql)->execute(array_values($row));
}

$cars = load("cars");
$customers = load("customers");
$bookings = load("bookings");
$payments = load("payments");
$maintenance = load("maintenance");
$admins = load("admins");

$pdo->beginTransaction();
try {
    // Xóa dữ liệu cũ: bảng con trước, bảng cha sau (vì khóa ngoại)
    foreach (["payments", "maintenance", "bookings", "customers", "cars", "admins"] as $t) {
        $pdo->exec("DELETE FROM $t");
    }

    foreach ($admins as $a) {
        insert($pdo, "admins", [
            "id" => (int)$a["id"],
            "username" => $a["username"],
            "name" => $a["name"] ?? "",
            "email" => !empty($a["email"]) ? $a["email"] : null,
            "password_hash" => $a["passwordHash"],
            "created_at" => dt($a["createdAt"] ?? "") ?? date("Y-m-d H:i:s"),
        ]);
    }

    $carIds = [];
    foreach ($cars as $c) {
        insert($pdo, "cars", [
            "id" => (int)$c["id"],
            "name" => $c["name"],
            "brand" => $c["brand"] ?? "",
            "type" => $c["type"] ?? "Sedan",
            "seats" => (int)($c["seats"] ?? 5),
            "price" => (int)$c["price"],
            "location" => $c["location"] ?? "",
            "status" => $c["status"] ?? "available",
            "rating" => (float)($c["rating"] ?? 4.8),
            "featured" => !empty($c["featured"]) ? 1 : 0,
            "image" => $c["image"] ?? "",
            "color" => $c["color"] ?? "",
        ]);
        $carIds[(int)$c["id"]] = true;
    }

    foreach ($customers as $c) {
        insert($pdo, "customers", [
            "id" => (int)$c["id"],
            "name" => $c["name"],
            "email" => $c["email"] ?? "",
            "phone" => $c["phone"] ?? "",
            "joined_at" => $c["joinedAt"] ?? date("Y-m-d"),
            "total_bookings" => (int)($c["totalBookings"] ?? 0),
            "total_spent" => max(0, (int)($c["totalSpent"] ?? 0)),
        ]);
    }

    $bookingStatus = [];
    foreach ($bookings as $b) {
        $carId = (int)$b["carId"];
        insert($pdo, "bookings", [
            "id" => (int)$b["id"],
            "customer_name" => $b["customerName"],
            "customer_email" => $b["customerEmail"],
            "customer_phone" => $b["customerPhone"],
            "car_id" => isset($carIds[$carId]) ? $carId : null,   // xe đã bị xóa -> NULL
            "car_name" => $b["carName"] ?? "",
            "start_date" => $b["startDate"],
            "end_date" => $b["endDate"],
            "location" => $b["location"] ?? "",
            "total" => (int)$b["total"],
            "status" => $b["status"],
            "payment_method" => $b["paymentMethod"] ?? null,
            "created_at" => dt($b["createdAt"] ?? "") ?? date("Y-m-d H:i:s"),
            "approved_at" => dt($b["approvedAt"] ?? ""),
        ]);
        $bookingStatus[(int)$b["id"]] = ["status" => $b["status"], "total" => (int)$b["total"], "approvedAt" => $b["approvedAt"] ?? ""];
    }

    $hasPayment = [];
    $skipped = 0;
    foreach ($payments as $p) {
        $bid = (int)($p["bookingId"] ?? 0);
        $bs = $bookingStatus[$bid] ?? null;
        // Bỏ giao dịch mồ côi (không có đơn) và giao dịch chưa thu của đơn đã hủy (bản cũ cũng tự bỏ)
        if (!$bs || ($bs["status"] === "cancelled" && ($p["status"] ?? "") === "pending")) { $skipped++; continue; }
        insert($pdo, "payments", [
            "id" => (int)$p["id"],
            "txn_code" => $p["txnCode"],
            "booking_id" => $bid,
            "amount" => (int)($p["amount"] ?? 0),
            "amount_edited" => !empty($p["amountEdited"]) ? 1 : 0,
            "method" => ($p["method"] ?? "") !== "" ? $p["method"] : null,
            "status" => $p["status"] ?? "pending",
            "created_at" => dt($p["createdAt"] ?? "") ?? date("Y-m-d H:i:s"),
            "paid_at" => dt($p["paidAt"] ?? ""),
            "refunded_at" => dt($p["refundedAt"] ?? ""),
            "note" => $p["note"] ?? "",
            "approved_by" => $p["approvedBy"] ?? "",
            "updated_at" => dt($p["updatedAt"] ?? ""),
            "updated_by" => $p["updatedBy"] ?? null,
        ]);
        $hasPayment[$bid] = true;
    }

    // Đơn đã duyệt nhưng chưa có giao dịch (dữ liệu cũ): tạo giao dịch "chờ thanh toán", hình thức để trống
    $created = 0;
    foreach ($bookingStatus as $bid => $bs) {
        if ($bs["status"] !== "confirmed" || isset($hasPayment[$bid])) continue;
        insert($pdo, "payments", [
            "txn_code" => "TXN-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(2))),
            "booking_id" => $bid,
            "amount" => $bs["total"],
            "status" => "pending",
            "created_at" => dt($bs["approvedAt"]) ?? date("Y-m-d H:i:s"),
        ]);
        $created++;
    }

    foreach ($maintenance as $m) {
        $carId = (int)($m["carId"] ?? 0);
        insert($pdo, "maintenance", [
            "id" => (int)$m["id"],
            "car_id" => isset($carIds[$carId]) ? $carId : null,
            "car_name" => $m["carName"] ?? "",
            "type" => $m["type"] ?? "",
            "cost" => max(0, (int)($m["cost"] ?? 0)),
            "start_date" => $m["startDate"],
            "end_date" => $m["endDate"],
            "status" => $m["status"] ?? "scheduled",
            "note" => $m["note"] ?? "",
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit("LỖI, đã hủy toàn bộ (database giữ nguyên như trước): " . $e->getMessage() . "\n");
}

echo "Đã chuyển dữ liệu vào MySQL:\n";
foreach (["admins", "cars", "customers", "bookings", "payments", "maintenance"] as $t) {
    printf("  %-12s %d dòng\n", $t, $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn());
}
if ($skipped) echo "  (bỏ qua $skipped giao dịch không còn hiệu lực)\n";
if ($created) echo "  (tạo thêm $created giao dịch chờ thanh toán cho đơn đã duyệt)\n";
