# Deployment Guide

## 1. Prerequisites
- **Render Account** (for deployment)
- **MySQL Database** (External provider like Aiven/PlanetScale/DigitalOcean, or local Docker if just testing)
- **Google Maps API Key**

## 2. Render.com Deployment
The project is configured for Render (`render.yaml` & `Dockerfile`).

1.  **Connect Repo:** Create a "Web Service" on Render and connect this repository.
2.  **Runtime:** Choose **Docker**.
3.  **Environment Variables:**
    *   `APP_KEY`: Generate with `php artisan key:generate --show` locally.
    *   `APP_DEBUG`: `false`
    *   `APP_URL`: Your Render URL (e.g., `https://myapp.onrender.com`).
    *   `DB_CONNECTION`: `mysql`
    *   `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: Your MySQL Details.
    *   `GOOGLE_GEOCODING_API_KEY`: Your API Key.

## 3. Database (MySQL)
The `dbase.sql` file contains your data.
*   **Import:** Use a tool (Workbench, TablePlus, CLI) to import `dbase.sql` into your external MySQL database *before* deploying, or allowing the app to run migrations (the `Start Command` runs `migrate --force`).
*   **Note:** The app attempts to seed data on start. If `dbase.sql` is imported, you might not need seeding.

## 4. Troubleshooting
*   **500 Error:** Check Render logs. Likely missing Environment Variables or DB connection failure.
*   **404 on Assets:** Ensure `npm run build` finished successfully in the build logs.

## 5. Recent Updates (Feb 2026)
- **Geocoding:** `GEOCODING_PROVIDER=google` added to `.env`. Ensure `GOOGLE_GEOCODING_API_KEY` is set.
- **Housekeepers:** Assigned to Owners via `owner_id`.
- **Calendar:** Added iCal URL field for properties.
- **Sessions:** Housekeepers now see upcoming sessions (Today + Future).

## Quick Start (Local)
1.  Run migrations: `php artisan migrate`
2.  Start server: `run_project.bat`

