# EKG Laravel Deployment Notes

Dokumen ini mencatat cara menjalankan aplikasi EKG setelah migrasi ke Laravel.

## Service Utama

Aplikasi web dijalankan oleh Laravel:

```bash
php artisan serve
```

Subscriber MQTT dijalankan oleh Laravel:

```bash
php artisan mqtt:listen
```

Jangan jalankan `mqttnew.py` bersamaan dengan `php artisan mqtt:listen`, karena payload alat bisa diproses dua kali.

## Environment Penting

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iot
DB_USERNAME=root
DB_PASSWORD=

MQTT_HOST=160.187.144.147
MQTT_PORT=1883
MQTT_CLIENT_ID=ekg-laravel-subscriber
MQTT_AUTO_CREATE_PATIENT=true

EKG_PYTHON_BIN=/var/www/ProjectTA-HariKusryanto/venv310/bin/python3.10
EKG_PROCESSOR_PATH=
EKG_PROCESS_TIMEOUT=180
EKG_MODEL_PATH=
EKG_PREDICTION_TIMEOUT=60
```

Pada Windows lokal, `EKG_PYTHON_BIN` bisa diarahkan ke venv lama:

```env
EKG_PYTHON_BIN=E:\EKG\ProjectTA-HariKusryanto\ProjectTA-HariKusryanto\venv_windows\Scripts\python.exe
```

## Database VPS Dari Laptop

Untuk memakai database MySQL VPS dari Laravel lokal, gunakan SSH tunnel. Konfigurasi Laravel:

```env
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=iot
DB_USERNAME=root
DB_PASSWORD=1
```

Buka tunnel dari terminal PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-vps-db-tunnel.ps1
```

Atau manual:

```bash
ssh -N -L 3307:127.0.0.1:3306 -p 22 root@160.187.144.147
```

Biarkan terminal tunnel tetap terbuka selama Laravel menggunakan database VPS.

## Data Flow Baru

```text
EKG Device
  -> MQTT Broker
  -> php artisan mqtt:listen
  -> MySQL iot.recordekg
  -> Laravel Controller
  -> Blade Dashboard
```

Upload CSV:

```text
Laravel Upload
  -> scripts/process_ekg_upload.py
  -> scripts/processdata.py
  -> MQTT Broker
  -> php artisan mqtt:listen
  -> MySQL iot.recordekg
```

Python masih dipakai untuk pemrosesan sinyal dan model karena algoritma lama memakai `scipy`, `biosppy`, dan pickle scikit-learn. Web, routing, UI, CRUD, monitoring, dan subscriber MQTT sudah berada di Laravel.

## Contoh Systemd VPS

Web Laravel biasanya dijalankan dengan Nginx + PHP-FPM. Untuk MQTT subscriber:

```ini
[Unit]
Description=EKG Laravel MQTT Subscriber
After=network.target mysql.service mosquitto.service

[Service]
Type=simple
WorkingDirectory=/var/www/Ekg-project
ExecStart=/usr/bin/php artisan mqtt:listen
Restart=always
RestartSec=5
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```

Aktifkan:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now ekg-mqtt.service
sudo systemctl status ekg-mqtt.service
```

## Health Check

Endpoint penting:

```text
/                       Dashboard
/pasien                 Manajemen pasien
/rekaman                Daftar rekaman EKG
/monitoring             Monitoring service
/api/monitoring/latest  JSON status terbaru
/up                     Laravel health check
```

## Validasi Setelah Deploy

```bash
php artisan config:clear
php artisan route:list
php artisan view:cache
php artisan test
php artisan mqtt:listen --help
```
