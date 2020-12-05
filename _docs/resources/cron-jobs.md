# Cron jobs

Cron jobs are defined and scheduled at `/resources/cron.php`.

## delete_api_ratelimit_buckets

This job runs once daily in order to clean up old rate limit buckets that have not been modified for more than 24 hours.