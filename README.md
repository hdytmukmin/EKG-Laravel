# EKG Monitoring AF / Non-AF

Aplikasi web monitoring EKG berbasis Laravel untuk manajemen pasien, sesi rekaman EKG, grafik sinyal EKG, monitoring alat, dan persiapan klasifikasi AF / Non-AF.

Project ini sudah memakai schema Laravel baru, bukan lagi struktur Flask lama. Data contoh EKG berasal dari CSV alat lama dan sudah tersedia dalam seeder.

## Fitur Saat Ini

- Login admin berbasis session Laravel.
- Role `super_admin` dan `admin_puskesmas`.
- Dashboard ringkasan pasien, AF, Non-AF, sesi terakhir, dan status sistem.
- Manajemen pasien.
- Riwayat sesi rekaman EKG.
- Detail sesi rekaman dengan grafik:
  - Raw signal
  - Filtered signal
  - R-peaks
  - BPM
  - RR interval
- Monitoring service dan alat EKG.
- Seeder data CSV EKG:
  - `Anarianti`
  - `Wahyu Saputra`
- Command import CSV manual.
- MQTT listener command untuk integrasi alat.

## Kebutuhan Sistem

- PHP `8.2` atau lebih baru.
- Composer.
- MySQL atau MariaDB.
- Node.js dan npm, jika ingin build asset Vite.
- Ekstensi PHP umum Laravel:
  - `pdo_mysql`
  - `openssl`
  - `mbstring`
  - `tokenizer`
  - `xml`
  - `ctype`
  - `json`
  - `fileinfo`

Catatan test: beberapa test memakai SQLite. Jika `pdo_sqlite` tidak tersedia, test tertentu akan otomatis `skipped`.

## Struktur Penting

```text
app/
  Console/Commands/
    ImportLegacyEcgCsvCommand.php
    MqttListenCommand.php
  Http/Controllers/
  Models/

database/
  migrations/
  seeders/
    DatabaseSeeder.php
    LegacyEcgCsvSeeder.php
    data/
      anarianti_1_rs.csv
      3w.csv

resources/views/
  dashboard/
  patients/
  recordings/
  monitoring/
  devices/
  users/
  layouts/

routes/
  web.php
```

## Setup Dari Nol

1. Clone atau pindahkan folder project ke server/lokal.

2. Masuk ke folder project:

```bash
cd Ekg-project
```

3. Install dependency PHP:

```bash
composer install
```

4. Salin file environment:

```bash
cp .env.example .env
```

Di Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

5. Generate application key:

```bash
php artisan key:generate
```

6. Buat database MySQL lokal.

Contoh nama database:

```sql
CREATE DATABASE ekg_af_local;
```

7. Sesuaikan `.env`:

```env
APP_NAME="EKG Monitoring"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ekg_af_local
DB_USERNAME=root
DB_PASSWORD=
```

8. Jalankan migration dan seeder:

```bash
php artisan migrate:fresh --seed
```

Perintah ini akan:

- Membuat tabel Laravel.
- Membuat tabel domain EKG.
- Membuat 3 puskesmas.
- Membuat user login.
- Membuat device EKG.
- Import CSV EKG dari:
  - `database/seeders/data/anarianti_1_rs.csv`
  - `database/seeders/data/3w.csv`

9. Jalankan server:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

10. Buka aplikasi:

```text
http://127.0.0.1:8000
```

## Akun Login Default

Password semua akun default:

```text
password
```

Daftar akun:

```text
superadmin@ekg.local
admin1@ekg.local
admin2@ekg.local
admin3@ekg.local
```

Role:

- `superadmin@ekg.local`: akses semua puskesmas.
- `admin1@ekg.local`: akses Puskesmas 1.
- `admin2@ekg.local`: akses Puskesmas 2.
- `admin3@ekg.local`: akses Puskesmas 3.

## Data Seeder EKG

Seeder EKG berada di:

```text
database/seeders/LegacyEcgCsvSeeder.php
```

File CSV berada di:

```text
database/seeders/data/anarianti_1_rs.csv
database/seeders/data/3w.csv
```

Data pasien hasil seed:

```text
Anarianti | 43 | Perempuan
Wahyu Saputra | 51 | Laki-laki
```

Seeder akan menyimpan:

- Data pasien.
- Sesi rekaman.
- Raw signal.
- Filtered signal.
- R-peaks.
- BPM.
- RR interval.
- SDNN sederhana dari RR interval.
- Prediction sementara `PENDING_MODEL`.

## Import CSV Manual

Selain lewat seeder, data CSV bisa diimport manual:

```bash
php artisan ekg:import-legacy-csv database/seeders/data/anarianti_1_rs.csv database/seeders/data/3w.csv --replace --sample-rate=360
```

Opsi penting:

```text
--replace
```

Menghapus data pasien dan sesi EKG lokal sebelum import ulang. User, puskesmas, dan device tetap dipertahankan.

## Menjalankan Seeder Saja

Jika database sudah ada dan hanya ingin isi ulang data:

```bash
php artisan db:seed --force
```

Jika hanya ingin menjalankan seeder CSV EKG:

```bash
php artisan db:seed --class=LegacyEcgCsvSeeder --force
```

## MQTT

Konfigurasi MQTT berada di `.env`:

```env
MQTT_HOST=160.187.144.147
MQTT_PORT=1883
MQTT_CLIENT_ID=ekg-laravel-subscriber
MQTT_AUTO_CREATE_PATIENT=true
MQTT_KEEPALIVE=60
MQTT_CONNECT_TIMEOUT=10
MQTT_RECONNECT_ATTEMPTS=0
MQTT_RECONNECT_DELAY=3
```

Menjalankan listener MQTT:

```bash
php artisan mqtt:listen
```

Topic yang digunakan mengikuti konfigurasi device:

```text
building/subjek
building/tspt
building/bpm
building/RR
building/rrlokal
building/hrr
building/rawdata
```

## Model Deep Learning EKG

Konfigurasi model berada di `.env`:

```env
EKG_PYTHON_BIN=python
DL_MODEL_ENABLED=true
DL_MODEL_DIR=../model/DeployModelEks3
DL_PREDICT_SCRIPT=scripts/predict_dl_ecg.py
DL_PREDICTION_TIMEOUT=180
EKG_SAMPLE_RATE=250
```

Model deep learning menggunakan raw signal EKG langsung melalui `pipeline.py`, sehingga prediksi tidak memakai fitur BPM/RR/SDNN/RMSSD sebagai input model. Fitur klinis tetap disimpan untuk grafik dan ringkasan.

Dependency Python:

```bash
python -m pip install -r requirements-dl.txt
```

Rekomendasi environment model:

- Python 3.10, 3.11, atau 3.12.
- `torch`, `numpy`, `scipy`, dan `neurokit2` terpasang di Python yang sama dengan `EKG_PYTHON_BIN`.
- Folder `DL_MODEL_DIR` berisi `model.py`, `pipeline.py`, dan `best_dl_modelExp3LSTMTuned.pth`.

Jika model atau dependency belum tersedia, aplikasi akan menampilkan prediksi sebagai:

```text
PENDING_MODEL
```

## Grafik EKG

Grafik EKG saat ini dibuat menyerupai ECG viewer lama:

- Raw signal: abu-abu.
- Filtered signal: biru.
- R-peaks: marker segitiga merah.
- Axis X: waktu dalam detik.
- Axis Y: amplitude.
- Mendukung horizontal scroll untuk sinyal panjang.

Grafik digunakan di:

- Dashboard.
- Detail pasien.
- Detail sesi rekaman.

## Command Umum

Membersihkan cache:

```bash
php artisan optimize:clear
```

Cache view:

```bash
php artisan view:cache
```

Menjalankan test:

```bash
php artisan test
```

Build asset frontend:

```bash
npm install
npm run build
```

Untuk development Vite:

```bash
npm run dev
```

## Setup Cepat Untuk Pindah Komputer

Urutan paling praktis:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Di Windows PowerShell:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Pastikan database di `.env` sudah dibuat terlebih dahulu.

## Catatan Penting

- Database VPS tidak perlu disentuh untuk development lokal.
- Data CSV contoh sudah ikut repo, sehingga seeder bisa jalan di tempat baru.
- Jangan jalankan `migrate:fresh --seed` di VPS produksi tanpa backup, karena command tersebut menghapus ulang tabel.
- Untuk produksi, gunakan migration biasa:

```bash
php artisan migrate --force
```

Lalu seed hanya jika memang diperlukan:

```bash
php artisan db:seed --force
```
