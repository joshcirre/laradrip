# AGENTS.md

## Cursor Cloud specific instructions

### Project overview
LaraDrip Me Out — an AI-powered photo booth built with Laravel 12, Livewire 3/Volt, and Flux/Flux Pro UI. Users capture a webcam photo and the Google Gemini API generates a modified image. Results display in a real-time gallery via Laravel broadcasting.

### System dependencies (pre-installed in snapshot)
- PHP 8.4 with extensions: mbstring, xml, curl, zip, sqlite3, gd, bcmath, intl
- Composer 2.x
- Node.js 22.x / npm 10.x
- SQLite (default database)

### Flux Pro authentication
`livewire/flux-pro` is a paid Composer package from `composer.fluxui.dev`. It requires `FLUX_USERNAME` and `FLUX_LICENSE_KEY` secrets. Without these credentials, `composer install` will fail. The CI workflow (`.github/workflows/tests.yml`) shows the auth setup:
```
composer config http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE_KEY"
```
Run this before `composer install` if the secrets are available.

### Running the application
- Full dev mode: `composer run dev` (starts artisan serve, queue listener, pail, and Vite concurrently)
- Individual services: see `composer.json` `scripts.dev` for the concurrently command breakdown
- The app uses SQLite by default — no external database needed

### Running tests
- `php artisan test` or `./vendor/bin/pest`
- 2 tests in `ImageGalleryTest` (retry button / retryImage) are pre-existing failures: the component filters out failed images and doesn't implement `retryImage()`

### Linting
- `vendor/bin/pint` (auto-fix) or `vendor/bin/pint --test` (check only)

### Environment variables
- `GEMINI_API_KEY` is required for AI image generation but not for basic app startup or tests
- `BROADCAST_CONNECTION=reverb` enables real-time gallery updates via WebSocket

### Reverb (WebSocket broadcasting)
- `laravel/reverb` is installed. Start with `php artisan reverb:start --host=0.0.0.0 --port=8080`
- The `.env` must include `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` plus their `VITE_REVERB_*` counterparts for the frontend
- Without these `VITE_REVERB_*` vars, the browser console throws `"You must pass your app key when you instantiate Pusher"` — rebuild assets (`npm run build`) after adding them
- If Reverb is not running, WebSocket connection errors appear in the console but the app still works (gallery just won't auto-refresh)

### Non-obvious gotchas
- Vite manifest errors (`ViteException`) during tests or page loads mean frontend assets need rebuilding: run `npm run build`
- The queue worker (`php artisan queue:listen --tries=1`) must be running for `GenerateImageJob` to process — without it, captured photos stay in "pending" status forever
- After switching between package versions (e.g. re-running `composer install` after a version change), run `php artisan view:clear` to avoid stale compiled Blade views referencing classes from the wrong version
- The Gemini model name in `GeminiService.php` is hardcoded as `gemini-2.5-flash-image-preview` which may become unavailable. The current working model is `gemini-2.5-flash-image`. If image generation fails with a 404, check the model name
- Images are stored on the `local` (private) disk with `'serve' => true` — Laravel serves them via signed URLs. Run `php artisan storage:link` if the public storage symlink doesn't exist
