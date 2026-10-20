<?php
// =====================================================================
// "Model": chuyển 1 dòng trong bảng MySQL (tên cột snake_case)
// thành object JSON mà frontend đang dùng (tên khóa camelCase).
// Nhờ lớp này, js/app.js và js/admin.js KHÔNG phải sửa gì.
// =====================================================================

function car_out($r) {
    return [
        "id"       => (int)$r["id"],
        "name"     => $r["name"],
        "brand"    => $r["brand"],
        "type"     => $r["type"],
        "seats"    => (int)$r["seats"],
        "price"    => (int)$r["price"],
        "location" => $r["location"],
        "status"   => $r["status"],
        "rating"   => (float)$r["rating"],
        "featured" => (bool)$r["featured"],
        "image"    => $r["image"],
        "color"    => $r["color"],
    ];
}

function booking_out($r) {
    $out = [
        "id"            => (int)$r["id"],
        "userId"        => null,
        "customerName"  => $r["customer_name"],
        "customerEmail" => $r["customer_email"],
        "customerPhone" => $r["customer_phone"],
        "carId"         => $r["car_id"] === null ? null : (int)$r["car_id"],
        "carName"       => $r["car_name"],
        "startDate"     => $r["start_date"],
        "endDate"       => $r["end_date"],
        "location"      => $r["location"],
        "total"         => (int)$r["total"],
        "status"        => $r["status"],
        "createdAt"     => fmt_iso($r["created_at"]),
    ];
    // Hai trường này chỉ có sau khi đơn được duyệt (giống bản cũ)
    if ($r["payment_method"] !== null) $out["paymentMethod"] = $r["payment_method"];
    if ($r["approved_at"] !== null) $out["approvedAt"] = fmt_iso($r["approved_at"]);
    return $out;
}

// Câu SELECT giao dịch: tên khách và tên xe lấy từ bảng bookings bằng JOIN
const PAYMENT_SELECT = "SELECT p.*, b.customer_name, b.car_name
                        FROM payments p
                        JOIN bookings b ON b.id = p.booking_id";

function payment_out($r) {
    $out = [
        "id"           => (int)$r["id"],
        "txnCode"      => $r["txn_code"],
        "bookingId"    => (int)$r["booking_id"],
        "customerName" => $r["customer_name"],
        "carName"      => $r["car_name"],
        "amount"       => (int)$r["amount"],
        "method"       => $r["method"] ?? "",
        "status"       => $r["status"],
        "createdAt"    => fmt_minute($r["created_at"]),
        "paidAt"       => fmt_minute($r["paid_at"]),
        "note"         => $r["note"],
        "approvedBy"   => $r["approved_by"],
    ];
    if ($r["refunded_at"] !== null) $out["refundedAt"] = fmt_minute($r["refunded_at"]);
    if ((int)$r["amount_edited"] === 1) $out["amountEdited"] = true;
    if ($r["updated_at"] !== null) {
        $out["updatedAt"] = fmt_minute($r["updated_at"]);
        $out["updatedBy"] = (string)$r["updated_by"];
    }
    return $out;
}

function payment_find($id) {
    return db_one(PAYMENT_SELECT . " WHERE p.id = ?", [$id]);
}

// Giao dịch hiển thị cho admin: của đơn đã duyệt, hoặc đơn đã hủy nhưng đã hoàn tiền
// (bản JSON cũ làm việc này bằng vòng lặp PHP trong payments_visible())
function payments_visible() {
    $rows = db_all(PAYMENT_SELECT . "
        WHERE b.status = 'confirmed'
           OR (b.status = 'cancelled' AND p.status = 'refunded')
        ORDER BY p.created_at DESC, p.id DESC");
    return array_map("payment_out", $rows);
}

function maintenance_out($r) {
    return [
        "id"        => (int)$r["id"],
        "carId"     => $r["car_id"] === null ? null : (int)$r["car_id"],
        "carName"   => $r["car_name"],
        "type"      => $r["type"],
        "cost"      => (int)$r["cost"],
        "startDate" => $r["start_date"],
        "endDate"   => $r["end_date"],
        "status"    => $r["status"],
        "note"      => $r["note"],
    ];
}

function customer_out($r) {
    $out = [
        "id"            => (int)$r["id"],
        "name"          => $r["name"],
        "email"         => $r["email"],
        "phone"         => $r["phone"],
        "joinedAt"      => $r["joined_at"],
        "totalBookings" => (int)$r["total_bookings"],
        "totalSpent"    => (int)$r["total_spent"],
        "tier"          => customer_tier($r["total_spent"]),   // tính, không lưu
    ];
    if (isset($r["rank_no"])) $out["rank"] = (int)$r["rank_no"];
    return $out;
}

// Danh sách khách kèm thứ hạng (#1 = chi tiêu nhiều nhất).
// ROW_NUMBER() là "window function": đánh số thứ tự ngay trong SQL thay vì usort() trong PHP.
function customers_ranked() {
    $rows = db_all("
        SELECT c.*, ROW_NUMBER() OVER (ORDER BY total_spent DESC, joined_at ASC, id ASC) AS rank_no
        FROM customers c
        ORDER BY rank_no");
    return array_map("customer_out", $rows);
}

// Tìm khách theo email, nếu không có thì theo số điện thoại (bỏ khoảng trắng/dấu khi so sánh).
// Cột email dùng collation utf8mb4_unicode_ci -> so sánh KHÔNG phân biệt hoa thường.
function customer_find($email, $phone) {
    $email = trim((string)$email);
    if ($email !== "") {
        $row = db_one("SELECT * FROM customers WHERE email = ? ORDER BY id LIMIT 1", [$email]);
        if ($row) return $row;
    }
    $phone = phone_norm($phone);
    if ($phone !== "") {
        return db_one("SELECT * FROM customers
                       WHERE REGEXP_REPLACE(phone, '[^0-9]', '') = ?
                       ORDER BY id LIMIT 1", [$phone]);
    }
    return null;
}

function admin_out($r, $withCreated = true) {
    $out = [
        "id"       => (int)$r["id"],
        "username" => $r["username"],
        "name"     => $r["name"],
        "email"    => $r["email"] ?? "",
    ];
    if ($withCreated) $out["createdAt"] = fmt_iso($r["created_at"]);
    return $out;
}
