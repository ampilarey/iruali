# Iruali Backup Guide

This guide explains how to use, automate, and troubleshoot backups for the Iruali e-commerce project using Spatie Laravel Backup.

---

## 1. Manual Backup Usage

### Local Backups
- **Run a backup:**
  ```bash
  php artisan backup:run
  ```
- **List backups:**
  ```bash
  php artisan backup:list
  ```
- **Clean up old backups:**
  ```bash
  php artisan backup:clean
  ```
- **Monitor backup health:**
  ```bash
  php artisan backup:monitor
  ```

### OneDrive Cloud Backups
- **Run OneDrive backup:**
  ```bash
  php artisan backup:onedrive
  ```
- **Test OneDrive connection:**
  ```bash
  php artisan backup:onedrive --test
  ```

Backups are stored locally in `storage/app/laravel-backup/` and uploaded to OneDrive in a `Backups` folder.

---

## 2. Automated Backups (Recommended)

Backups, cleanup, and health checks are scheduled automatically via Laravel's scheduler:

- **Local backup:** daily at 2:00 AM
- **Cleanup:** daily at 3:00 AM
- **Health check:** daily at 4:00 AM
- **OneDrive backup:** daily at 5:00 AM

To enable automation, ensure your server runs the Laravel scheduler:

```
* * * * * cd /path/to/iruali && php artisan schedule:run >> /dev/null 2>&1
```

---

## 3. Notification Setup

- **Email notifications:**
  - Set `BACKUP_NOTIFICATION_EMAIL` in your `.env` to receive backup alerts.
  - Uses `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` for sender info.
- **Slack/Discord notifications:**
  - Set `BACKUP_SLACK_WEBHOOK_URL` or `BACKUP_DISCORD_WEBHOOK_URL` in `.env` if you want alerts in those channels.
  - Leave blank to disable.

## 4. OneDrive Cloud Backup Setup

For OneDrive integration, follow the detailed setup guide:

**📖 [OneDrive Setup Guide](ONEDRIVE_SETUP_GUIDE.md)**

This includes:
- Azure Portal app registration
- API permissions configuration
- OAuth token setup
- Environment variable configuration
- Testing and troubleshooting

---

## 5. Restore from Backup

### Local Backups
1. Locate the backup zip in `storage/app/laravel-backup/`.
2. Unzip and restore files as needed.
3. For database, import the `.sql` file using your DB tool or:
   ```bash
   mysql -u <user> -p <database> < path/to/backup.sql.gz
   ```

### OneDrive Backups
1. Download the backup file from OneDrive `Backups` folder.
2. Follow the same restore process as local backups.

---

## 6. Troubleshooting

### Local Backup Issues
- **No backups created?**
  - Check `storage/logs/laravel.log` for errors.
  - Ensure `storage/app/laravel-backup/` is writable.
- **Notifications not received?**
  - Check your `.env` for correct email/webhook settings.
  - Test mail with `php artisan mail:test` (if available).
- **Scheduler not running?**
  - Make sure the cron job is set up and running.
- **Disk full or quota exceeded?**
  - Clean up old backups: `php artisan backup:clean`
  - Adjust retention in `config/backup.php` if needed.

### OneDrive Backup Issues
- **OneDrive connection failed?**
  - Run `php artisan backup:onedrive --test` to diagnose
  - Check your Azure app registration and permissions
  - Verify your refresh token is valid
- **Upload failed?**
  - Check OneDrive storage space
  - Verify network connectivity
  - Check file size limits

---

## 7. Customization

- Edit `config/backup.php` to:
  - Change what files/folders are included/excluded
  - Adjust retention policy
  - Change notification settings
  - Add/remove backup disks (e.g., S3, Dropbox)

- Edit `config/services.php` to:
  - Configure OneDrive API settings
  - Add other cloud storage providers

---

## 8. More Info

- [Spatie Laravel Backup Docs](https://spatie.be/docs/laravel-backup)
- [Backup config reference](config/backup.php)
- [OneDrive Setup Guide](ONEDRIVE_SETUP_GUIDE.md)

---

**Backups are only useful if you test restoring them!** 