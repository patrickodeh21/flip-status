# HK Checklist - Getting Started

This repository contains the HK Checklist application, ready for local testing and deployment.

## Prerequisites

- **PHP 8.2+**
- **Composer**
- **Node.js & NPM**
- **SQLite** (for local testing)

## Quick Start (Local Development)

1.  **Clone the project** to your local machine.
2.  **Environment Setup**:
    - The `.env` file is pre-configured for local testing with SQLite.
    - If you need to regenerate the application key, run: `php artisan key:generate`.
3.  **Install Dependencies**:
    ```bash
    composer install
    npm install
    ```
4.  **Run the Project**:
    You can use the provided batch script for a quick start:
    - Double-click `run_project.bat` to start both the Laravel backend and Vite frontend.
    - Alternatively, run these in separate terminals:
      - Terminal 1: `php artisan serve`
      - Terminal 2: `npm run dev`
5.  **Access the Application**:
    Open [http://localhost:8000](http://localhost:8000) in your browser.

## Deployment

Refer to [DEPLOY.md](DEPLOY.md) for detailed instructions on deploying to services like Render.com.

## Troubleshooting

- **Broken Images**: Ensure the storage symlink is created by running `php artisan storage:link` or using the provided `fix-storage-symlink.bat` (run as administrator).
- **Database Issues**: If the database is missing or corrupted, run `php artisan migrate --seed` to initialize a fresh SQLite database.

---
© 2026 HK Checklist Team
