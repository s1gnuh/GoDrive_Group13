<?php
require_once __DIR__ . "/_helpers.php";

$method = method();
$action = action();

if ($method === "GET" && $action === "lookup") {
    // Tra cứu theo email đã dùng khi đặt (collation _ci -> không phân biệt hoa thường)
    $email = strtolower(clean_str($_GET["email"] ?? "", 150));
    if ($email === "") fail("Vui lòng nhập email đã dùng khi đặt");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail("Email không hợp lệ");

    $result = array_map("booking_out",
        db_all("SELECT * FROM bookings WHERE customer_email = ? ORDER BY id DESC", [$email]));

    // Gắn tình trạng thanh toán để khách biết đơn đã trả tiền hay chưa
    if ($result) {
        $ids = array_column($result, "id");
        // Mỗi đơn lấy 1 giao dịch, ưu tiên giao dịch còn hiệu lực (chờ thu / đã thu) hơn đã hoàn tiền:
        // sắp xếp (status = 'refunded') tăng dần -> giao dịch chưa hoàn đứng trước
        $rows = db_all("SELECT booking_id, method, status, amount FROM payments
                        WHERE booking_id IN (" . db_placeholders(count($ids)) . ")
                        ORDER BY (status = 'refunded'), id", $ids);
        $payOf = [];
        foreach ($rows as $p) {
            $payOf[$p["booking_id"]] = $payOf[$p["booking_id"]] ?? $p;
        }
        foreach ($result as &$r) {
            $p = $payOf[$r["id"]] ?? null;
            $st = $r["status"];
            if ($st === "pending" || ($p && $st === "cancelled" && $p["status"] !== "refunded")) $p = null;
            $r["payment"] = $p ? ["method" => $p["method"] ?? "", "status" => $p["status"], "amount" => (int)$p["amount"]] : null;
        }
        unset($r);
    }

    // Thông tin hạng thành viên: chỉ trả về khi email đã khớp ít nhất một đơn
    $member = null;
    if ($result) {
        $found = customer_find($email, "");
        if ($found) {
            $ranked = customers_ranked();
            foreach ($ranked as $c) {
                if ($c["id"] !== (int)$found["id"]) continue;
                $member = [
                    "name" => $c["name"],
                    "totalSpent" => $c["totalSpent"],
                    "totalBookings" => $c["totalBookings"],
                    "tier" => $c["tier"],
                    "rank" => $c["rank"],
                    "totalCustomers" => count($ranked)
                ];
                break;
            }
        }
    }
    respond(["bookings" => $result, "member" => $member]);
}

if ($method === "GET") {
    require_auth();
    respond(array_map("booking_out", db_all("SELECT * FROM bookings ORDER BY id DESC")));
}

if ($method === "POST" && $action === "") {
    $body = body_json();
    $customerName = preg_replace('/\s+/u', " ", clean_str($body["customerName"] ?? "", 100));
    $customerEmail = clean_str($body["customerEmail"] ?? "", 150);
    $customerPhone = clean_str($body["customerPhone"] ?? "", 20);
    $carId = (int)($body["carId"] ?? 0);
    $startDate = $body["startDate"] ?? "";
    $endDate = $body["endDate"] ?? "";
    $location = clean_str($body["location"] ?? "", 100);

    if (!$customerName || !$customerEmail || !$customerPhone || !$carId || !$startDate || !$endDate) {
        fail("Thiếu thông tin đặt xe");
    }
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) fail("Email không hợp lệ");
    if (!valid_name($customerName)) fail("Họ tên chỉ được chứa chữ cái");
    if (!valid_phone($customerPhone)) fail("Số điện thoại chỉ được gồm 9-10 chữ số");
    if (!valid_date($startDate) || !valid_date($endDate)) fail("Ngày không hợp lệ");
    if ($startDate < date("Y-m-d")) fail("Ngày nhận xe không được ở quá khứ");
    if ($endDate <= $startDate) fail("Ngày trả xe phải sau ngày nhận xe");
    if ((strtotime($endDate) - strtotime($startDate)) / 86400 > 90) fail("Thời gian thuê tối đa 90 ngày");

    $bookingId = db_tx(function () use ($customerName, $customerEmail, $customerPhone, $carId, $startDate, $endDate, $location) {
        // FOR UPDATE khóa dòng xe này tới khi transaction kết thúc:
        // 2 người cùng đặt 1 xe cùng lúc sẽ phải xếp hàng, nên không thể cùng lọt qua bước kiểm tra trùng lịch
        $car = db_one("SELECT * FROM cars WHERE id = ? FOR UPDATE", [$carId]);
        if (!$car) fail("Không tìm thấy xe", 404);
        if ($car["status"] !== "available") fail("Xe không khả dụng");

        // Hai khoảng thời gian giao nhau khi: bắt đầu mới < kết thúc cũ VÀ kết thúc mới > bắt đầu cũ
        $overlap = db_value("SELECT COUNT(*) FROM bookings
                             WHERE car_id = ? AND status IN ('pending', 'confirmed')
                               AND start_date < ? AND end_date > ?",
                             [$carId, $endDate, $startDate]);
        if ($overlap) fail("Xe đã có đơn trong khoảng thời gian này");

        $days = max(1, (int)ceil((strtotime($endDate) - strtotime($startDate)) / 86400));
        $total = $days * (int)$car["price"];

        $id = db_insert(
            "INSERT INTO bookings (customer_name, customer_email, customer_phone, car_id, car_name,
                                   start_date, end_date, location, total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$customerName, $customerEmail, $customerPhone, $carId, $car["name"],
             $startDate, $endDate, $location !== "" ? $location : $car["location"], $total]
        );

        // Đếm số đơn ngay khi đặt; tiền thuê chỉ được cộng khi admin xác nhận đơn (xem phần PATCH)
        $customer = customer_find($customerEmail, $customerPhone);
        if ($customer) {
            db_exec("UPDATE customers SET total_bookings = total_bookings + 1 WHERE id = ?", [$customer["id"]]);
        } else {
            db_insert("INSERT INTO customers (name, email, phone, joined_at, total_bookings, total_spent)
                       VALUES (?, ?, ?, CURDATE(), 1, 0)",
                      [$customerName, $customerEmail, $customerPhone]);
        }
        return $id;
    });

    respond(booking_out(db_one("SELECT * FROM bookings WHERE id = ?", [$bookingId])));
}

if ($method === "PATCH") {
    $admin = require_auth();
    $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

    if ($action === "mark-rented") {
        $booking = db_one("SELECT * FROM bookings WHERE id = ?", [$id]);
        if (!$booking) fail("Không tìm thấy đơn", 404);
        if ($booking["status"] !== "confirmed") fail("Chỉ đơn đã xác nhận mới bàn giao xe được");
        db_exec("UPDATE cars SET status = 'rented' WHERE id = ?", [$booking["car_id"]]);
        respond(["success" => true]);
    }

    $data = body_json();
    $status = $data["status"] ?? "";
    if ($status === "") fail("Thiếu trạng thái");
    require_enum($status, BOOKING_STATUSES, "Trạng thái");

    // Mọi thay đổi (đơn, giao dịch, khách hàng, xe) nằm trong 1 transaction:
    // lỗi ở bất kỳ bước nào thì tất cả quay về như cũ, không có chuyện đơn đã duyệt mà thiếu giao dịch
    [$booking, $payment] = db_tx(function () use ($id, $status, $data, $admin) {
        $booking = db_one("SELECT * FROM bookings WHERE id = ? FOR UPDATE", [$id]);
        if (!$booking) fail("Không tìm thấy đơn", 404);
        $oldStatus = $booking["status"];
        $total = (int)$booking["total"];

        // Duyệt đơn: admin bắt buộc chọn hình thức thanh toán, giao dịch được ghi nhận từ thời điểm này
        $approving = $status === "confirmed" && $oldStatus !== "confirmed";
        if ($approving) {
            $payMethod = require_enum($data["paymentMethod"] ?? "", PAYMENT_METHODS, "Hình thức thanh toán");
            $payStatus = require_enum($data["paymentStatus"] ?? "pending", ["pending", "paid"], "Trạng thái thanh toán");
            $payNote = clean_str($data["paymentNote"] ?? "", 120);
            db_exec("UPDATE bookings SET status = ?, payment_method = ?, approved_at = NOW() WHERE id = ?",
                [$status, $payMethod, $id]);
        } else {
            db_exec("UPDATE bookings SET status = ? WHERE id = ?", [$status, $id]);
        }

        $payment = null;
        if ($approving) {
            $existing = db_value("SELECT id FROM payments
                                  WHERE booking_id = ? AND status IN ('pending', 'paid')
                                  ORDER BY id LIMIT 1", [$id]);
            $paidAt = $payStatus === "paid" ? date("Y-m-d H:i:s") : null;
            $approvedBy = (string)($admin["username"] ?? "");
            if ($existing) {
                db_exec("UPDATE payments
                         SET amount = ?, amount_edited = 0, method = ?, status = ?, created_at = NOW(),
                             paid_at = ?, note = ?, approved_by = ?
                         WHERE id = ?",
                        [$total, $payMethod, $payStatus, $paidAt, $payNote, $approvedBy, $existing]);
                $paymentId = (int)$existing;
            } else {
                $paymentId = db_insert(
                    "INSERT INTO payments (txn_code, booking_id, amount, method, status, paid_at, note, approved_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [txn_code(), $id, $total, $payMethod, $payStatus, $paidAt, $payNote, $approvedBy]
                );
            }
            $payment = payment_out(payment_find($paymentId));
        } elseif ($oldStatus === "confirmed" && $status !== "confirmed") {
            // Đơn đã duyệt bị hủy: giao dịch chưa thu thì xóa, giao dịch đã thu chuyển sang hoàn tiền
            db_exec("DELETE FROM payments WHERE booking_id = ? AND status = 'pending'", [$id]);
            db_exec("UPDATE payments SET status = 'refunded', refunded_at = NOW()
                     WHERE booking_id = ? AND status = 'paid'", [$id]);
        }

        // Tổng tiền thuê của khách: cộng khi đơn được xác nhận, trừ lại nếu đơn đã xác nhận bị hủy
        $delta = 0;
        if ($oldStatus !== "confirmed" && $status === "confirmed") $delta = $total;
        if ($oldStatus === "confirmed" && $status !== "confirmed") $delta = -$total;
        if ($delta !== 0) {
            $customer = customer_find($booking["customer_email"], $booking["customer_phone"]);
            if ($customer) {
                // CAST AS SIGNED: cột UNSIGNED không được âm, nên đổi sang có dấu rồi mới cộng/trừ
                db_exec("UPDATE customers SET total_spent = GREATEST(0, CAST(total_spent AS SIGNED) + ?) WHERE id = ?",
                    [$delta, $customer["id"]]);
            }
        }

        if ($status === "cancelled" && $booking["car_id"] !== null) {
            db_exec("UPDATE cars SET status = 'available' WHERE id = ? AND status = 'rented'", [$booking["car_id"]]);
        }

        return [db_one("SELECT * FROM bookings WHERE id = ?", [$id]), $payment];
    });

    respond(array_merge(booking_out($booking), ["payment" => $payment]));
}

fail("Không hỗ trợ yêu cầu này", 405);
