<?php
require_once __DIR__ . "/_helpers.php";

$method = method();
$action = action();

if ($method === "POST" && ($action === "" || $action === "login")) {
    $body = body_json();
    $username = clean_str($body["username"] ?? "", 150);
    $password = is_string($body["password"] ?? null) ? $body["password"] : "";
    if ($username === "" || $password === "") fail("Thiếu thông tin");

    // Đăng nhập bằng username (phân biệt hoa thường, nhờ BINARY) hoặc email (không phân biệt)
    $admin = db_one("SELECT * FROM admins WHERE BINARY username = ? LIMIT 1", [$username])
          ?? db_one("SELECT * FROM admins WHERE email = ? LIMIT 1", [$username]);
    if (!$admin) fail("Tài khoản không tồn tại", 401);

    // So khớp mật khẩu với hash bcrypt lưu trong DB (không giải ngược hash)
    if (!password_verify($password, $admin["password_hash"])) fail("Sai mật khẩu", 401);

    $token = make_token(["id" => (int)$admin["id"], "username" => $admin["username"]]);
    respond([
        "token" => $token,
        "admin" => admin_out($admin, false)
    ]);
}

if ($method === "GET" && ($action === "" || $action === "me")) {
    $auth = require_auth();
    $admin = db_one("SELECT * FROM admins WHERE id = ?", [(int)$auth["id"]]);
    if (!$admin) fail("Không tìm thấy", 404);
    respond(admin_out($admin));
}

if ($method === "POST" && $action === "change-password") {
    $auth = require_auth();
    $body = body_json();
    $oldPassword = is_string($body["oldPassword"] ?? null) ? $body["oldPassword"] : "";
    $newPassword = is_string($body["newPassword"] ?? null) ? $body["newPassword"] : "";
    if ($oldPassword === "" || strlen($newPassword) < 6) {
        fail("Mật khẩu mới ít nhất 6 ký tự");
    }
    $admin = db_one("SELECT * FROM admins WHERE id = ?", [(int)$auth["id"]]);
    if (!$admin) fail("Tài khoản không tồn tại", 404);
    if (!password_verify($oldPassword, $admin["password_hash"])) {
        fail("Mật khẩu cũ sai");
    }
    db_exec("UPDATE admins SET password_hash = ? WHERE id = ?",
        [password_hash($newPassword, PASSWORD_BCRYPT), (int)$admin["id"]]);
    respond(["success" => true]);
}

if ($method === "GET" && $action === "admins") {
    require_auth();
    respond(array_map("admin_out", db_all("SELECT * FROM admins ORDER BY id")));
}

if ($method === "POST" && $action === "admins") {
    require_auth();
    $body = body_json();
    $username = clean_str($body["username"] ?? "", 50);
    $password = is_string($body["password"] ?? null) ? $body["password"] : "";
    $name = clean_str($body["name"] ?? "", 100);
    $email = clean_str($body["email"] ?? "", 150);
    if ($username === "" || $password === "" || $name === "") fail("Thiếu thông tin");
    if (!preg_match("/^[A-Za-z0-9._-]{3,50}$/", $username)) fail("Username chỉ gồm chữ, số, . _ - (3-50 ký tự)");
    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail("Email không hợp lệ");
    if (strlen($password) < 6) fail("Mật khẩu ít nhất 6 ký tự");

    // Kiểm tra trước để báo lỗi rõ ràng; cột UNIQUE trong DB vẫn là lớp bảo vệ cuối cùng
    if (db_value("SELECT COUNT(*) FROM admins WHERE username = ?", [$username])) fail("Username đã tồn tại");
    if ($email !== "" && db_value("SELECT COUNT(*) FROM admins WHERE email = ?", [$email])) fail("Email đã tồn tại");

    $id = db_insert(
        "INSERT INTO admins (username, name, email, password_hash) VALUES (?, ?, ?, ?)",
        [$username, $name, $email !== "" ? $email : null, password_hash($password, PASSWORD_BCRYPT)]
    );
    respond(admin_out(db_one("SELECT * FROM admins WHERE id = ?", [$id]), false));
}

if ($method === "DELETE" && $action === "admins") {
    $auth = require_auth();
    $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
    db_tx(function () use ($id, $auth) {
        // FOR UPDATE khóa các dòng admin trong lúc kiểm tra, để 2 người xóa cùng lúc
        // không thể làm hệ thống mất hết admin
        $count = count(db_all("SELECT id FROM admins FOR UPDATE"));
        if ($count <= 1) fail("Phải giữ lại ít nhất 1 admin");
        if (!db_one("SELECT id FROM admins WHERE id = ?", [$id])) fail("Không tìm thấy", 404);
        if ($id === (int)$auth["id"]) fail("Không thể xóa chính mình");
        db_exec("DELETE FROM admins WHERE id = ?", [$id]);
    });
    respond(["success" => true]);
}

fail("Không hỗ trợ yêu cầu này", 405);
