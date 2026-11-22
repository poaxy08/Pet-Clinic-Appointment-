# 🐾 PetCare — Pet Clinic Appointment System (PHP + MongoDB)

Website with two roles — **User (pet owner)** and **Admin** — featuring pet registration, appointment booking
with live slot locking, appointment management, medical records, veterinarians, services, billing in
Philippine pesos (₱), reminders/notifications, feedback & ratings, and owner profiles.

---------------------------------------------------------------------

## STEP-BY-STEP SETUP

### Step 1 — Install PHP 8.1 or newer
- Windows: install **XAMPP** (https://www.apachefriends.org) or PHP from https://windows.php.net
- macOS: `brew install php`
- Ubuntu/Debian: `sudo apt install php php-cli php-dev php-pear`
Check: `php -v`

### Step 2 — Enable the PHP MongoDB extension
- **Windows/XAMPP:** download `php_mongodb.dll` matching your PHP version from
  https://pecl.php.net/package/mongodb (choose the Thread Safe/Non-Thread Safe build that matches `php -i`),
  copy it into the `ext` folder, then add `extension=mongodb` to `php.ini` and restart.
- **macOS/Linux:** `sudo pecl install mongodb`, then add `extension=mongodb.so` to `php.ini`
  (Ubuntu: `sudo apt install php-mongodb` is also available).
Check: `php -m | grep mongodb` must print `mongodb`.

### Step 3 — Install Composer packages
Install Composer (https://getcomposer.org), then in this project folder run:
```
composer install
```

### Step 4 — Install and start MongoDB
1. Download **MongoDB Community Server**: https://www.mongodb.com/try/download/community
   (optional: MongoDB Compass, a GUI to view your data).
2. Start it:
   - Windows: installs as a service and starts automatically (check Services → *MongoDB Server*).
   - macOS: `brew services start mongodb-community`
   - Ubuntu: `sudo systemctl start mongod`
3. Verify it runs on the default address `mongodb://127.0.0.1:27017`
   (in Compass click **Connect** with that address).
No need to create the database or tables manually — the system creates the `petclinic` database,
collections and indexes automatically.

*(Prefer the cloud? Create a free cluster on MongoDB Atlas and put its connection string in `MONGO_URI` below.)*

### Step 5 — Configure
Copy `.env.example` to `.env` and edit if needed:
```
MONGO_URI=mongodb://127.0.0.1:27017
MONGO_DB=petclinic
APP_BASE=
CLINIC_NAME=PetCare Veterinary Clinic
```

### Step 6 — Create the admin account and sample data (run once)
```
php seed.php
```
or with your own credentials:
```
php seed.php "you@email.com" "YourStrongPassword" "Your Name"
```
Default admin if you pass nothing: **admin@petcare.com / Admin@12345** — change it right after signing in.

### Step 7 — Run the website
```
php -S localhost:8000 -t public
```
Open **http://localhost:8000**

(XAMPP alternative: put the folder in `htdocs`, set `APP_BASE=/petcare/public` in `.env`, and open
`http://localhost/petcare/public`.)

---------------------------------------------------------------------

## HOW TO ACCESS THE ADMIN
1. Run `php seed.php` once (Step 6) — this is the only way an admin account is created.
2. Go to **http://localhost:8000/login.php** (the same sign-in page as users).
3. Sign in with the admin email/password. You are sent automatically to the **Admin Console**
   (`/admin/dashboard.php`). Public sign-up always creates normal *user* accounts only — nobody can
   register themselves as admin.
4. Change the password in **Admin → My Account**.

## HOW USERS USE IT
Create account (`/register.php`) → register a pet → Book Appointment → get confirmation, reminders,
view records & invoices (₱) → leave feedback.

---------------------------------------------------------------------

## "NOT AVAILABLE" SLOT LOCKING (same date & time conflicts)
- When a user **taps a time**, it is reserved for them for **5 minutes**. Anyone else viewing that
  vet/date sees it greyed out and marked **Not available**; the list refreshes every 5 seconds.
- If someone else clicks it anyway, they get the message: *"Not available — this date and time was
  already taken by another client…"*.
- Final protection: a **unique MongoDB index** on the slot, so even two people pressing *Confirm* in
  the same millisecond can only produce one booking — the other gets "Not available".
- Cancelled or declined appointments free the slot immediately.
- `SLOT_MODE=vet` (default) = one booking per vet per time; `SLOT_MODE=clinic` = one booking per time for the whole clinic.

## REMINDERS
Reminders for visits in the next 24 hours are created automatically while people use the site.
For reliable delivery even when nobody is online, schedule `php cron/send_reminders.php` every 15 minutes
(cron on Linux/macOS, Task Scheduler on Windows). Reminders are in-app notifications (bell icon); there is
no SMS/email sending built in.

## Notes
- Payments are **recorded** by the admin (cash, GCash, Maya, card, bank transfer); no online payment gateway.
- Production: use HTTPS, set a strong admin password, keep `.env` private, serve only the `public` folder.

## Troubleshooting
| Message | Fix |
|---|---|
| "PHP MongoDB extension is not enabled" | Step 2 |
| "Composer packages are not installed" | Step 3 |
| "Cannot connect to MongoDB" | Start MongoDB (Step 4), check `MONGO_URI` |
| Pages 404 / CSS missing in XAMPP | Set `APP_BASE` (Step 5) |
| Need details | Set `APP_DEBUG=1` in `.env` |
