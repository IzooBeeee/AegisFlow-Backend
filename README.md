# AegisFlow AI Backend

Laravel 13 API backend cho hệ thống giám sát lũ lụt và điều phối cứu hộ thông minh.

## Yêu cầu

- PHP 8.3+
- Composer 2.x
- PostgreSQL 16 + PostGIS 3.4 (Production)
- SQLite (Development)

## Cài đặt

```bash
# Cài đặt dependencies
composer install

# Copy env
cp .env.example .env

# Generate key
php artisan key:generate

# Chạy migrations (SQLite dev)
php artisan migrate

# Seed data mẫu
php artisan db:seed
```

## Migrations PostgreSQL + PostGIS

```bash
php artisan migrate
php artisan db:seed
```

## Test

```bash
php artisan test
# hoặc
./vendor/bin/phpunit
```

## API Documentation

### Authentication

| Method | Endpoint | Description |
|--------|---------|------------|
| POST | `/api/auth/login` | Đăng nhập |
| POST | `/api/auth/register` | Đăng ký |
| GET | `/api/auth/me` | Thông tin user |
| PUT | `/api/auth/profile` | Cập nhật profile |
| POST | `/api/auth/fcm-token` | Cập nhật FCM token |
| POST | `/api/auth/logout` | Đăng xuất |

### Incidents

| Method | Endpoint | Description |
|--------|---------|------------|
| GET | `/api/incidents` | Danh sách sự cố |
| POST | `/api/incidents` | Tạo sự cố |
| GET | `/api/incidents/{id}` | Chi tiết |
| PATCH | `/api/incidents/{id}` | Cập nhật |

### Rescue Requests

| Method | Endpoint | Description |
|--------|---------|------------|
| GET | `/api/rescue-requests` | Danh sách yêu cầu |
| POST | `/api/rescue-requests` | Tạo yêu cầu |
| PUT | `/api/rescue-requests/{id}/assign` | Phân công đội |
| PUT | `/api/rescue-requests/{id}/status` | Cập nhật trạng thái |

### Flood Zones

| Method | Endpoint | Description |
|--------|---------|------------|
| GET | `/api/flood-zones` | Danh sách vùng ngập |
| POST | `/api/flood-zones` | Tạo vùng ngập |
| GET | `/api/flood-zones/geojson` | GeoJSON |
| PUT | `/api/flood-zones/{id}` | Cập nhật |

### Rescue Teams & Shelters

| Method | Endpoint | Description |
|--------|---------|------------|
| GET | `/api/rescue-teams` | Danh sách đội cứu hộ |
| PUT | `/api/rescue-teams/{id}/location` | Cập nhật GPS |
| GET | `/api/shelters` | Danh sách trú ẩn |

### AI Predictions

| Method | Endpoint | Description |
|--------|---------|------------|
| POST | `/api/predictions/trigger` | Kích hoạt dự báo |
| GET | `/api/predictions` | Danh sách dự báo |
| PUT | `/api/predictions/{id}/verify` | Xác nhận dự báo |

### Map

| Method | Endpoint | Description |
|--------|---------|------------|
| GET | `/api/map/all` | Tất cả dữ liệu map |
| GET | `/api/map/incidents` | Incidents GeoJSON |
| GET | `/api/map/flood-zones` | Flood zones GeoJSON |
| GET | `/api/map/rescue-teams` | Rescue teams GeoJSON |
| GET | `/api/map/shelters` | Shelters GeoJSON |

### Analytics

| Method | Endpoint | Description |
|--------|---------|------------|
| GET | `/api/analytics/overview` | Dashboard KPIs |

### Admin

| Method | Endpoint | Description |
|--------|---------|------------|
| GET | `/api/admin/users` | User management |
| POST | `/api/admin/users` | Tạo user |
| GET | `/api/admin/stats` | System stats |
| GET | `/api/admin/logs` | Activity logs |

## Roles & Permissions

| Role | Permissions |
|------|-------------|
| `city_admin` | Toàn quyền |
| `rescue_operator` | Điều phối cứu hộ |
| `rescue_team` | Thực địa |
| `citizen` | Gửi báo cáo, yêu cầu cứu hộ |
| `ai_operator` | Vận hành AI |

## Default Users (after seed)

| Email | Password | Role |
|-------|---------|------|
| admin@aegisflow.ai | password | city_admin |
| operator@aegisflow.ai | password | rescue_operator |
| rescue@aegisflow.ai | password | rescue_team |
| ai@aegisflow.ai | password | ai_operator |
| citizen@example.com | password | citizen |

## Tech Stack

- Laravel 13
- Laravel Sanctum (API token auth)
- Laravel Spatie Permission (RBAC)
- Laravel Reverb (WebSocket realtime)
- PostgreSQL 16 + PostGIS 3.4
- PHPUnit 12
