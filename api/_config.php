<?php
// Thông tin kết nối MySQL.
// Mặc định khớp với XAMPP mới cài: user "root", KHÔNG có mật khẩu.
// Khi đưa lên hosting: đổi theo thông tin hosting cấp (hoặc đặt biến môi trường GODRIVE_DB_*).
return [
    "host"     => getenv("GODRIVE_DB_HOST") ?: "127.0.0.1",
    "port"     => getenv("GODRIVE_DB_PORT") ?: "3306",
    "database" => getenv("GODRIVE_DB_NAME") ?: "godrive",
    "username" => getenv("GODRIVE_DB_USER") ?: "root",
    "password" => getenv("GODRIVE_DB_PASS") ?: "",
];
