# Inventory Stock Management — Railway Ready

## Local
From the project root:
```powershell
C:\xampp\php\php.exe -S localhost:8000 -t public public/router.php
```
Open `http://localhost:8000/`.

## Railway
This build includes a PHP 8.2 Dockerfile, `/health` endpoint, Railway config, and environment-based MySQL configuration.

1. Keep the Railway **MySQL** service.
2. Create a separate Railway web service for this code (do not deploy the code into the MySQL service).
3. Connect this GitHub repository or run `railway up` after selecting the web service.
4. In the web service variables, add these Railway references:
   - `MYSQLHOST=${{MySQL.MYSQLHOST}}`
   - `MYSQLPORT=${{MySQL.MYSQLPORT}}`
   - `MYSQLDATABASE=${{MySQL.MYSQLDATABASE}}`
   - `MYSQLUSER=${{MySQL.MYSQLUSER}}`
   - `MYSQLPASSWORD=${{MySQL.MYSQLPASSWORD}}`
5. Deploy. Railway will use the included Dockerfile and the service listens on its `$PORT`.
6. Generate a public domain for the web service.

## UI feedback
Successful create/update/delete/status/stock/settings/profile operations now show clear success toasts such as:
- Saved successfully
- Updated successfully
- Deleted successfully
- Stock updated successfully
- Settings saved successfully
- Profile updated successfully

Database errors are shown as error toasts instead of silently failing.
