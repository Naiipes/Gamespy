# Gamespy

Gamespy is a Laravel web app for browsing discounted PC games, searching deals, saving games to a wishlist, and sending email notifications when prices drop or when they hit a desired price set by the user.

## Requirements

- [Laravel Herd](https://herd.laravel.com/)
- PHP 8.4 or newer
- [Composer](https://getcomposer.org/)
- MySQL or another database supported by Laravel
- A [Resend](https://resend.com/) account and API key for email notifications

## Local Setup

### 1. Clone the project

```bash
git clone https://github.com/Naiipes/Gamespy.git
cd Gamespy
```

### 2. Add the project to Laravel Herd

Open Laravel Herd and make sure this project folder is being served by Herd.

### 3. Install Composer dependencies

```bash
composer install
```

### 4. Create the environment file

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Connect your database in `.env`

Open `.env` and update the database settings to match your local database.

Example MySQL configuration:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gamespy
DB_USERNAME=root
DB_PASSWORD=
```

If you are using a different database, update the values accordingly.

### 6. Configure Resend in `.env`

Create a Resend account, generate an API key, and place it in your `.env` file:

```env
MAIL_MAILER=resend
RESEND_API_KEY=your_resend_api_key
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME="Gamespy"
```

Important notes:

- `onboarding@resend.dev` is fine for local testing.
- If you want to send emails to addresses other than the one tied to your Resend account, verify your own domain in Resend and replace `MAIL_FROM_ADDRESS` with an address from that domain.
- Email notifications will not work without a valid Resend API key.

### 7. Run the migrations

```bash
php artisan migrate
```

## Running the App

If you are using Herd, PHP serving is handled for you. Open the local Herd URL for the project in your browser after the setup steps above.

## Building the Game Cache

The homepage depends on cached game discovery data. After setup, build the cache manually:

```bash
php artisan games:build-cache
```

This fills the cached homepage and recommendation data used by the app.

## Checking Game Prices

To manually check wishlist prices and create notifications:

```bash
php artisan prices:check
```

If email notifications are enabled on a wishlist item and your Resend configuration is valid, this command can also send email alerts.

## Notes For Local Use

- This project is intended to be run locally through Laravel Herd.
- The homepage game cards will not appear until `php artisan games:build-cache` has been run at least once.
- Price notifications and email alerts will not be created until `php artisan prices:check` is run.
- For this local setup, those commands can be run manually when needed.
