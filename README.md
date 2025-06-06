# CFP Ethics Workshops Manager

A comprehensive WordPress plugin for managing CFP (Certified Financial Planner) Ethics Workshops with historical data tracking, upcoming workshop management, and digital attendance sign-in functionality.

## Features

### Workshop Management
- **Complete Workshop Database**: Track all workshop details including dates, FPA chapters, instructors, locations, and administrative information
- **Historical Data Import**: Import existing workshop data from Excel/CSV files
- **Comprehensive Tracking**: Monitor materials sent, invoicing, attendance counts, and evaluation reports
- **Batch Processing**: Organize workshops by batch numbers and processing dates

### Attendance & Sign-in System
- **Digital Sign-in Forms**: Public-facing forms for workshop attendees using shortcode `[cfp_workshop_signin]`
- **Program Evaluations**: Built-in 5-star rating system for workshop feedback
- **Automated Tracking**: Automatic attendance recording with timestamp and IP tracking
- **Export Functionality**: Generate CSV reports of attendees and evaluations

### Administrative Features
- **WordPress Admin Integration**: Full admin panel with intuitive navigation
- **Data Export/Import**: CSV import for historical data and export for reporting
- **Search & Filter**: Easy filtering by workshop, date, or FPA chapter
- **Manual Entry**: Admin can manually add sign-ins when needed

## Installation

1. **Download the Plugin**
   ```bash
   git clone https://github.com/yourusername/cfp-ethics-workshops-manager.git
   ```

2. **Upload to WordPress**
   - Upload the `cfp-ethics-workshops.php` file to your WordPress `/wp-content/plugins/` directory
   - Or upload via WordPress Admin → Plugins → Add New → Upload Plugin

3. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "CFP Ethics Workshops Manager" and click "Activate"

4. **Setup**
   - Navigate to "CFP Workshops" in your WordPress admin menu
   - Import your historical data via "Import Data" if needed
   - Add the shortcode `[cfp_workshop_signin]` to any page where attendees should sign in

## Usage

### Admin Functions

#### Workshop Management
- **View All Workshops**: `wp-admin/admin.php?page=cfp-workshops`
- **Add New Workshop**: `wp-admin/admin.php?page=cfp-workshops-add`
- **Edit Workshop**: Click "Edit" on any workshop in the list

#### Sign-in Management
- **View Sign-ins**: `wp-admin/admin.php?page=cfp-workshops-signins`
- **Add Manual Sign-in**: `wp-admin/admin.php?page=cfp-workshops-signin-add`
- **Export Sign-ins**: Filter by workshop and click "Export Sign-ins to CSV"

#### Data Import
- **Import Historical Data**: `wp-admin/admin.php?page=cfp-workshops-import`
- Supports CSV format with all workshop fields

### Public Sign-in Form

Add the shortcode to any page or post:
```
[cfp_workshop_signin]
```

The form includes:
- Participant information (name, email, CFP ID, affiliation)
- Workshop date selection
- 5-category evaluation system with star ratings
- Email newsletter opt-in

## Database Schema

### Workshops Table (`wp_cfp_workshops`)
- Complete workshop information including dates, contacts, instructors
- Billing and invoice tracking
- Materials and administrative status
- Batch processing information

### Sign-ins Table (`wp_cfp_workshop_signins`)
- Attendee information and CFP ID tracking
- Detailed evaluation ratings (5 categories)
- Timestamp and IP address logging
- Email newsletter preferences

## Shortcodes

### `[cfp_workshop_signin]`
Creates a public sign-in form with:
- Responsive design with modern UI
- Interactive star rating system
- Form validation and error handling
- Automatic workshop association
- Success confirmation messages

## Configuration

### Required WordPress Capabilities
- `manage_options` - Required for all admin functions

### File Permissions
- Plugin file should be readable by WordPress
- WordPress must have database write permissions

### Dependencies
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Data Import Format

CSV files should include these columns (in order):
1. Seminar Date
2. Customer (FPA Chapter)
3. Time & Location
4. Contact Name
5. Phone
6. Email
7. Other Email
8. Instructor
9. Instructor CFP ID
10. Webinar Completed  
11. Webinar Sign-in Link
12. CFP Board Attest Form
13. Location
14. Initial Materials Sent
15. All Materials Sent
16. Attendees Count
17. Roster Received
18. Batch Number
19. Batch Date
20. CFP Acknowledgment
21. Invoice Sent
22. Invoice Amount
23. Invoice Received
24. Settlement Report
25. Evals to CFPB
26. Notes

## Export Reports

### Sign-in Reports Include:
- Workshop information header
- Complete attendee list with contact information
- Evaluation ratings for all categories
- Newsletter signup status
- Export timestamp

## Security Features

- WordPress nonce verification for all forms
- Input sanitization and validation
- SQL injection prevention with prepared statements
- Cross-site scripting (XSS) protection
- IP address logging for sign-ins

## Support

For support, feature requests, or bug reports:
- Create an issue in this GitHub repository
- Email: [your-email@domain.com]

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v2 or later - see the WordPress Plugin License for details.

## Changelog

### Version 1.0.0
- Initial release
- Complete workshop management system
- Public sign-in forms with evaluations
- CSV import/export functionality
- WordPress admin integration 