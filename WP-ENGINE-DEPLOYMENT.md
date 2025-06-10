# WP Engine Deployment Guide

Since you're using **WP Engine hosting**, here are your best options for automatic deployment:

## 🎯 Recommended: WP Pusher (Easiest)

1. **Install WP Pusher** on your WordPress site: https://wppusher.com/
2. **WordPress Admin** → WP Pusher → GitHub
3. **Connect to GitHub** (one-time authorization)
4. **Push-to-Deploy** → Install Plugin from GitHub
5. **Repository**: `andyfreed/cfp-ethics-workshops-manager`
6. **Branch**: `main`
7. **Enable auto-deployment**

✅ **Result**: Every time you push to GitHub, your WP Engine site updates automatically!

## 🔧 Alternative: Manual Upload

1. **Download ZIP** from GitHub: https://github.com/andyfreed/cfp-ethics-workshops-manager/archive/refs/heads/main.zip
2. **Extract** the files
3. **Upload via WP Engine File Manager** or WordPress Admin
4. **Activate** the plugin

## ❌ Why GitHub Actions Won't Work

WP Engine blocks FTP/SFTP access for security reasons, so the automated workflows we tried won't work with their hosting platform.

## 🚀 Best Workflow for WP Engine

```
You edit code → Push to GitHub → WP Pusher deploys → Live on WP Engine
```

WP Pusher is specifically designed to work with hosting providers like WP Engine that have deployment restrictions. 