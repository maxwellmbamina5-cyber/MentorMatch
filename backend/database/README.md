# PostgreSQL setup

MentorMatch uses PostgreSQL through PHP PDO's `pdo_pgsql` driver. Install
PostgreSQL and enable `pdo_pgsql` in the PHP configuration used by Apache or
your PHP server. With XAMPP, uncomment or add `extension=pdo_pgsql` in
`php.ini`, then restart Apache. The `php_pdo_pgsql.dll` extension must be
available in PHP's extension directory.

Create the application database and load the system schema from the project
root:

```powershell
createdb -U postgres mentormatch
psql -U postgres -d mentormatch -f backend\database\schema.sql
```

For a quick local preview from the project root, start PHP's built-in server
after PostgreSQL is running and `pdo_pgsql` is enabled:

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '5432'
$env:APP_BASE_PATH = '/'
php -S 127.0.0.1:8000 -t frontend frontend\router.php
```

The schema file creates all application tables, indexes, constraints, and
timestamp triggers. It expects the database to exist first.

The default connection is `localhost:5432`, database `mentormatch`, user
`postgres`. The PHP configuration defaults to an empty password, but a local
PostgreSQL installation may require the password set during installation. Set
`DB_PASSWORD` in the environment of the PHP server process when required; do
not commit database passwords to the repository. The connection can be
overridden with these environment variables:

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASSWORD
```

Create the first administrator password hash with PHP:

```powershell
php -r "echo password_hash('replace-with-a-strong-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Connect to the database with `psql -U postgres -d mentormatch`, then insert an
administrator after replacing the example values and password hash:

```sql
INSERT INTO users (name, email, phone, password, role, status)
VALUES ('Administrator', 'admin@example.com', '+260000000000',
        '<paste-password_hash-output-here>', 'admin', 'active');
```

Never store a plain-text password in the database.

## Existing databases

The SQL scripts in `backend/database/migrations` are PostgreSQL scripts for databases that
already contain the base MentorMatch tables. Apply them from the project root
when their tables are not already present:

```powershell
psql -U postgres -d mentormatch -f backend\database\migrations\20261028_add_subscriptions.sql
psql -U postgres -d mentormatch -f backend\database\migrations\20261028_add_mobile_money_setting.sql
```

The schema and migrations create an empty PostgreSQL database structure; they
do not transfer data from an existing MySQL/MariaDB database.

The admin backup page uses `pg_dump`. Ensure it is on the PHP server's `PATH`,
or set `PG_DUMP_PATH` to its full executable path.

## Deploying on Render

The project root includes a `render.yaml` Blueprint and Docker configuration
for deploying the PHP app with Render PostgreSQL. The Blueprint uses Frankfurt
for both services and configures a persistent disk for profile photos and
certificates. The web service and database plans, plus the persistent disk,
are paid Render resources; review their current pricing before creating them.

1. Push the project to a Git provider supported by Render, then create a new
   Blueprint instance from the repository and review the resources in
   `render.yaml` before confirming.
2. After Render creates the database, initialize its schema once from a
   machine with PostgreSQL's `psql` client. Use the database's **external**
   connection URL from the Render dashboard:

   ```powershell
   $env:DATABASE_URL = 'paste-the-external-render-postgres-url-here'
   psql $env:DATABASE_URL -f backend\database\schema.sql
   ```

   Do not commit the connection URL or share it publicly. Keep using Render's
   internal database URL for the deployed app; the Blueprint wires this into
   `DATABASE_URL` automatically.
3. Set up the first administrator using the instructions above. If you have an
   existing database, migrate and verify its data separately before pointing
   live traffic at the new deployment; the schema file only creates tables.
4. Configure `APP_URL` in the Render service settings if you use a custom
   domain. Configure `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`,
   `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, and `SMTP_FROM_NAME` before setting
   `SEND_EMAILS` to `true`. Add `ZYNLEPAY_MERCHANT_ID`, `ZYNLEPAY_API_ID`,
   `ZYNLEPAY_API_KEY`, and `ZYNLEPAY_ENVIRONMENT` only when payment credentials
   are ready; the integration defaults to sandbox.

The Render service starts with debug output and email sending disabled. Uploads
are stored on the configured persistent disk. The dashboard assistant currently
expects Ollama on the same machine, so it will not work on Render without
replacing its local AI endpoint. The app's scheduled scripts in `backend/cron/` also
need separate Render Cron Jobs if you want automatic session completion and
expiration.

## Local dashboard AI

The mentor and mentee dashboard assistants use Ollama locally. Install Ollama,
then download the default model:

```powershell
winget install -e --id Ollama.Ollama
ollama pull qwen2.5:3b
```

Ollama should be running locally at `http://127.0.0.1:11434`. The assistants
send chat requests only to that local service. The default model is
`qwen2.5:3b`; set `OLLAMA_MODEL` before starting PHP to use a different model.
The first request can take longer while the model loads.

## Monthly subscriptions

Mentor subscriptions cost **ZMW 250/month** and mentee subscriptions cost
**ZMW 200/month**. New mentor and mentee accounts remain pending until an
administrator approves them from **Admin → Users**. Approval activates the
account but does not activate a subscription. Users receive a stable
`MM-00000042`-style payment reference after registration and can request payment
confirmation after account activation. Administrators confirm payments from
**Admin → Subscriptions**. Confirmation starts a one-calendar-month access
period.

Search, booking, mentor visibility, and AI are enabled according to the user's
role and active subscription, and stop automatically when the subscription
expires. Confirmed subscription amounts count as user subscription spending
and admin subscription revenue; pending or rejected requests do not count.
Subscription fees are paid outside the app and do not affect wallet balances
or mentor session earnings.

## Mentor availability and session booking

Mentors set weekly availability ranges from **Mentor → Availability**.
Mentees searching for subscribed mentors can see those ranges and request
sessions within them. Booking choices are filtered by the selected duration and
already pending or confirmed sessions; the server checks the schedule and
overlaps again before accepting a request. Mentors cannot be double-booked for
overlapping times.
