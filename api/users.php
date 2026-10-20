<?php
require_once __DIR__ . "/_helpers.php";

$method = method();
$action = action();

if ($action === "payments") {
    $admin = require_auth();
    if ($method === "GET") {
        respond(payments_visible());
    }
    if ($method === "PATCH") {
        // Admin sửa giao dịch: hình thức, trạng thái, số tiền, thời điểm thu, ghi chú
        $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
        $data = body_json();

        $paymentId = db_tx(function () use ($id, $data, $admin) {
            $p = db_one("SELECT p.*, b.total AS booking_total FROM payments p
                         JOIN bookings b ON b.id = p.booking_id
                         WHERE p.id = ? FOR UPDATE", [$id]);
            if (!$p) fail("Không tìm thấy giao dịch", 404);
            $now = date("Y-m-d H:i:s");

            if (array_key_exists("method", $data)) {
                $p["method"] = require_enum($data["method"], PAYMENT_METHODS, "Hình thức thanh toán");
            }
            if (array_key_exists("status", $data)) {
                $new = require_enum($data["status"], PAYMENT_STATUSES, "Trạng thái thanh toán");
                if ($new === "paid" && $p["status"] !== "paid" && !$p["paid_at"]) $p["paid_at"] = $now;
                if ($new === "pending") { $p["paid_at"] = null; $p["refunded_at"] = null; }
                if ($new === "refunded" && !$p["refunded_at"]) $p["refunded_at"] = $now;
                if ($new === "paid") $p["refunded_at"] = null;
                $p["status"] = $new;
            }
            if (array_key_exists("paidAt", $data) && $p["status"] !== "pending") {
                $p["paid_at"] = clean_datetime($data["paidAt"]) ?: $now;
            }
            if (!empty($data["syncAmount"])) {
                // Lấy lại số tiền theo tổng tiền của đơn
                $p["amount"] = (int)$p["booking_total"];
                $p["amount_edited"] = 0;
            } elseif (array_key_exists("amount", $data)) {
                $amount = (int)$data["amount"];
                if ($amount < 0 || $amount > 100000000000) fail("Số tiền không hợp lệ");
                if ($amount !== (int)$p["amount"]) $p["amount_edited"] = 1;
                $p["amount"] = $amount;
            }
            if (array_key_exists("note", $data)) $p["note"] = clean_str($data["note"], 120);
            if (!$p["method"] && $p["status"] !== "pending") fail("Cần chọn hình thức thanh toán trước");

            db_exec("UPDATE payments
                     SET method = ?, status = ?, paid_at = ?, refunded_at = ?, amount = ?, amount_edited = ?,
                         note = ?, updated_at = ?, updated_by = ?
                     WHERE id = ?",
                    [$p["method"], $p["status"], $p["paid_at"], $p["refunded_at"], $p["amount"],
                     $p["amount_edited"], $p["note"], $now, (string)($admin["username"] ?? ""), $id]);
            return $id;
        });
        respond(payment_out(payment_find($paymentId)));
    }
    fail("Không hỗ trợ yêu cầu này", 405);
}

if ($action === "maintenance") {
    require_auth();
    if ($method === "GET") {
        respond(array_map("maintenance_out", db_all("SELECT * FROM maintenance ORDER BY id")));
    }
    if ($method === "POST") {
        $data = body_json();
        $carId = (int)($data["carId"] ?? 0);
        $car = db_one("SELECT id, name FROM cars WHERE id = ?", [$carId]);
        if (!$car) fail("Không tìm thấy xe", 404);
        $startDate = $data["startDate"] ?? date("Y-m-d");
        $endDate = $data["endDate"] ?? date("Y-m-d");
        if (!valid_date($startDate) || !valid_date($endDate) || $endDate < $startDate) fail("Ngày bảo trì không hợp lệ");
        $cost = (int)($data["cost"] ?? 0);
        if ($cost < 0) fail("Chi phí không hợp lệ");
        $newId = db_insert(
            "INSERT INTO maintenance (car_id, car_name, type, cost, start_date, end_date, status, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$carId, $car["name"], clean_str($data["type"] ?? "", 100), $cost, $startDate, $endDate,
             require_enum($data["status"] ?? "scheduled", MAINT_STATUSES, "Trạng thái"),
             clean_str($data["note"] ?? "", 500)]
        );
        respond(maintenance_out(db_one("SELECT * FROM maintenance WHERE id = ?", [$newId])));
    }
    if ($method === "PATCH") {
        $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
        $data = body_json();
        $update = [];   // [tên cột => giá trị mới]
        if (array_key_exists("type", $data)) $update["type"] = clean_str($data["type"], 100);
        if (array_key_exists("note", $data)) $update["note"] = clean_str($data["note"], 500);
        if (array_key_exists("cost", $data)) {
            $update["cost"] = (int)$data["cost"];
            if ($update["cost"] < 0) fail("Chi phí không hợp lệ");
        }
        foreach (["startDate" => "start_date", "endDate" => "end_date"] as $key => $col) {
            if (array_key_exists($key, $data)) {
                if (!valid_date($data[$key])) fail("Ngày bảo trì không hợp lệ");
                $update[$col] = $data[$key];
            }
        }
        if (array_key_exists("status", $data)) {
            $update["status"] = require_enum($data["status"], MAINT_STATUSES, "Trạng thái");
        }

        $row = db_tx(function () use ($id, $update) {
            $item = db_one("SELECT * FROM maintenance WHERE id = ? FOR UPDATE", [$id]);
            if (!$item) fail("Không tìm thấy", 404);
            if ($update) {
                $sets = array_map(fn($c) => "$c = ?", array_keys($update));
                db_exec("UPDATE maintenance SET " . implode(", ", $sets) . " WHERE id = ?",
                    array_merge(array_values($update), [$id]));
            }
            // Bắt đầu bảo dưỡng -> ẩn xe khỏi trang khách; hoàn tất -> xe sẵn sàng lại
            $status = $update["status"] ?? "";
            if ($status === "in_progress" && $item["car_id"] !== null) {
                db_exec("UPDATE cars SET status = 'maintenance' WHERE id = ?", [$item["car_id"]]);
            }
            if ($status === "completed" && $item["car_id"] !== null) {
                db_exec("UPDATE cars SET status = 'available' WHERE id = ? AND status = 'maintenance'", [$item["car_id"]]);
            }
            return db_one("SELECT * FROM maintenance WHERE id = ?", [$id]);
        });
        respond(maintenance_out($row));
    }
    if ($method === "DELETE") {
        $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
        db_exec("DELETE FROM maintenance WHERE id = ?", [$id]);
        respond(["success" => true]);
    }
    fail("Không hỗ trợ yêu cầu này", 405);
}

if ($method === "GET") {
    require_auth();
    // Hạng được tính lại từ tổng tiền thuê, kèm thứ hạng theo mức chi tiêu
    respond(customers_ranked());
}

fail("Không hỗ trợ yêu cầu này", 405);
