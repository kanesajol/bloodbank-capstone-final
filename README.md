# CRH Blood Bank

Staff system for recording blood donations, blood consumption, and monthly reports for Caraga hospitals and collection sites (Dinagat Islands, Surigao, and Agusan).

Two clients share one Laravel backend:

- **Web** — Blade pages for staff on a desktop browser.
- **Mobile** — Expo / React Native app in this same folder, signed in with a Sanctum token.

## What staff can do

| Area | What it records |
| --- | --- |
| Blood collection | Donor profile, screening (passed or deferred), and each donation visit |
| Blood consumption | Patient, blood component, units, and address |
| Reports | Monthly, quarterly, and yearly donation totals, demographics, and consumption |
| Staff | Accounts, shifts, profile, and a duty log written on login and logout |

A donor is one person. The same name is not stored twice. A later visit at another site is attached to the existing profile and does not replace the home address.

Donor age on registration is 16–70. Blood types are `O+`, `O-`, `A+`, `A-`, `B+`, `B-`, `AB+`, and `AB-`.

## Requirements

- PHP 8.3 or newer, with `sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, and `fileinfo`
- Composer
- Node.js 20 or newer (web assets and the mobile app)
- For the phone app: [Expo Go](https://expo.dev/go), or a browser for the Expo web preview

The default database is SQLite (`database/database.sqlite`). MySQL is optional; see [Setup](#setup).

## Setup

From `c:\wamp64\www\BLOODBANK`:

```powershell
composer install
copy .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database\database.sqlite | Out-Null
php artisan migrate --seed
npm install
```

`php artisan migrate --seed` creates the tables and five demo staff accounts. Every demo password is `password`.

| Username | Role |
| --- | --- |
| `kane_sajol` | Medical Technologist |
| `john_doe` | Blood Collection Nurse |
| `jumel_alipao` | Emergency Screening Officer |
| `sarah_ahmed` | Blood Bank Administrator |
| `maria_santos` | Phlebotomist / Screener |

Change these passwords before any shared or production use. New staff can also sign up at `/signup`.

### MySQL on WAMP

In `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bloodbank
DB_USERNAME=root
DB_PASSWORD=
```

Create the `bloodbank` database in phpMyAdmin, then run `php artisan migrate --seed` again.

### Production site (AlwaysData)

The production site is [bloodbank.alwaysdata.net](https://bloodbank.alwaysdata.net).
Deploy updates through AlwaysData; this repository does not deploy automatically.

## Run the web app

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) and sign in. After login the app opens Blood Collection.

On WAMP you can also point the site at the `public` folder (`http://localhost/BLOODBANK/public`). Set `APP_URL` in `.env` to that address. The mobile app still expects the API on port 8000 unless you change `src/config.js`.

Health check: `GET /up`.

## Run the mobile app

Start the API so the phone can reach it, then start Expo:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
npx expo start
```

Press `w` for the browser preview, or scan the QR code with Expo Go. The API base URL in `src/config.js` defaults to `https://bloodbank.alwaysdata.net/api`. To use a local API instead, set `EXPO_PUBLIC_API_URL` before starting Expo, for example `http://192.168.1.20:8000/api` on a physical phone.

`npm run mobile:dev` runs `start-mobile.ps1` if you use that script locally.

The `mobile/` folder is an older copy. The app that `package.json` starts is the one at the project root (`App.js`, `index.js`, `src/`).

## Tests

```powershell
php artisan test
```

Tests use an in-memory SQLite database and do not touch `database/database.sqlite`.

## Project layout

```
app/Http/Controllers/        Web controllers
app/Http/Controllers/Api/    Mobile API
app/Models/                  Donor, Donation, ConsumptionRecord, User, StaffShiftRecord
app/Services/ReportService.php
app/Support/BloodBank.php    Blood types, roles, shifts, address helpers
routes/web.php               Browser routes
routes/api.php               /api routes (Sanctum)
resources/views/             Blade pages
public/css                   Web styles
src/                         Expo screens, navigation, API client
database/migrations
database/seeders
```

## More documentation

- [Staff guide](docs/staff-guide.md) — how to use collection, consumption, reports, and staff tools
- [API](docs/api.md) — mobile endpoints

## Roles

Roles are labels for the duty log. They do not change which pages a signed-in user can open.

- Medical Technologist
- Blood Collection Nurse
- Emergency Screening Officer
- Phlebotomist / Screener
- Blood Bank Administrator

Shifts are Morning (6:00 AM–2:00 PM), Afternoon (2:00 PM–10:00 PM), and Midnight (10:00 PM–6:00 AM). Login opens a shift record; logout closes it.
