# CFP Ethics Workshops Manager

WordPress plugin for managing CFP Ethics Workshops with historical data, upcoming workshops, attendance sign‑in, and materials generation from PDF/PowerPoint templates.

## Features
- Admin UI for workshops, sign‑ins, dashboard, and templates
- Upload PDF/PPTX templates; generate materials with workshop data
- Public sign‑in form via shortcode: `[cfp_workshop_signin]`
- Clean, gated debug logging to avoid production log bloat

## Requirements
- WordPress 5.8+
- PHP 7.4+
- PHP ZipArchive extension (for PPTX processing)

## Installation
### Using WP Pusher (recommended)
1. Install WP Pusher on your site: https://wppusher.com
2. WP Admin → WP Pusher → GitHub → connect
3. Install Plugin from GitHub:
   - Repository: `andyfreed/cfp-ethics-workshops-manager`
   - Branch: `main`
   - Subdirectory: leave blank
4. Enable Push‑to‑Deploy for automatic updates on every push.

### Manual
- Download ZIP: https://codeload.github.com/andyfreed/cfp-ethics-workshops-manager/zip/refs/heads/main
- Upload the ZIP in WP Admin → Plugins → Add New → Upload Plugin, or
- Extract and upload the folder to `wp-content/plugins/cfp-ethics-workshops-manager/`
- Activate the plugin

## Deployment (WP Engine notes)
- WP Pusher works well with WP Engine and avoids PHP upload limits
- Alternatives:
  - SFTP: upload the plugin folder to `wp-content/plugins/`
  - SSH + WP‑CLI:
    ```bash
    cd wp-content/plugins
    rm -rf cfp-ethics-workshops-manager
    wp plugin install https://codeload.github.com/andyfreed/cfp-ethics-workshops-manager/zip/refs/heads/main --force --activate
    ```

## Usage
- WP Admin → CFP Workshops
  - All Workshops: manage records; use “Generate Materials” to download files
  - Materials Templates: upload PDF/PPTX and define field mappings
  - Sign‑ins: manage attendee sign‑ins
- Public sign‑in form: add `[cfp_workshop_signin]` to a page

## Logging
- Logging is gated by a toggle to keep production quiet.
- Code checks `CFPEW_DEBUG_LOGGING` (default: false) or the site option `cfpew_debug_logging`.
- To enable temporarily, define the constant or set the option to `1`.

## Troubleshooting
- “Download failed” in WP Pusher: ensure repo is public (or use a GitHub classic token with `repo` scope) and branch is `main`.
- Template download opens corrupted: fixed by early download handling; clear cache and retry.
- PowerPoint generation: requires ZipArchive; ensure placeholders like `{{workshop_date}}` exist.

## License
GPL v2 or later
