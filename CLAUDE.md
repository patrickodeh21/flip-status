# FlipStatus Contest — Project Guide

## Project Overview
Laravel-based checklist/task management system for cleaning companies ("FlipStatus" / "HK Checklist"). Cleaners (housekeepers) complete checklists per cleaning session; owners/admins manage properties, rooms, tasks, and assignments.

**Contest context**: 10 tasks total, ~$50 budget. Tasks 1-3 must be completed first as a qualifying entry, then 2 extra days for tasks 4-10 (pending price renegotiation given scope).

**Critical constraint**: Keep existing project structure intact. No third-party services/APIs beyond what's already integrated (Google Geocoding). Prefer additive changes (new files, new methods) over rewriting existing files.

---

## Environment Setup (Docker, local dev)

### Stack
- PHP 8.3, Laravel, SQLite (local dev) / MySQL (production)
- Tailwind CSS (darkMode: 'class'), Alpine.js, Vite
- Roles via spatie/permission-style: `admin`, `owner`, `company`, `housekeeper`

### Docker setup
`docker-compose.yml` (already configured):
```yaml
services:
  app:
    build: .
    ports:
      - "8000:8000"
    volumes:
      - .:/app
      - /app/vendor
      - /app/node_modules
    env_file:
      - .env
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
    command: sh -c "php artisan migrate --force && php artisan db:seed --force && php -S 0.0.0.0:8000 -t public public/router.php"
```

**IMPORTANT**: The command uses `public/router.php` (a custom router script we created), NOT `public/index.php` directly. PHP's built-in dev server (`php -S`) requires a router script that falls through to `index.php` for non-static-file requests, otherwise BOTH custom routes (like `/file/{type}/{filename}`) AND static asset routing breaks in different ways.

`public/router.php` content (already created):
```php
<?php
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

require_once __DIR__ . '/index.php';
```

### Key `.env` settings for local dev
```
APP_ENV=local
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
FILESYSTEM_DISK=public
SESSION_DOMAIN=null
```
Run `php artisan key:generate` if `APP_KEY` is empty.

### `public/storage` symlink
Must be a real symlink (`php artisan storage:link`), not a copied directory. If `file public/storage` says "directory" instead of "symbolic link", delete and recreate:
```bash
sudo docker compose exec app rm -rf public/storage
sudo docker compose exec app php artisan storage:link
```

### Common commands
```bash
sudo docker compose build
sudo docker compose up -d
sudo docker compose down
sudo docker compose exec app <command>
sudo docker compose exec app php artisan tinker --execute="..."
sudo docker compose exec app npm run build   # IMPORTANT after adding new Tailwind classes
```

**Gotcha**: Tailwind/Vite assets are built once during image build. Any NEW Tailwind utility classes added to Blade files after that won't be in the compiled CSS until you run `npm run build` again inside the container. If new UI elements render with missing/wrong styling (e.g., a modal showing white instead of dark), this is the first thing to check.

### Bash heredoc gotcha
Long heredocs (`cat > file << 'EOF' ... EOF`) sometimes get truncated mid-paste in this WSL terminal. ALWAYS verify file content after writing with `wc -l file` and `tail -5 file` (check it ends properly, e.g. with a closing `}` or `EOF` tag). If truncated, the actual file on disk is often still complete — `cat` the full file to confirm before assuming failure. If genuinely truncated, split into smaller `cat >` + `cat >>` chunks.

### Permission gotcha
Files created via `docker compose exec app php artisan make:...` are owned by `root` (container's user). To edit them from host, first:
```bash
sudo chown soarer:soarer <path>
```

### Demo login credentials
- Password for all seeded users: `password` (or `DEMO_USER_PASSWORD` env var)
- Find specific users via tinker:
```bash
sudo docker compose exec app php artisan tinker --execute="
\$session = \App\Models\CleaningSession::find(1);
\$user = \App\Models\User::find(\$session->housekeeper_id);
echo \$user->email;
"
```

### Key DB schema notes
- `cleaning_sessions` columns: `id, property_id, owner_id, housekeeper_id, scheduled_date, status, started_at, ended_at, gps_confirmed_at, start_latitude, start_longitude, scheduled_time, skipped_rooms, report_token, stage, checkout_id, sporadic_tasks`
- `tasks` columns: `id, name, is_default, type, instructions, phase, is_sporadic` — NO `property_id`/`room_id` columns. Tasks are linked via pivot tables (`room_task`, `property_tasks`, `property_room`).
- `task.type` enum: `room | inventory | verify | instructions`
- `task.phase` enum (property-level tasks): `pre_cleaning | during_cleaning | post_cleaning`
- Tasks with `type === 'instructions'` are filtered OUT of "checkable" tasks but still render in the room view.

---

## TASK 1 — Instructions Popup Modal ✅ COMPLETE

### What was built
A floating "Instructions" button (fixed bottom-right, `bg-amber-500`, always visible regardless of scroll) on the session checklist page (`resources/views/sessions/show.blade.php` — NOT `show-redesigned.blade.php`, which is unused/WIP). Clicking it opens a modal listing ALL tasks with `type === 'instructions'` across the whole session (pre-cleaning, during-cleaning, post-cleaning property tasks, and all rooms), grouped by section/room name.

### Implementation details
1. **`resources/views/sessions/show.blade.php`** — after the existing `@php $dataUrl = ...; $reportUrl = ...; $photoDeleteUrl = ...; @endphp` block (around line 34), added a new `@php` block that builds `$groupedInstructions`:
   - Loops `$preCleaningTasks`, `$duringCleaningTasks`, `$postCleaningTasks` (property-level, no pivot — use `$task->instructions`)
   - Loops `$rooms` and `$roomTasksByRoom[$room->id]` (room-level — use `$task->pivot->instructions ?? $task->instructions`)
   - Filters each by `where('type', 'instructions')` and non-empty instructions text
   - Groups into `$allInstructionItems->groupBy('section')` → `$groupedInstructions`

2. **FAB + Modal** — appended at the end of the file, AFTER the `checklistRenderer` x-data div closes but BEFORE `</x-app-layout>`. Wrapped in its own `x-data="{ instructionsModalOpen: false }"` scope (independent of the page's main Alpine state). Only renders `@if($groupedInstructions->isNotEmpty())`.

3. Existing inline "View Instructions" toggle per task (in `resources/views/components/checklist/task-item.blade.php`) is UNTOUCHED.

### Known pitfalls already solved
- The view actually rendered by `SessionController@show` (line ~543: `return view('sessions.show', [...])`) is `show.blade.php`, NOT `show-redesigned.blade.php`. Don't edit the wrong file.
- The navbar (`resources/views/components/navbar.blade.php`) is `sticky top-0` but is a single global component invoked once in `app.blade.php` — adding conditional content there is more invasive, hence the FAB approach instead.
- Pre-existing unrelated console error `noteSaving is not defined` in `task-item.blade.php`'s Alpine bindings — NOT caused by our changes, page renders fine despite it. Don't try to fix unless asked.

### Testing
Test instruction task (ID 154) was created via tinker and attached to Room 1 ("Living Room") for manual testing, then can be cleaned up:
```bash
sudo docker compose exec app php artisan tinker --execute="
\App\Models\Room::find(1)->tasks()->detach(154);
\App\Models\Task::find(154)->delete();
"
```
(Optional cleanup — doesn't block anything if left in place.)

---

## TASK 2 — Instructional Videos Resource ✅ COMPLETE

### Goal
Admin-managed video library. Each video can be assigned to multiple properties. Accessible via new "Resources" sidebar menu item (admin/owner/company). Videos assigned to a property should be viewable by cleaners working that property (exact display location TBD — likely similar FAB/modal pattern on session page, or a dedicated "Resources" page listing videos for the cleaner's assigned properties).

### Progress so far

**Database (DONE, migrated successfully):**
- `database/migrations/2026_06_12_112237_create_videos_table.php`:
  ```php
  Schema::create('videos', function (Blueprint $table) {
      $table->id();
      $table->string('title');
      $table->text('description')->nullable();
      $table->string('url')->nullable();
      $table->string('thumbnail')->nullable();
      $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
  });
  ```
- `database/migrations/2026_06_12_112238_create_property_video_table.php`:
  ```php
  Schema::create('property_video', function (Blueprint $table) {
      $table->id();
      $table->foreignId('property_id')->constrained()->cascadeOnDelete();
      $table->foreignId('video_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['property_id', 'video_id']);
  });
  ```
Both migrated successfully via `php artisan migrate`.

**Model (DONE):** `app/Models/Video.php` created — `fillable: ['title', 'description', 'url', 'thumbnail', 'uploaded_by']`, `belongsToMany(Property::class, 'property_video')` relation, `uploader()` belongsTo User, and `url`/`thumbnail` accessor Attributes that follow the EXACT same pattern as `TaskMedia::url()` / `Property::getPhotoUrlAttribute()` — i.e., if value starts with `http(s)://` return as-is, otherwise strip leading `storage/` and route through `url('file/' . $path)` (uses the `/file/{type}/{filename}` route we fixed in environment setup).

**Property model (DONE):** Added `videos(): BelongsToMany` relation to `app/Models/Property.php` (after `propertyTasks()`, line ~51):
```php
public function videos(): BelongsToMany
{
    return $this->belongsToMany(Video::class, 'property_video')->withTimestamps();
}
```

**Controller (DONE):** `app/Http/Controllers/VideoController.php` created with full CRUD:
- `index()` — list all videos with their assigned properties, role-gated to `admin|owner|company`
- `create()` — form, passes `$properties = Property::orderBy('name')->get()` for multi-select
- `store()` — validates `title` (required), `description` (nullable), `video_file` (nullable file, mimetypes mp4/quicktime/webm/x-msvideo, max 200MB), `video_url` (nullable url), `properties` (nullable array of property IDs). Requires either `video_file` or `video_url`. Stores file via `$request->file('video_file')->store('videos', 'public')` (same pattern as `task-media` in `TaskController`). Syncs properties via `$video->properties()->sync(...)`.
- `edit()` — passes video, properties list, and `$assignedPropertyIds` for pre-checking checkboxes
- `update()` — same validation, preserves existing URL if no new file/url given (`$video->getRawOriginal('url')`)
- `destroy()` — detaches properties, deletes video record (Note: does NOT delete the physical file from storage — acceptable for now, could be added later)

### Implementation completed

- Routes: `Route::resource('videos', ...)` added in `routes/web.php`, includes all resource routes (index, create, store, show, edit, update, destroy).
- Sidebar: Resources dropdown with "Instructional Videos" and "Upload Video" links added for `admin|owner|company` roles.
- Index (`resources/views/resources/videos/index.blade.php`): Table listing with video thumbnail, title, description, and properties column. Properties display logic: 0 = "No properties assigned", 1–5 = show names inline, 5+ = show "{count} Properties Assigned" link to the show page.
- Show (`resources/views/resources/videos/show.blade.php`): Video player, description, and all assigned properties displayed in a card grid layout (non-table). Supports both admin and housekeeper viewers (housekeepers are restricted to videos for their assigned properties).
- Create (`resources/views/resources/videos/create.blade.php`): Form with title, description, video file upload or URL, and property multi-select checkboxes.
- Edit (`resources/views/resources/videos/edit.blade.php`): Same as create but pre-filled, with current video preview and pre-checked property selections.
- Controller (`VideoController`): Full CRUD with role-based access, property sync, and housekeeper filtering.
- Housekeeper view: Read-only access to videos for their assigned properties via the same index and show views.

### Testing plan for Task 2
1. Run `php artisan route:list | grep video` to confirm routes registered.
2. Log in as admin/owner, navigate to Resources > Upload Video, create a test video (try both file upload and URL methods).
3. Confirm it appears in the index with assigned properties.
4. Edit it, change property assignments, confirm sync works.
5. Delete it, confirm removal.
6. If housekeeper view built: log in as housekeeper (e.g. `chet04@example.com` / `password`), confirm they see only videos for their assigned property/properties.
7. Run `npm run build` after all view changes (new Tailwind classes).

---

## TASK 3 — Make "Read Instructions" Button Stand Out ✅ COMPLETE

### What was done
Styled the instructions toggle button to stand out visually from the Note button. Applied changes to both files that render the button:

- **`resources/js/checklist-renderer.js`** (line ~1500) — the LIVE version used by the active checklist page. The button was restyled from a small plain text link into a bold amber badge/pill with an info-circle icon, padding, border, and shadow. The Note button below it remains a small gray/blue pill.
- **`resources/views/components/checklist/task-item.blade.php`** (line ~107) — the Blade component used by the summary partial and `show-redesigned.blade.php`, updated with the same amber styling for consistency.

Key visual changes applied:
- Bold text, more padding, full border, and background color (`bg-amber-100`, `text-amber-800`, `border-2 border-amber-300`, `shadow-sm`)
- Added info-circle icon before the label
- Kept existing position in its own row above the Note/Photo action footer
- No behavior changes — the toggle click handler is untouched

---

## STOP POINT

**After completing Task 3, STOP and report back.** Do not proceed to Tasks 4-10 without further instruction — the contest entry requires only Tasks 1-3, and remaining scope/pricing needs to be discussed with the client before continuing.

---

## TASKS 4-10 — Reference Notes for Later (DO NOT START YET)

### Task 4 — Group assignments list by day
File likely: `resources/views/sessions/index.blade.php` (housekeeper's "My Jobs" list) and/or `resources/views/sessions/manage/index.blade.php` (admin "Jobs" list).
Approach: In the controller, group sessions by `scheduled_date` using Carbon. For each group's date, compute label:
```php
$diffDays = now()->startOfDay()->diffInDays($session->scheduled_date, false); // signed
// 0 = Today, 1 = Tomorrow, -1 = Yesterday, ±2/±3 = "X days ago"/"in X days"
// beyond ±3: format as "Mon, Jan 5" (day-of-week + date, no bare date)
```
Use `Carbon::isToday()`, `isTomorrow()`, `isYesterday()`, and `diffInDays()` for the ±3 day logic. Beyond 3 days: `$date->format('D, M j')` (e.g. "Wed, Jun 17").

### Task 5 — Default jobs page date filter cleanup
File likely: `resources/views/sessions/manage/index.blade.php` + `ManageSessionController@index` (or `SessionController`).
Requirements:
- Remove any default date filter value (no pre-selected date range on page load).
- Default view: show "upcoming" jobs (today + future) prominently, but ALSO show past jobs.
- Ordering: today first, then PAST dates (most recent past first), then upcoming/future in a separate "Upcoming" section — NO future dates mixed into the main past-to-present list.
- Likely needs a query restructure: split into two collections (`$upcomingSessions` = today + future, `$pastSessions` = before today, ordered descending by date) and render in two sections.

### Task 6 — Property tasks page should match room tasks page UI
Files: `resources/views/properties/property-tasks/index.blade.php` (current, needs updating) vs `resources/views/rooms/tasks/index.blade.php` (reference/target style).
Approach: Compare both files' Blade structure (table columns, badges for task type/media indicators, action buttons). Port the room-tasks page's layout/components into the property-tasks page, keeping property-tasks' existing data/routes (don't change controller logic, just the view's presentation layer — column structure, type badges, media thumbnails if room-tasks shows them).

### Task 7 — Reorder drag handles too small on mobile
Find the drag-and-drop implementation:
```bash
grep -rln "sortable\|Sortable\|draggable" resources/views resources/js --include="*.blade.php" --include="*.js"
```
Likely uses SortableJS or Alpine's sort directive with a small icon (e.g., 6-dot grip icon) as the drag handle. Fix: increase the handle's clickable/touch area — add padding (`p-3` or larger), increase icon size (`w-6 h-6` minimum, consider `w-8 h-8` on mobile via responsive classes), and ensure `touch-action: none` CSS or `select-none` class is applied to prevent text selection during drag on touch devices. Also check if `cursor-grab`/`cursor-grabbing` classes exist for visual feedback.

### Task 8 — Property active/inactive status
Migration: add `is_active` boolean column to `properties` table, default `true`.
```php
$table->boolean('is_active')->default(true)->after('timezone');
```
Add to `Property::$fillable`. In `PropertyController@index`, default query: `Property::where('is_active', true)`. Add a toggle/filter (checkbox or link) "Show inactive properties" that includes `where('is_active', false)` or removes the filter entirely. Add an "Active/Inactive" toggle action (button) on each property row — similar to existing action-dropdown pattern — that does a quick PATCH/PUT to toggle `is_active`. Add a small status badge (reuse `<x-status-badge>` component or similar) showing "Inactive" on properties when `!is_active`.

### Task 9 — GPS override restrictions + admin remote-start + time-gating
Multiple parts:
- (a) **Restrict GPS override to admins**: Find current override mechanism — search `resources/views/sessions/partials/gps-script.blade.php` and `SessionController@start` (or wherever `sessions.start` route handler is) for any "skip GPS" / override logic. Wrap any cleaner-facing override UI in `@role('admin')` or remove entirely if cleaners shouldn't see it.
- (b) **Admin remote-start feature**: New admin-only action/route, e.g. `POST /manage/sessions/{session}/admin-start`, that sets `started_at`, `gps_confirmed_at` (with a flag/note indicating admin override), and `status = 'in_progress'` — bypassing GPS check. Add a button in `resources/views/sessions/manage/index.blade.php` or session detail/edit view, visible only to `admin` role, e.g. "Force Start (GPS Override)".
- (c) **Time-gating**: The checklist/start button should be disabled/hidden until `now() >= $session->scheduled_date->setTimeFromTimeString($session->scheduled_time)`. Check `SessionController@show` and the "PENDING: Start gate" block in `show.blade.php` — add a time check alongside the existing `pending` status check. Consider: `$session->scheduled_date->copy()->setTimeFromTimeString($session->scheduled_time ?? '00:00')->isFuture()` → show "not yet available" message instead of Start button.

### Task 10 — SMS notifications at cleaning milestones
Significant feature — requires SMS provider integration (Twilio or similar — note: brief says "no third-party apps/sites" but SMS inherently requires a gateway; flag this conflict to the client before starting).
Structure:
- New migration: add SMS notification preference columns to `properties` table (e.g., `notify_phone`, `notify_on_start`, `notify_on_finish`, `notify_on_photos_start`, `notify_on_task_note` — all booleans/nullable strings).
- New `NotificationService` or similar, called from:
  - `SessionController@start` — on session start (status → in_progress)
  - Session completion handler (status → completed) — finish notification
  - First photo upload for a session — "started taking photos" notification (track via a flag on session, e.g. `first_photo_notified_at`)
  - `ChecklistController@toggle` or note-save endpoint — when a task note is added, send notification with the note content
- Each trigger checks the property's notification settings and sends via SMS gateway (Twilio recommended — requires `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` env vars, `composer require twilio/sdk`).
- Add a settings UI section on the property edit page for configuring phone number + which events trigger notifications.

**Flag to client**: Task 10 requires a paid third-party SMS service (Twilio, Vonage, etc.) which conflicts with "no third-party apps or sites" — needs clarification/budget for SMS credits before implementation.
