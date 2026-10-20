<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

date_default_timezone_set("Asia/Ho_Chi_Minh");

// Thư mục data/ giờ chỉ còn giữ khóa ký token (.jwt_secret); dữ liệu đã chuyển sang MySQL
define("DATA_DIR", dirname(__DIR__) . DIRECTORY_SEPARATOR . "data");

// Secret ký token: ưu tiên biến môi trường GODRIVE_JWT_SECRET, nếu không có thì
// tự sinh ngẫu nhiên một lần và lưu vào data/.jwt_secret (thư mục data bị chặn truy cập web).
function load_jwt_secret() {
    $env = getenv("GODRIVE_JWT_SECRET");
    if ($env !== false && strlen($env) >= 16) return $env;

    $file = DATA_DIR . DIRECTORY_SEPARATOR . ".jwt_secret";
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    if (is_file($file)) {
        $secret = trim((string)file_get_contents($file));
        if (strlen($secret) >= 32) return $secret;
    }
    $secret = bin2hex(random_bytes(32));
    file_put_contents($file, $secret, LOCK_EX);
    return $secret;
}
define("JWT_SECRET", load_jwt_secret());

function body_json() {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail($message, $code = 400) {
    respond(["error" => $message], $code);
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
}

function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat("=", 4 - $remainder);
    }
    return base64_decode(strtr($data, "-_", "+/"));
}

function make_token($payload) {
    $payload["exp"] = time() + 7 * 24 * 3600;
    $body = base64url_encode(json_encode($payload));
    $sig = base64url_encode(hash_hmac("sha256", $body, JWT_SECRET, true));
    return $body . "." . $sig;
}

function verify_token($token) {
    $parts = explode(".", $token);
    if (count($parts) !== 2) return null;
    [$body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac("sha256", $body, JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64url_decode($body), true);
    if (!is_array($payload)) return null;
    if (!isset($payload["exp"]) || $payload["exp"] < time()) return null;
    return $payload;
}

function bearer_token() {
    $header = "";
    if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
        $header = $_SERVER["HTTP_AUTHORIZATION"];
    } elseif (isset($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])) {
        $header = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"];
    } elseif (function_exists("apache_request_headers")) {
        $headers = apache_request_headers();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === "authorization") {
                $header = $v;
                break;
            }
        }
    }
    if (stripos($header, "Bearer ") === 0) {
        return trim(substr($header, 7));
    }
    return null;
}

function require_auth() {
    $token = bearer_token();
    if (!$token) fail("Chưa đăng nhập", 401);
    $admin = verify_token($token);
    if (!$admin) fail("Token không hợp lệ", 401);
    return $admin;
}

function method() {
    return strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
}

function action() {
    return isset($_GET["action"]) ? $_GET["action"] : "";
}

// ===== Validate đầu vào =====
const BOOKING_STATUSES = ["pending", "confirmed", "cancelled"];
const CAR_STATUSES = ["available", "rented", "maintenance"];
const CAR_TYPES = ["Sedan", "SUV", "Hatchback"];
const MAINT_STATUSES = ["scheduled", "in_progress", "completed"];
// Hình thức thanh toán admin chọn khi duyệt đơn ("COD" giữ lại để đọc dữ liệu cũ)
const PAYMENT_METHODS = ["Tiền mặt", "Chuyển khoản", "Thẻ tín dụng", "Ví MoMo", "ZaloPay", "VNPay", "COD"];
const PAYMENT_STATUSES = ["pending", "paid", "refunded"];
const CAR_COLORS = ["", "purple", "blue", "red", "white", "black", "silver", "teal", "orange"];

function txn_code() {
    return "TXN-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(2)));
}

// Thời điểm thanh toán admin nhập: "Y-m-d H:i" hoặc "Y-m-d\TH:i" (ô datetime-local)
function clean_datetime($value) {
    $value = str_replace("T", " ", trim((string)$value));
    if ($value === "") return "";
    $d = DateTime::createFromFormat("Y-m-d H:i", substr($value, 0, 16));
    if (!$d) fail("Thời điểm thanh toán không hợp lệ");
    return $d->format("Y-m-d H:i");
}

function clean_str($value, $max = 200) {
    $value = is_scalar($value) ? trim((string)$value) : "";
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', "", $value) ?? "";
    // Cắt theo ký tự UTF-8 (không dùng mbstring vì hosting có thể không bật)
    if (preg_match('/^.{0,' . (int)$max . '}/su', $value, $m)) return $m[0];
    return substr($value, 0, $max);
}

function valid_date($value) {
    if (!is_string($value)) return false;
    $d = DateTime::createFromFormat("Y-m-d", $value);
    return $d && $d->format("Y-m-d") === $value;
}

// Số điện thoại: chỉ gồm chữ số, 9-10 số
function valid_phone($value) {
    return (bool)preg_match('/^\d{9,10}$/', $value);
}

// Họ tên: chỉ gồm chữ cái (kể cả tiếng Việt có dấu), các từ cách nhau một khoảng trắng
function valid_name($value) {
    return (bool)preg_match('/^[\p{L}\p{M}]+(?: [\p{L}\p{M}]+)*$/u', $value);
}

// Chỉ cho phép ảnh http(s) hoặc để trống.
function clean_image_url($value) {
    $url = is_scalar($value) ? trim((string)$value) : "";
    if ($url === "") return "";
    if (strlen($url) > 2000 || !preg_match("#^https?://#i", $url)) {
        fail("Link ảnh phải bắt đầu bằng http:// hoặc https://");
    }
    return $url;
}

function require_enum($value, $allowed, $label) {
    if (!in_array($value, $allowed, true)) fail("$label không hợp lệ");
    return $value;
}

// ===== Hạng khách hàng =====
// Xếp theo tổng tiền thuê của các đơn ĐÃ XÁC NHẬN. Mức cao nhất là trên 1 tỷ đồng,
// các mức dưới 1 tỷ được xếp tự động theo mốc tiền bên dưới (sửa mốc tại đây và trong js/app.js, js/admin.js).
const CUSTOMER_TIERS = [
    // [mã hạng, số tiền tối thiểu, true = phải VƯỢT QUA mốc (>) thay vì chỉ đạt (>=)]
    ["diamond", 1000000000, true],
    ["platinum", 500000000, false],
    ["gold", 200000000, false],
    ["silver", 50000000, false],
    ["bronze", 0, false]
];

function customer_tier($spent) {
    $spent = (int)$spent;
    foreach (CUSTOMER_TIERS as [$code, $min, $strict]) {
        if ($strict ? $spent > $min : $spent >= $min) return $code;
    }
    return "bronze";
}

function phone_norm($phone) {
    return preg_replace('/\D+/', "", (string)$phone);
}

// ===== Kết nối database và lớp chuyển đổi dữ liệu =====
require_once __DIR__ . "/_db.php";
require_once __DIR__ . "/_models.php";
