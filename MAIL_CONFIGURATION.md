# Mail Configuration Guide for Production

## Overview
This guide helps you configure email delivery for the Iruali E-commerce application in production.

## Current Production Configuration
The `.env.production` file contains the following mail settings:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_secure_password_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="Iruali E-commerce"
```

## Required Updates Before Deployment

### 1. Replace Placeholder Values
Update the following in `.env.production`:

- `smtp.yourdomain.com` → Your actual SMTP server
- `noreply@yourdomain.com` → Your actual email address
- `your_secure_password_here` → Your actual email password

### 2. Popular Email Provider Configurations

#### Gmail (G Suite)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-specific-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your-email@gmail.com"
MAIL_FROM_NAME="Iruali E-commerce"
```

#### Outlook/Hotmail
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-mail.outlook.com
MAIL_PORT=587
MAIL_USERNAME=your-email@outlook.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your-email@outlook.com"
MAIL_FROM_NAME="Iruali E-commerce"
```

#### SendGrid
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="Iruali E-commerce"
```

#### Mailgun
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-mailgun-username
MAIL_PASSWORD=your-mailgun-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="Iruali E-commerce"
```

## Testing Email Configuration

### 1. Test Command
Use the provided test command to verify email delivery:

```bash
# Test with default email (MAIL_FROM_ADDRESS)
php artisan mail:test

# Test with specific email
php artisan mail:test admin@yourdomain.com
```

### 2. What the Test Does
- Displays current mail configuration
- Sends a test email
- Logs success/failure
- Provides troubleshooting tips if failed

### 3. Expected Output
```
Testing email configuration...
From: noreply@yourdomain.com (Iruali E-commerce)
To: noreply@yourdomain.com
Mailer: smtp
Host: smtp.yourdomain.com
Port: 587
Encryption: tls
✅ Test email sent successfully!
Check your inbox for the test email.
```

## Security Best Practices

### 1. Use App-Specific Passwords
- For Gmail: Enable 2FA and generate app-specific password
- For other providers: Use dedicated email accounts for applications

### 2. Environment Variables
- Never commit real credentials to version control
- Use environment variables for all sensitive data
- Keep `.env` files secure and backed up

### 3. Email Validation
- Use verified sender addresses
- Implement SPF, DKIM, and DMARC records
- Monitor email deliverability

## Troubleshooting

### Common Issues

#### 1. Authentication Failed
```
Error: SMTP connect() failed
```
**Solution:** Check username/password and enable "less secure apps" or use app-specific passwords

#### 2. Connection Timeout
```
Error: Connection timed out
```
**Solution:** Check firewall settings and verify SMTP host/port

#### 3. TLS/SSL Issues
```
Error: SSL certificate problem
```
**Solution:** Verify encryption settings (tls/ssl) and port numbers

### Debug Steps
1. Run `php artisan mail:test` to see detailed error messages
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify SMTP settings with your email provider
4. Test SMTP connection using telnet or online tools

## Production Checklist

- [ ] Updated `.env.production` with real credentials
- [ ] Tested email delivery with `php artisan mail:test`
- [ ] Verified email received in inbox
- [ ] Checked spam folder for test emails
- [ ] Configured email provider security settings
- [ ] Set up email monitoring/logging
- [ ] Backed up email configuration

## Email Templates Used in Application

The application sends emails for:
- User registration verification
- Password reset
- Order confirmations
- Order status updates
- Newsletter subscriptions (if implemented)

All emails use the configured `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` settings. 