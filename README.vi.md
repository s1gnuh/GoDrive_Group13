<div align="center">

# 🚗 GoDrive

### Nền tảng thuê xe tự lái: trang khách hàng + trang quản trị

**Đặt xe trong 30 giây · Không cần tài khoản · Giá minh bạch**

[![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![JavaScript](https://img.shields.io/badge/JavaScript-thuần-F7DF1E?logo=javascript&logoColor=black)](#-công-nghệ)
[![Database](https://img.shields.io/badge/CSDL-không_cần_(file_JSON)-0f9f8f)](#các-file-dữ-liệu)
[![i18n](https://img.shields.io/badge/Ngôn_ngữ-Việt_%7C_Anh-5b3fd9)](#-tính-năng)
[![Theme](https://img.shields.io/badge/Giao_diện-Sáng_%7C_Tối-141a2e)](#-tính-năng)
[![Project](https://img.shields.io/badge/Dự_án-Học_tập_%2F_phi_thương_mại-orange)](#%EF%B8%8F-tuyên-bố-miễn-trừ)

[**Xem demo**](https://godrive.rf.gd) · [Trang quản trị](https://godrive.rf.gd/admin) · [English](README.md) · **Tiếng Việt**

</div>

> [!IMPORTANT]
> **GoDrive là dự án học tập phi thương mại, không phải dịch vụ cho thuê xe thật.** Không có đơn đặt xe nào được thực hiện và website không nhận thanh toán; xe, giá và số liệu đều là dữ liệu mẫu. Khi thử đặt xe, vui lòng dùng thông tin giả. Xem [Tuyên bố miễn trừ](#%EF%B8%8F-tuyên-bố-miễn-trừ) và trang [`/disclaimer.html`](disclaimer.html).

---

## 📑 Mục lục

- [Tổng quan](#-tổng-quan)
- [Ảnh chụp màn hình](#-ảnh-chụp-màn-hình)
- [Tính năng](#-tính-năng)
- [Cách hoạt động](#-cách-hoạt-động)
- [Công nghệ](#-công-nghệ)
- [Cài đặt và chạy](#-cài-đặt-và-chạy)
- [Hướng dẫn cho khách hàng](#-hướng-dẫn-cho-khách-hàng)
- [Hướng dẫn cho quản trị viên](#%EF%B8%8F-hướng-dẫn-cho-quản-trị-viên)
- [Triển khai lên hosting](#%EF%B8%8F-triển-khai-lên-hosting)
- [Cấu trúc dự án](#%EF%B8%8F-cấu-trúc-dự-án)
- [Tài liệu API](#-tài-liệu-api)
- [Bảo mật](#-bảo-mật)
- [Xử lý sự cố](#-xử-lý-sự-cố)
- [Định hướng phát triển](#%EF%B8%8F-định-hướng-phát-triển)
- [Tuyên bố miễn trừ](#%EF%B8%8F-tuyên-bố-miễn-trừ)

---

## 🔎 Tổng quan

GoDrive mô phỏng trọn quy trình của một cửa hàng cho thuê xe nhỏ:

1. Khách xem xe, chọn ngày và đặt xe **không cần tạo tài khoản**.
2. Admin xem đơn và **duyệt đơn, đồng thời chọn hình thức thanh toán**.
3. Khi giao dịch được **xác nhận đã thu tiền**, số tiền mới được tính vào doanh thu, và tổng chi tiêu giúp khách lên **hạng thành viên**.

Toàn bộ viết bằng **HTML, CSS, JavaScript thuần và PHP**, dữ liệu lưu trong file JSON, nên chạy được trên hosting PHP miễn phí (ví dụ InfinityFree) mà không cần cơ sở dữ liệu hay bước build.

---

## 📸 Ảnh chụp màn hình

<table>
  <tr>
    <td width="62%"><img src="docs/screenshots/01-home.png" alt="Trang chủ chế độ tối" /></td>
    <td width="38%">
      <h3>🏠 Trang chủ</h3>
      <p>Hero có hiệu ứng, số liệu lấy từ dữ liệu thật (số xe sẵn sàng, giá thấp nhất, điểm đánh giá) và ô tìm xe theo địa điểm, ngày thuê.</p>
    </td>
  </tr>
  <tr>
    <td width="38%">
      <h3>🚙 Danh sách & bộ lọc</h3>
      <p>Lọc theo dáng xe và hãng, sắp xếp theo giá hoặc độ nổi bật. Mỗi thẻ hiện tiền tạm tính theo ngày đã chọn. Xe chưa có ảnh được vẽ minh họa tự động.</p>
    </td>
    <td width="62%"><img src="docs/screenshots/02-cars.png" alt="Danh sách xe và bộ lọc" /></td>
  </tr>
  <tr>
    <td width="62%"><img src="docs/screenshots/03-car-details.png" alt="Popup chi tiết xe" /></td>
    <td width="38%">
      <h3>🔍 Chi tiết xe</h3>
      <p>Thông số, quyền lợi, tiền tạm tính và các xe tương tự, đặt xe chỉ với một nút bấm.</p>
    </td>
  </tr>
  <tr>
    <td width="38%">
      <h3>📊 Tổng quan quản trị</h3>
      <p>Doanh thu đã thu (chỉ tính giao dịch đã thanh toán) kèm biểu đồ 7/30 ngày và xu hướng, số tiền chờ thu, trạng thái đơn, đội xe và việc cần làm.</p>
    </td>
    <td width="62%"><img src="docs/screenshots/04-admin-dashboard.png" alt="Trang tổng quan" /></td>
  </tr>
  <tr>
    <td width="62%"><img src="docs/screenshots/05-admin-approve.png" alt="Duyệt đơn kèm hình thức thanh toán" /></td>
    <td width="38%">
      <h3>✅ Duyệt đơn & ghi nhận thanh toán</h3>
      <p>Duyệt đơn bắt buộc chọn hình thức thanh toán và cho biết đã thu tiền hay chưa. Giao dịch được ghi nhận ngay lúc duyệt.</p>
    </td>
  </tr>
  <tr>
    <td width="38%">
      <h3>💳 Thanh toán</h3>
      <p>Tổng đã thu, chờ thu, đã hoàn tiền, cơ cấu theo hình thức thanh toán và lịch sử giao dịch luôn đồng bộ với đơn, chỉnh sửa được.</p>
    </td>
    <td width="62%"><img src="docs/screenshots/06-admin-payments.png" alt="Trang thanh toán" /></td>
  </tr>
</table>

<sub>Ảnh chụp ở chế độ tối, dùng dữ liệu mẫu.</sub>

---

## ✨ Tính năng

### Trang khách hàng

| Nhóm | Chi tiết |
|---|---|
| **Tìm xe** | Lọc theo dáng xe (Sedan / SUV / Hatchback), hãng và thành phố; sắp xếp theo giá hoặc độ nổi bật; dải hãng xe và thẻ thành phố để chọn nhanh |
| **Hiển thị xe** | Ảnh thật hoặc hình minh họa SVG theo dáng xe và màu sơn; popup chi tiết có thông số, quyền lợi và xe tương tự |
| **Đặt xe** | Không cần tài khoản; tổng tiền tự tính (giá/ngày × số ngày); họ tên chỉ chữ cái, số điện thoại 9–10 số; chặn đặt trùng lịch; tối đa 90 ngày |
| **Tra cứu** | Chỉ cần email đã dùng khi đặt; xem trạng thái đơn, **tình trạng thanh toán**, **hạng thành viên** và tổng tiền đã thuê |
| **Trải nghiệm** | Việt ↔ Anh, sáng ↔ tối (lần đầu theo máy), tối ưu cho điện thoại, popup hướng dẫn, nút tải lại bản mới nhất |

### Trang quản trị (`/admin`)

| Nhóm | Chi tiết |
|---|---|
| **Tổng quan** | Doanh thu đã thu, so với 30 ngày trước, số tiền chờ thu, biểu đồ 7/30 ngày, trạng thái đơn và đội xe, việc cần làm |
| **Đơn đặt xe** | Tab, tìm kiếm, chi tiết, gọi/email khách; **duyệt kèm hình thức thanh toán**, bàn giao xe, hủy đơn; cột thanh toán cho từng đơn |
| **Thanh toán** | Tạo khi duyệt đơn; xác nhận đã thu; **sửa** hình thức, trạng thái, số tiền, thời điểm thu, ghi chú; cơ cấu theo hình thức; tự đồng bộ với đơn |
| **Đội xe** | Thêm/sửa/xóa xe, link ảnh hoặc màu sơn cho hình minh họa, đánh dấu nổi bật, đổi trạng thái ngay trên thẻ |
| **Khách hàng** | Tự xếp hạng theo số tiền thuê với 5 hạng (Kim cương = trên 1 tỷ), lọc theo hạng, tiến độ lên hạng kế tiếp |
| **Bảo trì** | Lên lịch → bắt đầu (xe ẩn khỏi trang khách) → hoàn thành (xe sẵn sàng trở lại) |
| **Hệ thống** | Nhiều tài khoản admin, đổi mật khẩu có đo độ mạnh, xuất CSV, tự làm mới mỗi phút, phím tắt `/`, hướng dẫn tích hợp |

---

## 🔄 Cách hoạt động

### Vòng đời đơn đặt xe

```
               ┌───────────── Duyệt + chọn hình thức thanh toán ─────────────┐
               │                                                             ▼
 Khách ──▶ Chờ duyệt                                                    Đã xác nhận ──[Bàn giao]──▶ Xe "Đang thuê"
               │                                                             │
               └──────────────[Hủy]──────────────┬──────────────[Hủy]────────┘
                                                 ▼
                                              Đã hủy  (xe đang thuê trở về "Sẵn sàng")
```

### Thanh toán và doanh thu

| Sự kiện | Giao dịch | Doanh thu |
|---|---|---|
| Khách đặt xe | — | Chưa tính |
| Admin duyệt, **chưa thu tiền** | Tạo giao dịch *Chờ thanh toán* với hình thức đã chọn | **Chưa tính** (hiện ở "Chờ thu") |
| **Xác nhận đã thu tiền** | *Đã thanh toán*, kèm thời điểm thu | **Tính** vào ngày thu tiền |
| Hủy đơn đã duyệt | *Đã thanh toán* → *Đã hoàn tiền*; *Chờ thanh toán* → bị bỏ | Bị trừ khỏi doanh thu |

Giao dịch luôn đồng bộ với đơn: tên khách, tên xe và số tiền lấy theo đơn, trừ khi admin đã tự sửa số tiền (có nút lấy lại theo tổng tiền đơn). Đơn được duyệt trước khi có tính năng thanh toán sẽ tự có giao dịch chờ bổ sung hình thức.

### Hạng thành viên

Hạng được tính tự động từ tổng tiền các đơn **đã duyệt**:

| Hạng | Tổng tiền thuê |
|---|---|
| 💎 Kim cương | **trên** 1.000.000.000 ₫ |
| Bạch kim | từ 500.000.000 ₫ |
| Vàng | từ 200.000.000 ₫ |
| Bạc | từ 50.000.000 ₫ |
| Đồng | dưới 50.000.000 ₫ |

Các mốc nằm ở `api/_helpers.php` (`CUSTOMER_TIERS`) và được khai báo tương ứng trong `js/app.js`, `js/admin.js`.

---

## 🧰 Công nghệ

| Thành phần | Công nghệ |
|---|---|
| Giao diện | HTML5, CSS3 (biến CSS, bộ màu sáng/tối), JavaScript thuần (ES2020) |
| Biểu đồ | [Chart.js 4](https://www.chartjs.org/) (chỉ trang quản trị, qua CDN) |
| Máy chủ | PHP 8 (không framework), API dạng REST trả JSON |
| Lưu trữ | MySQL / MariaDB qua PDO (bản gốc dùng file JSON trong `data/`) |
| Xác thực | Token ký HMAC, mật khẩu băm bcrypt |
| Hosting | Bất kỳ hosting Apache + PHP (`.htaccess`), hoặc `php -S` trên máy |

---

## 🚀 Cài đặt và chạy

### Yêu cầu

- **PHP 8.0 trở lên**, có bật `pdo_mysql` (XAMPP bật sẵn)
- **MySQL hoặc MariaDB** (ví dụ XAMPP)
- Trình duyệt hiện đại

| Hệ điều hành | Cài PHP |
|---|---|
| Windows | `winget install PHP.PHP.8.4` (cài xong mở lại terminal) |
| macOS | `brew install php` |
| Ubuntu / Debian | `sudo apt install php-cli` |

### Chạy trên máy (Windows + XAMPP)

1. Mở **XAMPP Control Panel**, bấm **Start** ở dòng **MySQL**.
2. Tạo database và các bảng: mở http://localhost/phpmyadmin (cần Start cả Apache), chọn **Import**, chọn `database/schema.sql`, bấm **Import**.
   Hoặc dùng dòng lệnh: `D:\xampp\mysql\bin\mysql.exe -u root -e "source database/schema.sql"`
3. Chuyển dữ liệu mẫu từ `data/*.json` vào MySQL:
   ```powershell
   D:\xampp\php\php.exe database\migrate_json.php
   ```
4. Chạy web bằng PHP của XAMPP (đã bật sẵn `pdo_mysql`):
   ```powershell
   D:\xampp\php\php.exe -S localhost:8080 router.php
   ```

Thông tin kết nối (host, user, mật khẩu, tên database) nằm trong `api/_config.php`. Mặc định khớp với XAMPP: user `root`, không mật khẩu, database `godrive`.

| Trang | Địa chỉ |
|---|---|
| Trang khách hàng | http://localhost:8080/ |
| Trang quản trị | http://localhost:8080/admin |
| Tuyên bố miễn trừ | http://localhost:8080/disclaimer.html |

**Tài khoản admin mặc định:** `admin` / `admin123`. Hãy đổi mật khẩu ngay sau lần đăng nhập đầu tiên.

> [!WARNING]
> Không mở trực tiếp `index.html` (nhấp đúp hoặc Live Server). Khi đó phần PHP (API) không chạy và trang không tải được danh sách xe.

---

## 📖 Hướng dẫn cho khách hàng

### Tìm xe

1. Chọn **địa điểm nhận xe** và **ngày thuê** ở ô tìm kiếm, bấm **Tìm xe**.
2. Lọc theo **dáng xe** hoặc **hãng**, hoặc bấm **thẻ thành phố** / **hãng xe** ở trang chủ.
3. Sắp xếp theo **Nổi bật nhất**, **Giá thấp đến cao** hoặc **Giá cao đến thấp**.
4. Bấm tên xe hoặc **Xem chi tiết** để xem thông số và xe tương tự.

### Đặt xe

1. Bấm **Đặt xe** trên thẻ (hoặc **Đặt xe này** trong popup chi tiết).
2. Nhập họ tên (chỉ chữ cái), số điện thoại (9–10 số) và email.
3. Chọn địa điểm, ngày nhận và trả xe (tối đa 90 ngày), kiểm tra **Tổng cộng** rồi bấm **Xác nhận đặt xe**.
4. Ghi lại **mã đơn**. Đơn ở trạng thái **Chờ xác nhận** cho tới khi được duyệt.

### Tra cứu đơn

Bấm **Tra cứu đơn**, nhập email đã dùng khi đặt. Kết quả hiện trạng thái từng đơn, tình trạng thanh toán (hình thức · đã/chưa thanh toán · đã hoàn tiền), hạng thành viên và tổng tiền đã thuê.

---

## 🛠️ Hướng dẫn cho quản trị viên

### Đơn đặt xe

- Dùng các tab (**Tất cả / Chờ duyệt / Đã xác nhận / Đã hủy**) và ô tìm kiếm (mã đơn, tên, số điện thoại, email, tên xe).
- **Duyệt** mở hộp thoại: chọn **hình thức thanh toán** (tiền mặt, chuyển khoản, thẻ tín dụng, MoMo, ZaloPay, VNPay), chọn **đã thu** hoặc **chưa thu tiền**, thêm ghi chú nếu cần rồi xác nhận.
- **Bàn giao** chuyển xe sang *Đang thuê*; khi khách trả xe, vào **Đội xe** đổi về *Sẵn sàng*.
- **Hủy** luôn có hộp thoại xác nhận; hủy đơn đã duyệt sẽ hoàn tiền hoặc bỏ giao dịch tương ứng.

### Thanh toán

- **Xác nhận đã thu** khi khách trả tiền sau; doanh thu được ghi nhận từ lúc này.
- **Sửa (✎)** để đổi hình thức, trạng thái (chờ thanh toán / đã thanh toán / đã hoàn tiền), số tiền, thời điểm thu hoặc ghi chú. Hệ thống lưu người sửa và thời gian sửa.
- Dải cảnh báo màu cam báo các giao dịch còn thiếu hình thức thanh toán.

### Đội xe, khách hàng, bảo trì

- **Đội xe:** nhập link ảnh (`https://…`) hoặc chọn **màu sơn** cho hình minh họa; viết tên hãng thống nhất để bộ lọc hãng hoạt động đúng.
- **Khách hàng:** bấm thẻ hạng để lọc; xuất CSV.
- **Bảo trì:** lên lịch, **Bắt đầu** (xe ẩn khỏi trang khách), **Hoàn thành** (xe sẵn sàng trở lại).

### Phím tắt

`/` nhảy tới ô tìm kiếm · `Esc` đóng hộp thoại · ↻ làm mới dữ liệu (tự động mỗi phút).

---

## ☁️ Triển khai lên hosting

### Lần đầu (InfinityFree hoặc hosting Apache + PHP bất kỳ)

1. Upload mã nguồn vào thư mục gốc (`htdocs`), **kể cả các file ẩn `.htaccess`**.
2. Đảm bảo thư mục `data/` ghi được.
3. Mở `https://ten-mien/data/admins.json`: **phải trả về 403 Forbidden**.
4. Đăng nhập `/admin` và đổi mật khẩu mặc định.

### Cập nhật web đang chạy

```powershell
powershell -ExecutionPolicy Bypass -File .\build-deploy.ps1
```

Script gắn số phiên bản mới vào `version.json` và các link `?v=` trong HTML, rồi tạo `..\godrive-deploy.zip` **không chứa `data/`**. Upload file zip vào `htdocs`, **Extract** và chọn **Overwrite**, sau đó bấm **Tải lại bản mới nhất** (🔄) để trình duyệt bỏ bản cũ trong bộ nhớ đệm.

> [!CAUTION]
> Không bao giờ upload thư mục `data/` đè lên web thật: sẽ mất toàn bộ đơn đặt xe và tài khoản.

---

## 🗂️ Cấu trúc dự án

```
├── index.html            # Trang khách hàng
├── admin.html            # Trang quản trị
├── disclaimer.html       # Tuyên bố dự án học tập (Việt/Anh)
├── css/
│   ├── style.css         # Giao diện trang khách (sáng/tối)
│   └── admin.css         # Giao diện trang quản trị (sáng/tối)
├── js/
│   ├── app.js            # Logic trang khách + bản dịch
│   ├── admin.js          # Logic trang quản trị + bản dịch
│   ├── car-art.js        # Hình minh họa xe SVG dùng chung
│   └── brand-logos.js    # Logo chính thức hãng xe (img/brands/), dùng chung
├── img/brands/           # File logo PNG dùng bởi brand-logos.js
├── api/
│   ├── _helpers.php      # Lưu JSON, khóa file, token, kiểm tra dữ liệu, hạng khách, đồng bộ thanh toán
│   ├── login.php         # Đăng nhập, mật khẩu, tài khoản admin
│   ├── products.php      # Xe và quản lý đội xe
│   ├── orders.php        # Đơn đặt xe, tra cứu, duyệt/hủy/bàn giao
│   └── users.php         # Khách hàng, thanh toán, bảo trì
├── data/                 # Dữ liệu JSON (chặn truy cập từ web)
├── docs/screenshots/     # Ảnh dùng trong README
├── build-deploy.ps1      # Tạo gói godrive-deploy.zip
├── version.json          # Phiên bản hiện tại (làm mới bộ nhớ đệm)
├── .htaccess             # Định tuyến, chống cache, chặn file ẩn
└── router.php            # Router cho php -S (chỉ dùng trên máy)
```

### Database (MySQL)

| Bảng | Nội dung |
|---|---|
| `cars` | Danh sách xe (`image`, `color` có thể để trống) |
| `bookings` | Đơn đặt xe, khóa ngoại `car_id → cars.id` |
| `payments` | Giao dịch, khóa ngoại `booking_id → bookings.id`; tên khách/tên xe lấy bằng JOIN |
| `customers` | Khách hàng và tổng tiền thuê; hạng tính khi truy vấn, không lưu |
| `maintenance` | Lịch bảo trì, khóa ngoại `car_id → cars.id` |
| `admins` | Tài khoản admin (mật khẩu băm bcrypt) |

| File | Vai trò |
|---|---|
| `database/schema.sql` | Tạo database `godrive` và 6 bảng |
| `database/migrate_json.php` | Chuyển dữ liệu từ `data/*.json` vào MySQL |
| `api/_config.php` | Thông tin kết nối MySQL |
| `api/_db.php` | Kết nối PDO, hàm truy vấn, transaction |
| `api/_models.php` | Đổi dòng trong bảng thành JSON cho frontend |
| `data/.jwt_secret` | Khóa ký token, tự tạo khi chạy lần đầu, không đưa lên Git |

---|---|
| `data/cars.json` | Danh sách xe (có thể có `image`, `color`) |
| `data/bookings.json` | Đơn đặt xe (kèm `paymentMethod`, `approvedAt`) |
| `data/payments.json` | Giao dịch (`method`, `status`, `amount`, `paidAt`, `approvedBy`…) |
| `data/customers.json` | Khách hàng, tổng tiền thuê và hạng |
| `data/maintenance.json` | Lịch bảo trì |
| `data/admins.json` | Tài khoản admin (mật khẩu băm bcrypt) |
| `data/.jwt_secret` | Khóa ký token, tự tạo khi chạy lần đầu, không đưa lên Git |

---

## 🔌 Tài liệu API

Mọi endpoint trả về JSON. Endpoint cần đăng nhập đọc header `Authorization: Bearer <token>`.

| Endpoint | Phương thức | Mô tả | Cần đăng nhập |
|---|---|---|---|
| `api/products.php` | GET | Danh sách xe | — |
| `api/products.php` | POST · PUT · DELETE | Thêm, sửa, xóa xe | ✔ |
| `api/products.php?action=status&id=` | PATCH | Đổi trạng thái xe | ✔ |
| `api/orders.php` | POST | Khách đặt xe | — |
| `api/orders.php?action=lookup&email=` | GET | Đơn, tình trạng thanh toán và hạng theo email | — |
| `api/orders.php` | GET | Toàn bộ đơn | ✔ |
| `api/orders.php?id=` | PATCH | Duyệt (`status`, `paymentMethod`, `paymentStatus`, `paymentNote`) hoặc hủy | ✔ |
| `api/orders.php?action=mark-rented&id=` | PATCH | Bàn giao xe | ✔ |
| `api/users.php` | GET | Khách hàng kèm hạng và thứ hạng | ✔ |
| `api/users.php?action=payments` | GET | Giao dịch (đã đồng bộ với đơn) | ✔ |
| `api/users.php?action=payments&id=` | PATCH | Sửa `method`, `status`, `amount` / `syncAmount`, `paidAt`, `note` | ✔ |
| `api/users.php?action=maintenance` | GET · POST · PATCH · DELETE | Lịch bảo trì | ✔ |
| `api/login.php` | POST | Đăng nhập, nhận token | — |
| `api/login.php?action=…` | GET · POST · DELETE | Hồ sơ, đổi mật khẩu, quản lý admin | ✔ |

---

## 🔐 Bảo mật

- **Token** ký HMAC bằng khóa ngẫu nhiên trong `data/.jwt_secret` (hoặc biến môi trường `GODRIVE_JWT_SECRET`, tối thiểu 16 ký tự). Xóa file này sẽ đăng xuất mọi admin.
- **Mật khẩu** băm bằng bcrypt.
- **Kiểm tra dữ liệu** ở mọi API ghi: chỉ nhận trường cho phép, giá trị liệt kê, ngày, số tiền, quy tắc họ tên và số điện thoại.
- **Chống XSS:** mọi nội dung người dùng nhập đều được escape; link ảnh phải là `http(s)`; file CSV vô hiệu hóa công thức Excel.
- **Ghi đồng thời:** các thao tác ghi chạy lần lượt sau khóa file (`data/.write.lock`).
- **Bảo vệ dữ liệu:** thư mục `data/` và file ẩn bị chặn bởi `.htaccess` (hosting) và `router.php` (máy cá nhân).
- **Lưu ý quyền riêng tư:** tra cứu đơn chỉ cần email, nên ai biết email cũng xem được đơn của email đó. Cần thêm bước xác thực email nếu dùng ngoài mục đích demo.

---

## ❓ Xử lý sự cố

<details>
<summary><b>Danh sách xe không tải được / "Không kết nối được máy chủ"</b></summary>

PHP chưa chạy hoặc bạn mở trực tiếp file HTML. Chạy `php -S localhost:8080 router.php` rồi mở http://localhost:8080.
</details>

<details>
<summary><b>Giao diện vẫn là bản cũ sau khi upload</b></summary>

Bấm 🔄 **Tải lại bản mới nhất** (thanh menu trang khách hoặc thanh bên trang quản trị), hoặc nhấn **Ctrl + Shift + R**.
</details>

<details>
<summary><b>Quên mật khẩu admin</b></summary>

```bash
php -r "echo password_hash('matkhaumoi', PASSWORD_BCRYPT);"
```

Thay giá trị `passwordHash` của tài khoản trong `data/admins.json` bằng chuỗi vừa tạo.
</details>

<details>
<summary><b>Trên hosting đăng nhập được nhưng thao tác nào cũng báo "Chưa đăng nhập"</b></summary>

Hosting đã bỏ header `Authorization`. Kiểm tra file ẩn `.htaccess` đã được upload chưa; file này chuyển tiếp header đó.
</details>

<details>
<summary><b>FileZilla báo lỗi "451" khi upload file lớn</b></summary>

Chuyển kiểu truyền sang **Binary**, hoặc upload file zip qua File Manager của hosting rồi giải nén tại đó.
</details>

---

## 🗺️ Định hướng phát triển

- [ ] Mã xác thực qua email khi tra cứu đơn
- [ ] Thanh toán online bản thử nghiệm (VNPay / MoMo) và đặt cọc
- [ ] Gửi email khi đơn được duyệt
- [ ] Lịch trống của từng xe và biểu đồ thời gian đội xe cho admin
- [ ] Biên bản nhận xe: số km, xăng, ảnh hư hỏng, phụ phí
- [ ] Hóa đơn PDF, phân quyền admin, nhật ký thao tác
- [ ] Ưu đãi theo hạng thành viên và mã khuyến mãi
- [ ] Chuyển từ file JSON sang MySQL khi dữ liệu lớn

---

## ⚖️ Tuyên bố miễn trừ

GoDrive là **dự án cá nhân, phi thương mại, phục vụ mục đích học tập** và làm hồ sơ năng lực.

- Đây **không phải doanh nghiệp** và **không cung cấp** dịch vụ cho thuê xe thật. Đơn đặt xe không có giá trị.
- Website **không nhận thanh toán**. Hình thức thanh toán, giao dịch và doanh thu trong trang quản trị chỉ là **chức năng mô phỏng**, không gắn với tài khoản ngân hàng hay ví điện tử nào.
- Xe, giá, đánh giá và số liệu là **dữ liệu mẫu**. Vui lòng không nhập thông tin cá nhân thật.
- Tên hãng và mẫu xe thuộc về chủ sở hữu tương ứng, chỉ dùng để minh họa; dự án không liên kết hay được các hãng xác nhận. "GoDrive" là tên dự án, không liên quan đến doanh nghiệp nào có tên tương tự. Ảnh xe (nếu có) lấy từ Wikimedia Commons theo giấy phép của tác giả.

Toàn văn có trên website tại [`/disclaimer.html`](https://godrive.rf.gd/disclaimer.html).

---

<div align="center">

Thực hiện với ☕ trong quá trình học tập · Group 13 Mô hình hóa

</div>
