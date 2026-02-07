# Scheduler / Deployment notes

This project defines a scheduled Artisan command `events:fetch` in `app/Console/Commands/FetchApiEvents.php` which fetches configured API payloads and stores them on the `User` record (`api_last_payload` / `api_last_fetched_at`).

To ensure Laravel runs scheduled tasks, run `php artisan schedule:run` every minute on the server. Examples:

Linux / macOS (crontab)
```bash
* * * * * cd /path/to/dashZam && php artisan schedule:run >> /dev/null 2>&1
```

Windows (Task Scheduler - create task via CLI)
```powershell
schtasks /Create /SC MINUTE /MO 1 /TN "LaravelSchedule" /TR "\"C:\path\to\php.exe\" \"C:\Users\viper\Desktop\type2\dashZam\artisan\" schedule:run" /F
```

Quick verification

Run a manual fetch to verify the command works and updates the stored payloads:
```bash
php artisan events:fetch
```

Confirm the latest stored timestamp:
```bash
php artisan tinker --execute="echo \App\Models\User::query()->whereNotNull('api_last_payload')->orderByDesc('api_last_fetched_at')->first()?->api_last_fetched_at;"
```

Add the crontab / Task Scheduler step to your deployment procedure so the scheduler runs continuously.
