# OneDrive Backup Setup Guide

This guide will help you set up OneDrive backup integration for your Iruali e-commerce application using Microsoft Graph API.

---

## Prerequisites

- Microsoft 365 account (or Azure AD account)
- Access to Azure Portal
- Basic understanding of OAuth 2.0

---

## Step 1: Register Your Application in Azure Portal

### 1.1 Go to Azure Portal
1. Visit [Azure Portal](https://portal.azure.com/)
2. Sign in with your Microsoft account
3. Navigate to **Azure Active Directory** → **App registrations**

### 1.2 Create New Registration
1. Click **New registration**
2. Fill in the details:
   - **Name**: `Iruali Backup App` (or any name you prefer)
   - **Supported account types**: Choose based on your needs:
     - `Accounts in this organizational directory only` (for business accounts)
     - `Accounts in any organizational directory and personal Microsoft accounts` (for personal accounts)
   - **Redirect URI**: 
     - Type: `Web`
     - URI: `http://localhost:8000/auth/onedrive/callback` (for development)
     - For production: `https://yourdomain.com/auth/onedrive/callback`

### 1.3 Note Your Application Details
After registration, note down:
- **Application (client) ID** - This is your `ONEDRIVE_CLIENT_ID`
- **Directory (tenant) ID** - This is your `ONEDRIVE_TENANT_ID`

---

## Step 2: Configure API Permissions

### 2.1 Add Microsoft Graph Permissions
1. In your app registration, go to **API permissions**
2. Click **Add a permission**
3. Select **Microsoft Graph**
4. Choose **Delegated permissions**
5. Add these permissions:
   - `Files.ReadWrite.All` - Read and write files in OneDrive
   - `User.Read` - Read user profile
   - `offline_access` - Maintain access to data you have given it access to

### 2.2 Grant Admin Consent (if needed)
- If you're using a business account, click **Grant admin consent**
- For personal accounts, this step is not required

---

## Step 3: Create Client Secret

### 3.1 Generate Secret
1. Go to **Certificates & secrets**
2. Click **New client secret**
3. Add a description (e.g., "Backup App Secret")
4. Choose expiration (recommend 12 months)
5. Click **Add**

### 3.2 Copy the Secret Value
- **Important**: Copy the secret value immediately (you won't see it again)
- This is your `ONEDRIVE_CLIENT_SECRET`

---

## Step 4: Get Refresh Token

### 4.1 Method 1: Using Browser (Recommended)
1. Open your browser and navigate to this URL (replace with your values):
```
https://login.microsoftonline.com/{TENANT_ID}/oauth2/v2.0/authorize?
client_id={CLIENT_ID}&
response_type=code&
redirect_uri=http://localhost:8000/auth/onedrive/callback&
scope=offline_access%20https://graph.microsoft.com/.default
```

2. Sign in with your Microsoft account
3. Grant permissions when prompted
4. You'll be redirected to a URL like:
   `http://localhost:8000/auth/onedrive/callback?code=...`
5. Copy the `code` parameter value

### 4.2 Method 2: Using Postman or cURL
1. Make a POST request to:
   `https://login.microsoftonline.com/{TENANT_ID}/oauth2/v2.0/token`

2. With form data:
   ```
   grant_type=authorization_code
   client_id={CLIENT_ID}
   client_secret={CLIENT_SECRET}
   code={CODE_FROM_STEP_4_1}
   redirect_uri=http://localhost:8000/auth/onedrive/callback
   ```

3. The response will contain:
   ```json
   {
     "access_token": "...",
     "refresh_token": "...",
     "expires_in": 3600
   }
   ```

4. Copy the `refresh_token` value

---

## Step 5: Configure Your Laravel Application

### 5.1 Add Environment Variables
Add these to your `.env` file:
```env
# OneDrive Configuration
ONEDRIVE_CLIENT_ID=your_client_id_here
ONEDRIVE_CLIENT_SECRET=your_client_secret_here
ONEDRIVE_REFRESH_TOKEN=your_refresh_token_here
ONEDRIVE_TENANT_ID=your_tenant_id_here
ONEDRIVE_REDIRECT_URI=http://localhost:8000/auth/onedrive/callback
```

### 5.2 Test the Connection
Run this command to test your OneDrive connection:
```bash
php artisan backup:onedrive --test
```

You should see:
```
🚀 Starting OneDrive backup process...
🔍 Testing OneDrive connection...
✅ OneDrive connection successful!
📁 Drive ID: b!...
👤 User: Your Name
```

---

## Step 6: Run Your First Backup

### 6.1 Manual Backup
```bash
php artisan backup:onedrive
```

This will:
1. Create a local backup using Spatie Laravel Backup
2. Upload the backup file to OneDrive in a `Backups` folder
3. Show upload progress for large files

### 6.2 Automated Backups
Your application is already configured to run OneDrive backups daily at 5:00 AM.

---

## Step 7: Verify Backups

### 7.1 Check OneDrive
1. Go to [OneDrive](https://onedrive.live.com/)
2. Look for a `Backups` folder
3. Your backup files will be named like: `iruali-backup-2025-07-13-02-00-00.zip`

### 7.2 Check Backup Status
```bash
php artisan backup:list
```

---

## Troubleshooting

### Common Issues

#### 1. "OneDrive credentials not configured"
- Check that all environment variables are set in `.env`
- Ensure no extra spaces or quotes around values

#### 2. "Failed to get access token"
- Verify your client ID, client secret, and tenant ID
- Check that your refresh token is valid
- Ensure your app has the correct permissions

#### 3. "OneDrive connection failed"
- Check your internet connection
- Verify your Microsoft account has OneDrive access
- Ensure your app registration is active

#### 4. "Upload failed"
- Check file size limits (OneDrive has limits)
- Verify you have enough OneDrive storage space
- Check network connectivity

### Debug Commands

```bash
# Test OneDrive connection
php artisan backup:onedrive --test

# Run backup with verbose output
php artisan backup:onedrive -v

# Check Laravel logs
tail -f storage/logs/laravel.log
```

---

## Security Best Practices

1. **Never commit credentials to version control**
2. **Use environment variables for all secrets**
3. **Rotate client secrets regularly**
4. **Use least-privilege permissions**
5. **Monitor access logs in Azure Portal**

---

## Production Deployment

### 1. Update Redirect URI
For production, update your redirect URI in Azure Portal to your actual domain:
```
https://yourdomain.com/auth/onedrive/callback
```

### 2. Update Environment Variables
Update your production `.env` file with the correct redirect URI:
```env
ONEDRIVE_REDIRECT_URI=https://yourdomain.com/auth/onedrive/callback
```

### 3. Test in Production
```bash
php artisan backup:onedrive --test
```

---

## Backup File Management

### Automatic Cleanup
- Local backups are cleaned up by Spatie Laravel Backup
- OneDrive files are not automatically deleted
- Consider setting up OneDrive retention policies

### Manual Cleanup
You can manually delete old OneDrive backup files through the OneDrive web interface.

---

## Support

If you encounter issues:
1. Check the troubleshooting section above
2. Review Laravel logs: `storage/logs/laravel.log`
3. Check Azure Portal for app registration status
4. Verify OneDrive storage space and permissions

---

**Your OneDrive backup system is now ready! 🚀** 