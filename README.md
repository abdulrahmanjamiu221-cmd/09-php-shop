# Good Goods PHP Shop

## Setup

1. Start Apache and MySQL in XAMPP.
2. Import [`database.sql`](./database.sql) with phpMyAdmin. To import from PowerShell:

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\swd\09-php-shop\database.sql
   ```

   The export creates `swd_shop` and the categories, products, orders, order_items, and admin_users tables. If the shop tables already exist, importing adds the admin table without deleting existing data.
3. Seed the catalog from PowerShell:

   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\swd\09-php-shop\seed-catalog.php
   ```

   The CLI-only seeder can be run again safely to refresh its 15 sample products.
4. Create an administrator account. Enter credentials into process environment variables (the password is requested as a secure prompt) and run the CLI-only account tool:

   ```powershell
   $env:SHOP_ADMIN_USERNAME = Read-Host "Admin username"
   $securePassword = Read-Host "Admin password (at least 12 characters)" -AsSecureString
   $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
   try {
       $env:SHOP_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
       & C:\xampp\php\php.exe C:\xampp\htdocs\swd\09-php-shop\admin\create-admin.php
   } finally {
       Remove-Item Env:\SHOP_ADMIN_PASSWORD -ErrorAction SilentlyContinue
       Remove-Item Env:\SHOP_ADMIN_USERNAME -ErrorAction SilentlyContinue
       [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
   }
   ```

   The password is stored as a `password_hash()` hash. To change it, run the same command with the existing username; the account is updated.
5. Open <http://localhost/swd/09-php-shop/>. The admin area is at <http://localhost/swd/09-php-shop/admin/login.php>.

By default, the app connects to MySQL at `127.0.0.1` as `root` with an empty password. Override the connection with `SHOP_DB_HOST`, `SHOP_DB_NAME`, `SHOP_DB_USER`, and `SHOP_DB_PASSWORD` environment variables if needed.

## Database export

`database.sql` is the importable schema export. To create a dump including current catalog and order data:

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root swd_shop > C:\xampp\htdocs\swd\09-php-shop\database.sql
```

Keep database dumps private if they contain customer information.

## Orders and security

The bag stores only product IDs and quantities in the session. Prices are loaded from MySQL each time; checkout re-reads and locks products before calculating totals. A hidden price field is user-editable, and session prices can become stale, so neither is trusted for payment/order totals.

`SELECT ... FOR UPDATE` locks product rows until the order transaction commits or rolls back. If two customers try to buy the last item, the second transaction waits and then sees the reduced stock. The transaction keeps the order, order lines, and stock decrements atomic. Cancelling an order restores its stock inside a transaction, and only valid status transitions are allowed.

Customer order lookup requires both the order reference and checkout email. Checkout creates pending orders; this sample shop does not collect payment.
