# Automatic Deployment Setup

This guide will help you set up automatic deployment from GitHub to your WordPress site whenever you push changes to the main branch.

## 🚨 WP Engine Users - Special Instructions

**If you're using WP Engine hosting**, skip to **Method 2 (WP Pusher)** or **Method 4 (WP Engine Git)**. WP Engine doesn't support FTP/SFTP deployment for security reasons.

## Method 1: GitHub Actions with FTP (Recommended)

### Prerequisites
- FTP access to your WordPress hosting
- Your WordPress site's FTP credentials

### Setup Steps

1. **Get Your FTP Credentials**
   - FTP Host (e.g., `ftp.yoursite.com`)
   - FTP Username
   - FTP Password
   - Path to your WordPress plugins directory (usually `/wp-content/plugins/`)

2. **Add GitHub Secrets**
   
   Go to your GitHub repository → Settings → Secrets and variables → Actions → New repository secret
   
   Add these secrets:
   - `FTP_HOST` - Your FTP server hostname
   - `FTP_USERNAME` - Your FTP username  
   - `FTP_PASSWORD` - Your FTP password

3. **Commit and Push the Workflow**
   
   The workflow file `.github/workflows/deploy.yml` is already created. Just commit and push:
   ```bash
   git add .
   git commit -m "Add automatic deployment workflow"
   git push origin main
   ```

4. **Test the Deployment**
   
   - Make any small change to your plugin
   - Commit and push to main branch
   - Check GitHub Actions tab to see the deployment status
   - Verify the plugin updated on your WordPress site

### How It Works
- Triggers on every push to main branch
- Downloads your code
- Uploads only the plugin files to your WordPress site
- Excludes Git files, README, and other non-essential files

## Method 2: WP Pusher (Recommended - Most Reliable)

### Steps:
1. **Install WP Pusher plugin** on your WordPress site: https://wppusher.com/
2. **In WordPress Admin** → WP Pusher → GitHub
3. **Connect to GitHub** (authorize the app)
4. **Push-to-Deploy** → Install Plugin from GitHub
5. **Repository**: `andyfreed/cfp-ethics-workshops-manager`
6. **Branch**: `main`
7. **Subdirectory**: Leave blank
8. **Enable auto-deployment**: Check "Push-to-Deploy"

### Pros:
- ✅ Works with any hosting provider
- ✅ No FTP/SFTP credentials needed
- ✅ Handles WordPress-specific deployment
- ✅ Easy setup through WordPress admin
- ✅ Real-time deployment notifications

### Why This Works Better:
- Bypasses hosting FTP/SFTP restrictions
- Uses GitHub API directly
- Handles file permissions correctly
- More reliable than GitHub Actions for hosting with limited access

## Method 3: SFTP (More Secure)

If your host supports SFTP (more secure than FTP):

1. **Uncomment the SFTP section** in `.github/workflows/deploy.yml`
2. **Comment out the FTP section**
3. **Add these secrets instead**:
   - `SFTP_HOST` - Your SFTP server hostname
   - `SFTP_USERNAME` - Your SFTP username
   - `SFTP_PASSWORD` - Your SFTP password

## Method 4: WP Engine Git Push (For WP Engine Users)

### Option A: WP Engine Git Push
1. **Enable Git** in your WP Engine User Portal
2. **Add your SSH key** to WP Engine
3. **Clone the WP Engine repository**:
   ```bash
   git clone git@git.wpengine.com:production/your-site-name.git
   ```
4. **Copy your plugin** to the WP Engine repo:
   ```bash
   cp -r cfp-ethics-workshops-manager /path/to/wpengine-repo/wp-content/plugins/
   ```
5. **Push to WP Engine**:
   ```bash
   git add . && git commit -m "Add CFP plugin" && git push origin master
   ```

### Option B: WP Engine GitHub Action (Advanced)
Use WP Engine's official GitHub Action for automated deployment.

## Method 5: Manual Upload (WP Engine Backup)

1. **Download the latest release** from GitHub
2. **Upload via WP Engine's File Manager** or WordPress admin
3. **Activate the plugin** in WordPress

## Troubleshooting

### Connection Timeout Errors (Common Issue)

If you get "Timeout (control socket)" or "Failed to connect" errors:

1. **Try SFTP Instead of FTPS**:
   - Disable the main workflow: Rename `deploy.yml` to `deploy.yml.disabled`
   - Enable SFTP workflow: Rename `deploy-sftp.yml` to `deploy.yml`
   - Update your GitHub Secrets to use `SFTP_` prefix instead of `FTP_`

2. **Contact Your Host** to confirm:
   - What protocol they support (FTP, FTPS, or SFTP)
   - What port to use (21 for FTP/FTPS, 22 for SFTP)
   - If there are firewall restrictions

3. **Test Your Credentials** locally:
   ```bash
   # Test FTPS
   lftp ftps://username:password@your-host.com
   
   # Test SFTP  
   sftp username@your-host.com
   ```

### GitHub Actions Fails
- Check the Actions tab for error messages
- Verify FTP/SFTP credentials are correct
- Ensure the server path exists
- Check file permissions on your server

### Plugin Not Updating
- Verify the deployment completed successfully
- Check if files actually uploaded to the right directory
- Clear any WordPress caching
- Deactivate and reactivate the plugin if needed

### Security Best Practices
- Use SFTP instead of FTP when possible
- Use strong, unique passwords for FTP accounts
- Consider IP restrictions on FTP access
- Regularly rotate credentials

## Manual Deployment (Backup Method)

If automatic deployment fails, you can always deploy manually:

1. **Download the latest release** from GitHub
2. **Upload via FTP** to `/wp-content/plugins/cfp-ethics-workshops-manager/`
3. **Or use WordPress admin** → Plugins → Upload Plugin

## Support

For deployment issues:
- Check GitHub Actions logs
- Verify hosting provider supports the deployment method
- Contact your hosting provider for FTP/SFTP issues 