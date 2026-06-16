# Client Deployment Guide

This repository contains the updated source code for the "HK Checklist" application.

**Version:** 1.0 (Feb 9 2026)
**Fixes Included:**
1.  **MySQL Database Compatibility:** Supports importing the `dbase.sql` file.
2.  **Login Page:** Optimized environment configuration.
3.  **Features:** Room Skipping, Admin Media Upload, Housekeeper Timestamped Photos.

## How to Deploy on Your Live Server
1.  **Download Source:** Clone this repository or download the ZIP.
2.  **Database:**
    *   Create a MySQL database on your server.
    *   Import the included `dbase.sql` file:
        ```bash
        mysql -u user -p database_name < dbase.sql
        ```
3.  **Environment:**
    *   Copy `.env.example` to `.env`.
    *   Set `DB_CONNECTION=mysql` and your database credentials.
    *   Set `GOOGLE_GEOCODING_API_KEY`.
4.  **Install:**
    ```bash
    composer install --no-dev
    php artisan key:generate
    npm install && npm run build
    ```

## Notes on Performance
*   The previous test server (Render Free Tier) sleeps after inactivity, causing a 5-minute wake-up delay.
*   **Your paid/live server will NOT have this issue** and will be fast immediately.
