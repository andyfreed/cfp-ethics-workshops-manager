<?php
/**
 * Plugin Name: CFP Ethics Workshops Manager
 * Plugin URI: https://yoursite.com/
 * Description: Manages CFP Ethics Workshops with historical data, upcoming workshops, and attendance sign-in
 * Version: 1.0.3
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CFPEW_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CFPEW_PLUGIN_URL', plugin_dir_url(__FILE__));

// Activation hook
register_activation_hook(__FILE__, 'cfpew_activate');
function cfpew_activate() {
    cfpew_create_tables();
    flush_rewrite_rules();
    set_transient('cfpew_activation_notice', true, 5);
}

// Create database tables
function cfpew_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Workshops table with ALL original columns
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    $sql_workshops = "CREATE TABLE $workshops_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        seminar_date date NOT NULL,
        customer varchar(255) DEFAULT NULL,
        time_location text DEFAULT NULL,
        contact_name varchar(255) DEFAULT NULL,
        phone varchar(50) DEFAULT NULL,
        email varchar(255) DEFAULT NULL,
        other_email varchar(255) DEFAULT NULL,
        instructor varchar(255) DEFAULT NULL,
        instructor_cfp_id varchar(50) DEFAULT NULL,
        webinar_completed varchar(50) DEFAULT NULL,
        webinar_signin_link text DEFAULT NULL,
        cfp_board_attest_form varchar(255) DEFAULT NULL,
        location varchar(255) DEFAULT NULL,
        initial_materials_sent date DEFAULT NULL,
        all_materials_sent date DEFAULT NULL,
        attendees_count int(11) DEFAULT NULL,
        roster_received varchar(50) DEFAULT NULL,
        batch_number int(11) DEFAULT NULL,
        batch_date date DEFAULT NULL,
        cfp_acknowledgment varchar(50) DEFAULT NULL,
        invoice_sent date DEFAULT NULL,
        invoice_amount decimal(10,2) DEFAULT NULL,
        invoice_received date DEFAULT NULL,
        settlement_report varchar(50) DEFAULT NULL,
        evals_to_cfpb varchar(50) DEFAULT NULL,
        notes text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_seminar_date (seminar_date)
    ) $charset_collate;";
    
    // Attendance sign-ins table with evaluation fields
    $signins_table = $wpdb->prefix . 'cfp_workshop_signins';
    $sql_signins = "CREATE TABLE $signins_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        workshop_id int(11) NOT NULL,
        first_name varchar(255) NOT NULL,
        last_name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        cfp_id varchar(50) NOT NULL,
        affiliation varchar(255) DEFAULT NULL,
        workshop_date date NOT NULL,
        learning_objectives_rating int(1) DEFAULT NULL,
        content_organized_rating int(1) DEFAULT NULL,
        content_relevant_rating int(1) DEFAULT NULL,
        activities_helpful_rating int(1) DEFAULT NULL,
        instructor_knowledgeable_rating int(1) DEFAULT NULL,
        overall_rating int(1) DEFAULT NULL,
        email_newsletter tinyint(1) DEFAULT 0,
        completion_date datetime DEFAULT CURRENT_TIMESTAMP,
        ip_address varchar(45) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_workshop_id (workshop_id),
        KEY idx_email (email)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_workshops);
    dbDelta($sql_signins);
}

// Add admin menu
add_action('admin_menu', 'cfpew_admin_menu');
function cfpew_admin_menu() {
    add_menu_page(
        'CFP Ethics Workshops',
        'CFP Workshops',
        'manage_options',
        'cfp-workshops',
        'cfpew_workshops_page',
        'dashicons-welcome-learn-more',
        30
    );
    
    add_submenu_page(
        'cfp-workshops',
        'All Workshops',
        'All Workshops',
        'manage_options',
        'cfp-workshops',
        'cfpew_workshops_page'
    );
    
    add_submenu_page(
        'cfp-workshops',
        'Add New Workshop',
        'Add New',
        'manage_options',
        'cfp-workshops-add',
        'cfpew_add_workshop_page'
    );
    
    add_submenu_page(
        'cfp-workshops',
        'Sign-ins',
        'Sign-ins',
        'manage_options',
        'cfp-workshops-signins',
        'cfpew_signins_page'
    );
    
    add_submenu_page(
        'cfp-workshops',
        'Add Sign-in',
        'Add Sign-in',
        'manage_options',
        'cfp-workshops-signin-add',
        'cfpew_add_signin_page'
    );
    
    add_submenu_page(
        'cfp-workshops',
        'Import Data',
        'Import Data',
        'manage_options',
        'cfp-workshops-import',
        'cfpew_import_page'
    );
}

// Workshops list page
function cfpew_workshops_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cfp_workshops';
    
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $wpdb->delete($table_name, array('id' => intval($_GET['id'])));
        echo '<div class="notice notice-success"><p>Workshop deleted successfully!</p></div>';
    }
    
    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Get workshops
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $workshops = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY seminar_date DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));
    
    ?>
    <div class="wrap">
        <h1>CFP Ethics Workshops <a href="?page=cfp-workshops-add" class="page-title-action">Add New</a></h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Location</th>
                    <th>Contact</th>
                    <th>Instructor</th>
                    <th>Attendees</th>
                    <th>Invoice</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($workshops as $workshop): ?>
                <tr>
                    <td><?php echo esc_html($workshop->seminar_date); ?></td>
                    <td><?php echo esc_html($workshop->customer); ?></td>
                    <td><?php echo esc_html($workshop->location ?: $workshop->time_location); ?></td>
                    <td>
                        <?php echo esc_html($workshop->contact_name); ?><br>
                        <small><?php echo esc_html($workshop->email); ?></small>
                    </td>
                    <td><?php echo esc_html($workshop->instructor); ?></td>
                    <td><?php echo esc_html($workshop->attendees_count); ?></td>
                    <td>$<?php echo number_format($workshop->invoice_amount, 2); ?></td>
                    <td>
                        <a href="?page=cfp-workshops-add&id=<?php echo $workshop->id; ?>" class="button button-small">Edit</a>
                        <a href="?page=cfp-workshops&action=delete&id=<?php echo $workshop->id; ?>" 
                           class="button button-small" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Pagination links
        $total_pages = ceil($total_items / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $current_page
            ));
            echo '</div></div>';
        }
        ?>
    </div>
    <?php
}

// Add/Edit workshop page with ALL fields
function cfpew_add_workshop_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cfp_workshops';
    
    $workshop = null;
    if (isset($_GET['id'])) {
        $workshop = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cfpew_nonce']) && 
        wp_verify_nonce($_POST['cfpew_nonce'], 'cfpew_save_workshop')) {
        
        $data = array(
            'seminar_date' => sanitize_text_field($_POST['seminar_date']),
            'customer' => sanitize_text_field($_POST['customer']),
            'time_location' => sanitize_textarea_field($_POST['time_location']),
            'contact_name' => sanitize_text_field($_POST['contact_name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'other_email' => sanitize_text_field($_POST['other_email']),
            'instructor' => sanitize_text_field($_POST['instructor']),
            'instructor_cfp_id' => sanitize_text_field($_POST['instructor_cfp_id']),
            'webinar_completed' => sanitize_text_field($_POST['webinar_completed']),
            'webinar_signin_link' => esc_url_raw($_POST['webinar_signin_link']),
            'cfp_board_attest_form' => sanitize_text_field($_POST['cfp_board_attest_form']),
            'location' => sanitize_text_field($_POST['location']),
            'initial_materials_sent' => !empty($_POST['initial_materials_sent']) ? sanitize_text_field($_POST['initial_materials_sent']) : null,
            'all_materials_sent' => !empty($_POST['all_materials_sent']) ? sanitize_text_field($_POST['all_materials_sent']) : null,
            'attendees_count' => intval($_POST['attendees_count']),
            'roster_received' => sanitize_text_field($_POST['roster_received']),
            'batch_number' => intval($_POST['batch_number']),
            'batch_date' => !empty($_POST['batch_date']) ? sanitize_text_field($_POST['batch_date']) : null,
            'cfp_acknowledgment' => sanitize_text_field($_POST['cfp_acknowledgment']),
            'invoice_sent' => !empty($_POST['invoice_sent']) ? sanitize_text_field($_POST['invoice_sent']) : null,
            'invoice_amount' => floatval($_POST['invoice_amount']),
            'invoice_received' => !empty($_POST['invoice_received']) ? sanitize_text_field($_POST['invoice_received']) : null,
            'settlement_report' => sanitize_text_field($_POST['settlement_report']),
            'evals_to_cfpb' => sanitize_text_field($_POST['evals_to_cfpb']),
            'notes' => sanitize_textarea_field($_POST['notes'])
        );
        
        if ($workshop) {
            $wpdb->update($table_name, $data, array('id' => $workshop->id));
            echo '<div class="notice notice-success"><p>Workshop updated successfully!</p></div>';
        } else {
            $wpdb->insert($table_name, $data);
            echo '<div class="notice notice-success"><p>Workshop added successfully!</p></div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo $workshop ? 'Edit Workshop' : 'Add New Workshop'; ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('cfpew_save_workshop', 'cfpew_nonce'); ?>
            
            <h2>Basic Information</h2>
            <table class="form-table">
                <tr>
                    <th><label for="seminar_date">Seminar Date*</label></th>
                    <td><input type="date" name="seminar_date" id="seminar_date" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->seminar_date) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="customer">Customer (FPA Chapter)*</label></th>
                    <td><input type="text" name="customer" id="customer" class="large-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->customer) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="time_location">Time & Location Details</label></th>
                    <td><textarea name="time_location" id="time_location" class="large-text" rows="3"><?php 
                        echo $workshop ? esc_textarea($workshop->time_location) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="location">Location</label></th>
                    <td><input type="text" name="location" id="location" class="large-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->location) : ''; ?>"></td>
                </tr>
            </table>
            
            <h2>Contact Information</h2>
            <table class="form-table">
                <tr>
                    <th><label for="contact_name">Contact Name</label></th>
                    <td><input type="text" name="contact_name" id="contact_name" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->contact_name) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="phone">Phone</label></th>
                    <td><input type="tel" name="phone" id="phone" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->phone) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="email">Email</label></th>
                    <td><input type="email" name="email" id="email" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->email) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="other_email">Other Email or #</label></th>
                    <td><input type="text" name="other_email" id="other_email" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->other_email) : ''; ?>"></td>
                </tr>
            </table>
            
            <h2>Instructor Information</h2>
            <table class="form-table">
                <tr>
                    <th><label for="instructor">Instructor*</label></th>
                    <td><input type="text" name="instructor" id="instructor" class="large-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->instructor) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="instructor_cfp_id">Instructor CFP ID #</label></th>
                    <td><input type="text" name="instructor_cfp_id" id="instructor_cfp_id" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->instructor_cfp_id) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="webinar_completed">Current Instructor Webinar Completed</label></th>
                    <td><input type="text" name="webinar_completed" id="webinar_completed" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->webinar_completed) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="webinar_signin_link">Webinar Sign in/Survey Link</label></th>
                    <td><input type="url" name="webinar_signin_link" id="webinar_signin_link" class="large-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->webinar_signin_link) : ''; ?>"></td>
                </tr>
            </table>
            
            <h2>Materials & Administrative</h2>
            <table class="form-table">
                <tr>
                    <th><label for="cfp_board_attest_form">CFP Board Acknowledgment Attest Form</label></th>
                    <td><input type="text" name="cfp_board_attest_form" id="cfp_board_attest_form" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->cfp_board_attest_form) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="initial_materials_sent">Initial Materials Sent</label></th>
                    <td><input type="date" name="initial_materials_sent" id="initial_materials_sent" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->initial_materials_sent) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="all_materials_sent">All Materials Sent</label></th>
                    <td><input type="date" name="all_materials_sent" id="all_materials_sent" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->all_materials_sent) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="attendees_count"># Attend Including Instructor</label></th>
                    <td><input type="number" name="attendees_count" id="attendees_count" class="small-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->attendees_count) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="roster_received">Roster Received</label></th>
                    <td><input type="text" name="roster_received" id="roster_received" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->roster_received) : ''; ?>"></td>
                </tr>
            </table>
            
            <h2>Batch & Processing</h2>
            <table class="form-table">
                <tr>
                    <th><label for="batch_number">Batch #</label></th>
                    <td><input type="number" name="batch_number" id="batch_number" class="small-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->batch_number) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="batch_date">Batch Date</label></th>
                    <td><input type="date" name="batch_date" id="batch_date" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->batch_date) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="cfp_acknowledgment">CFP Acknowledgment</label></th>
                    <td><input type="text" name="cfp_acknowledgment" id="cfp_acknowledgment" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->cfp_acknowledgment) : ''; ?>"></td>
                </tr>
            </table>
            
            <h2>Billing</h2>
            <table class="form-table">
                <tr>
                    <th><label for="invoice_sent">Invoice Sent</label></th>
                    <td><input type="date" name="invoice_sent" id="invoice_sent" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->invoice_sent) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="invoice_amount">Invoice Amount ($)</label></th>
                    <td><input type="number" name="invoice_amount" id="invoice_amount" class="regular-text" step="0.01"
                             value="<?php echo $workshop ? esc_attr($workshop->invoice_amount) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="invoice_received">Invoice Received</label></th>
                    <td><input type="date" name="invoice_received" id="invoice_received" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->invoice_received) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="settlement_report">Settlement Report</label></th>
                    <td><input type="text" name="settlement_report" id="settlement_report" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->settlement_report) : ''; ?>"></td>
                </tr>
            </table>
            
            <h2>Other</h2>
            <table class="form-table">
                <tr>
                    <th><label for="evals_to_cfpb">Evals-To-CFPB-Quarterly</label></th>
                    <td><input type="text" name="evals_to_cfpb" id="evals_to_cfpb" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->evals_to_cfpb) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="notes">Notes</label></th>
                    <td><textarea name="notes" id="notes" class="large-text" rows="4"><?php 
                        echo $workshop ? esc_textarea($workshop->notes) : ''; ?></textarea></td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php echo $workshop ? 'Update Workshop' : 'Add Workshop'; ?>">
            </p>
        </form>
    </div>
    <?php
}

// Sign-ins page
function cfpew_signins_page() {
    global $wpdb;
    $signins_table = $wpdb->prefix . 'cfp_workshop_signins';
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    // Handle export action
    if (isset($_GET['action']) && $_GET['action'] == 'export' && isset($_GET['workshop_id'])) {
        cfpew_export_signins(intval($_GET['workshop_id']));
        exit;
    }
    
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $wpdb->delete($signins_table, array('id' => intval($_GET['id'])));
        echo '<div class="notice notice-success"><p>Sign-in deleted successfully!</p></div>';
    }
    
    // Filter by workshop if specified
    $where_clause = '';
    $workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;
    if ($workshop_id) {
        $where_clause = $wpdb->prepare(" WHERE s.workshop_id = %d", $workshop_id);
    }
    
    // Get sign-ins with workshop info
    $signins = $wpdb->get_results("
        SELECT s.*, w.seminar_date, w.customer, w.instructor 
        FROM $signins_table s 
        LEFT JOIN $workshops_table w ON s.workshop_id = w.id 
        $where_clause
        ORDER BY s.completion_date DESC 
        LIMIT 100
    ");
    
    // Get all workshops for filter dropdown
    $workshops = $wpdb->get_results("
        SELECT id, seminar_date, customer, instructor 
        FROM $workshops_table 
        ORDER BY seminar_date DESC
    ");
    
    ?>
    <div class="wrap">
        <h1>Workshop Sign-ins <a href="?page=cfp-workshops-signin-add" class="page-title-action">Add Manual Sign-in</a></h1>
        
        <div style="margin-bottom: 20px;">
            <form method="get" action="">
                <input type="hidden" name="page" value="cfp-workshops-signins">
                <label for="workshop_id">Filter by Workshop: </label>
                <select name="workshop_id" id="workshop_id" onchange="this.form.submit()">
                    <option value="">All Workshops</option>
                    <?php foreach ($workshops as $workshop): ?>
                        <option value="<?php echo $workshop->id; ?>" <?php selected($workshop_id, $workshop->id); ?>>
                            <?php echo esc_html($workshop->seminar_date . ' - ' . $workshop->customer . ' (' . $workshop->instructor . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($workshop_id): ?>
                    <a href="?page=cfp-workshops-signins&action=export&workshop_id=<?php echo $workshop_id; ?>" 
                       class="button">Export Sign-ins to CSV</a>
                <?php endif; ?>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Sign-in Date</th>
                    <th>Workshop</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>CFP® ID</th>
                    <th>Affiliation</th>
                    <th>Overall Rating</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($signins): ?>
                    <?php foreach ($signins as $signin): ?>
                    <tr>
                        <td><?php echo esc_html($signin->completion_date); ?></td>
                        <td><?php echo esc_html($signin->seminar_date . ' - ' . $signin->customer); ?></td>
                        <td><?php echo esc_html($signin->first_name . ' ' . $signin->last_name); ?></td>
                        <td><?php echo esc_html($signin->email); ?></td>
                        <td><?php echo esc_html($signin->cfp_id); ?></td>
                        <td><?php echo esc_html($signin->affiliation); ?></td>
                        <td><?php echo $signin->overall_rating ? str_repeat('★', $signin->overall_rating) : 'N/A'; ?></td>
                        <td>
                            <a href="?page=cfp-workshops-signins&action=delete&id=<?php echo $signin->id; ?>" 
                               class="button button-small" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No sign-ins found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Add manual sign-in page
function cfpew_add_signin_page() {
    global $wpdb;
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    $signins_table = $wpdb->prefix . 'cfp_workshop_signins';
    
    // Get all workshops for dropdown
    $workshops = $wpdb->get_results("
        SELECT id, seminar_date, customer, instructor 
        FROM $workshops_table 
        ORDER BY seminar_date DESC
    ");
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cfpew_signin_nonce']) && 
        wp_verify_nonce($_POST['cfpew_signin_nonce'], 'cfpew_save_signin')) {
        
        $workshop_id = intval($_POST['workshop_id']);
        $workshop = $wpdb->get_row($wpdb->prepare("SELECT seminar_date FROM $workshops_table WHERE id = %d", $workshop_id));
        
        $data = array(
            'workshop_id' => $workshop_id,
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'cfp_id' => sanitize_text_field($_POST['cfp_id']),
            'affiliation' => sanitize_text_field($_POST['affiliation']),
            'workshop_date' => $workshop->seminar_date,
            'learning_objectives_rating' => intval($_POST['learning_objectives_rating']),
            'content_organized_rating' => intval($_POST['content_organized_rating']),
            'content_relevant_rating' => intval($_POST['content_relevant_rating']),
            'activities_helpful_rating' => intval($_POST['activities_helpful_rating']),
            'instructor_knowledgeable_rating' => intval($_POST['instructor_knowledgeable_rating']),
            'overall_rating' => intval($_POST['overall_rating']),
            'email_newsletter' => isset($_POST['email_newsletter']) ? 1 : 0,
            'completion_date' => current_time('mysql'),
            'ip_address' => 'Manual Entry'
        );
        
        $wpdb->insert($signins_table, $data);
        echo '<div class="notice notice-success"><p>Sign-in added successfully!</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1>Add Manual Sign-in</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('cfpew_save_signin', 'cfpew_signin_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="workshop_id">Workshop*</label></th>
                    <td>
                        <select name="workshop_id" id="workshop_id" required>
                            <option value="">-- Select Workshop --</option>
                            <?php foreach ($workshops as $workshop): ?>
                                <option value="<?php echo $workshop->id; ?>">
                                    <?php echo esc_html($workshop->seminar_date . ' - ' . $workshop->customer . ' (' . $workshop->instructor . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="first_name">First Name*</label></th>
                    <td><input type="text" name="first_name" id="first_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="last_name">Last Name*</label></th>
                    <td><input type="text" name="last_name" id="last_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="email">Email*</label></th>
                    <td><input type="email" name="email" id="email" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="cfp_id">CFP ID #*</label></th>
                    <td><input type="text" name="cfp_id" id="cfp_id" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="affiliation">Affiliation</label></th>
                    <td><input type="text" name="affiliation" id="affiliation" class="regular-text"></td>
                </tr>
            </table>
            
            <h2>Evaluation (Optional)</h2>
            <table class="form-table">
                <tr>
                    <th><label for="learning_objectives_rating">The learning objectives were clearly articulated</label></th>
                    <td>
                        <select name="learning_objectives_rating" id="learning_objectives_rating">
                            <option value="0">Not Rated</option>
                            <option value="1">1 Star</option>
                            <option value="2">2 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="5">5 Stars</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="content_organized_rating">Content was well organized and presented</label></th>
                    <td>
                        <select name="content_organized_rating" id="content_organized_rating">
                            <option value="0">Not Rated</option>
                            <option value="1">1 Star</option>
                            <option value="2">2 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="5">5 Stars</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="content_relevant_rating">Content was relevant and helpful</label></th>
                    <td>
                        <select name="content_relevant_rating" id="content_relevant_rating">
                            <option value="0">Not Rated</option>
                            <option value="1">1 Star</option>
                            <option value="2">2 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="5">5 Stars</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="activities_helpful_rating">The activities incorporated in the program helped illustrate how the new Code and Standards would be applied</label></th>
                    <td>
                        <select name="activities_helpful_rating" id="activities_helpful_rating">
                            <option value="0">Not Rated</option>
                            <option value="1">1 Star</option>
                            <option value="2">2 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="5">5 Stars</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="instructor_knowledgeable_rating">The instructor was knowledgeable about the new Code and Standards</label></th>
                    <td>
                        <select name="instructor_knowledgeable_rating" id="instructor_knowledgeable_rating">
                            <option value="0">Not Rated</option>
                            <option value="1">1 Star</option>
                            <option value="2">2 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="5">5 Stars</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="overall_rating">How many stars would you give this program?</label></th>
                    <td>
                        <select name="overall_rating" id="overall_rating">
                            <option value="0">Not Rated</option>
                            <option value="1">1 Star</option>
                            <option value="2">2 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="5">5 Stars</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="email_newsletter">Email Newsletter</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="email_newsletter" id="email_newsletter" value="1">
                            Subscribe to email list for new courses and coupon codes
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button-primary" value="Add Sign-in">
            </p>
        </form>
    </div>
    <?php
}

// Export sign-ins to CSV
function cfpew_export_signins($workshop_id) {
    global $wpdb;
    $signins_table = $wpdb->prefix . 'cfp_workshop_signins';
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    // Get workshop info
    $workshop = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $workshops_table WHERE id = %d", 
        $workshop_id
    ));
    
    if (!$workshop) {
        wp_die('Workshop not found');
    }
    
    // Get sign-ins
    $signins = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $signins_table WHERE workshop_id = %d ORDER BY last_name, first_name",
        $workshop_id
    ));
    
    // Set headers for CSV download
    $filename = 'cfp-ethics-signins-' . $workshop->seminar_date . '-' . sanitize_title($workshop->customer) . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    // Add workshop info at top
    fputcsv($output, array('CFP Ethics Workshop Sign-in Report'));
    fputcsv($output, array('Workshop Date:', $workshop->seminar_date));
    fputcsv($output, array('FPA Chapter:', $workshop->customer));
    fputcsv($output, array('Instructor:', $workshop->instructor));
    fputcsv($output, array('Total Attendees:', count($signins)));
    fputcsv($output, array('')); // Empty row
    
    // Column headers
    fputcsv($output, array(
        'First Name', 
        'Last Name', 
        'Email', 
        'CFP ID', 
        'Affiliation',
        'Sign-in Date/Time',
        'Learning Objectives Rating',
        'Content Organized Rating',
        'Content Relevant Rating',
        'Activities Helpful Rating',
        'Instructor Knowledgeable Rating',
        'Overall Rating',
        'Newsletter Signup'
    ));
    
    // Data rows
    foreach ($signins as $signin) {
        fputcsv($output, array(
            $signin->first_name,
            $signin->last_name,
            $signin->email,
            $signin->cfp_id,
            $signin->affiliation,
            $signin->completion_date,
            $signin->learning_objectives_rating ?: 'N/A',
            $signin->content_organized_rating ?: 'N/A',
            $signin->content_relevant_rating ?: 'N/A',
            $signin->activities_helpful_rating ?: 'N/A',
            $signin->instructor_knowledgeable_rating ?: 'N/A',
            $signin->overall_rating ?: 'N/A',
            $signin->email_newsletter ? 'Yes' : 'No'
        ));
    }
    
    fclose($output);
}

// Import page
function cfpew_import_page() {
    ?>
    <div class="wrap">
        <h1>Import Workshop Data</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
            cfpew_process_import();
        }
        ?>
        
        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th><label for="import_file">Select Excel File</label></th>
                    <td>
                        <input type="file" name="import_file" id="import_file" accept=".xlsx,.xls,.csv" required>
                        <p class="description">Upload your SeminarLedger.xlsx file to import historical workshop data.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button-primary" value="Import Data">
            </p>
        </form>
        
        <div class="notice notice-warning">
            <p><strong>Important:</strong> This will import all workshops from your Excel file. Existing workshops with the same date and customer will be updated.</p>
            <p>Please export your Excel file to CSV format before importing.</p>
        </div>
    </div>
    <?php
}

// Process import
function cfpew_process_import() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cfp_workshops';
    
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error"><p>File upload failed.</p></div>';
        return;
    }
    
    $uploaded_file = $_FILES['import_file']['tmp_name'];
    $file_extension = pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION);
    
    if ($file_extension == 'csv') {
        cfpew_import_csv($uploaded_file);
    } else {
        echo '<div class="notice notice-info"><p>For Excel files, please first export to CSV format and then import.</p></div>';
    }
}

// Import CSV data with ALL columns
function cfpew_import_csv($file_path) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cfp_workshops';
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        echo '<div class="notice notice-error"><p>Could not open file.</p></div>';
        return;
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    
    $imported = 0;
    $updated = 0;
    
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (empty($data[0])) continue; // Skip empty rows
        
        // Parse date
        $seminar_date = date('Y-m-d', strtotime($data[0]));
        if (!$seminar_date || $seminar_date == '1970-01-01') continue;
        
        $workshop_data = array(
            'seminar_date' => $seminar_date,
            'customer' => isset($data[1]) ? $data[1] : '',
            'time_location' => isset($data[2]) ? $data[2] : '',
            'contact_name' => isset($data[3]) ? $data[3] : '',
            'phone' => isset($data[4]) ? $data[4] : '',
            'email' => isset($data[5]) ? $data[5] : '',
            'other_email' => isset($data[6]) ? $data[6] : '',
            'instructor' => isset($data[7]) ? $data[7] : '',
            'instructor_cfp_id' => isset($data[8]) ? $data[8] : '',
            'webinar_completed' => isset($data[9]) ? $data[9] : '',
            'webinar_signin_link' => isset($data[10]) ? $data[10] : '',
            'cfp_board_attest_form' => isset($data[11]) ? $data[11] : '',
            'location' => isset($data[12]) ? $data[12] : '',
            'initial_materials_sent' => !empty($data[13]) ? date('Y-m-d', strtotime($data[13])) : null,
            'all_materials_sent' => !empty($data[14]) ? date('Y-m-d', strtotime($data[14])) : null,
            'attendees_count' => isset($data[15]) ? intval($data[15]) : 0,
            'roster_received' => isset($data[16]) ? $data[16] : '',
            'batch_number' => isset($data[17]) ? intval($data[17]) : 0,
            'batch_date' => !empty($data[18]) ? date('Y-m-d', strtotime($data[18])) : null,
            'cfp_acknowledgment' => isset($data[19]) ? $data[19] : '',
            'invoice_sent' => !empty($data[20]) ? date('Y-m-d', strtotime($data[20])) : null,
            'invoice_amount' => isset($data[21]) ? floatval($data[21]) : 0,
            'invoice_received' => !empty($data[22]) ? date('Y-m-d', strtotime($data[22])) : null,
            'settlement_report' => isset($data[23]) ? $data[23] : '',
            'evals_to_cfpb' => isset($data[24]) ? $data[24] : '',
            'notes' => isset($data[25]) ? $data[25] : ''
        );
        
        // Check if workshop exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE seminar_date = %s AND customer = %s",
            $seminar_date, $workshop_data['customer']
        ));
        
        if ($existing) {
            $wpdb->update($table_name, $workshop_data, array('id' => $existing));
            $updated++;
        } else {
            $wpdb->insert($table_name, $workshop_data);
            $imported++;
        }
    }
    
    fclose($handle);
    
    echo '<div class="notice notice-success"><p>';
    echo sprintf('Import complete! %d workshops imported, %d workshops updated.', $imported, $updated);
    echo '</p></div>';
}

// Shortcode for public sign-in form
add_shortcode('cfp_workshop_signin', 'cfpew_signin_shortcode');
function cfpew_signin_shortcode($atts) {
    global $wpdb;
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    // Get all FPA chapters for dropdown
    $chapters = $wpdb->get_col("SELECT DISTINCT customer FROM $workshops_table ORDER BY customer");
    
    ob_start();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cfp_signin_submit'])) {
        cfpew_process_signin();
    }
    
    ?>
    <div class="cfp-workshop-signin-form">
        <?php if (isset($_GET['signin_success']) && $_GET['signin_success'] == '1'): ?>
            <div class="cfp-notice success">
                <p>Thank you for signing in! Your attendance has been recorded for CFP® continuing education credit reporting.</p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="cfp-form-row">
                <div class="cfp-form-field">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" id="first_name" required>
                </div>
                <div class="cfp-form-field">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" id="last_name" required>
                </div>
            </div>
            
            <div class="cfp-form-row">
                <div class="cfp-form-field">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" name="email" id="email" required>
                </div>
                <div class="cfp-form-field">
                    <label for="cfp_id">CFP ID # <span class="required">*</span></label>
                    <input type="text" name="cfp_id" id="cfp_id" required>
                </div>
            </div>
            
            <div class="cfp-form-field">
                <label for="affiliation">Affiliation <span class="required">*</span></label>
                <select name="affiliation" id="affiliation" required>
                    <option value="">-- Select FPA Chapter --</option>
                    <?php foreach ($chapters as $chapter): ?>
                        <option value="<?php echo esc_attr($chapter); ?>"><?php echo esc_html($chapter); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="cfp-form-field">
                <label for="workshop_date">Date of Workshop <span class="required">*</span></label>
                <input type="date" name="workshop_date" id="workshop_date" required>
            </div>
            
            <h3>Program Evaluation</h3>
            
            <div class="cfp-rating-field">
                <label>The learning objectives were clearly articulated <span class="required">*</span></label>
                <div class="cfp-star-rating" data-field="learning_objectives">
                    <span class="star" data-value="1">☆</span>
                    <span class="star" data-value="2">☆</span>
                    <span class="star" data-value="3">☆</span>
                    <span class="star" data-value="4">☆</span>
                    <span class="star" data-value="5">☆</span>
                </div>
                <input type="hidden" name="learning_objectives_rating" id="learning_objectives_rating" required>
            </div>
            
            <div class="cfp-rating-field">
                <label>Content was well organized and presented <span class="required">*</span></label>
                <div class="cfp-star-rating" data-field="content_organized">
                    <span class="star" data-value="1">☆</span>
                    <span class="star" data-value="2">☆</span>
                    <span class="star" data-value="3">☆</span>
                    <span class="star" data-value="4">☆</span>
                    <span class="star" data-value="5">☆</span>
                </div>
                <input type="hidden" name="content_organized_rating" id="content_organized_rating" required>
            </div>
            
            <div class="cfp-rating-field">
                <label>Content was relevant and helpful <span class="required">*</span></label>
                <div class="cfp-star-rating" data-field="content_relevant">
                    <span class="star" data-value="1">☆</span>
                    <span class="star" data-value="2">☆</span>
                    <span class="star" data-value="3">☆</span>
                    <span class="star" data-value="4">☆</span>
                    <span class="star" data-value="5">☆</span>
                </div>
                <input type="hidden" name="content_relevant_rating" id="content_relevant_rating" required>
            </div>
            
            <div class="cfp-rating-field">
                <label>The activities incorporated in the program helped illustrate how the new Code and Standards would be applied <span class="required">*</span></label>
                <div class="cfp-star-rating" data-field="activities_helpful">
                    <span class="star" data-value="1">☆</span>
                    <span class="star" data-value="2">☆</span>
                    <span class="star" data-value="3">☆</span>
                    <span class="star" data-value="4">☆</span>
                    <span class="star" data-value="5">☆</span>
                </div>
                <input type="hidden" name="activities_helpful_rating" id="activities_helpful_rating" required>
            </div>
            
            <div class="cfp-rating-field">
                <label>The instructor was knowledgeable about the new Code and Standards <span class="required">*</span></label>
                <div class="cfp-star-rating" data-field="instructor_knowledgeable">
                    <span class="star" data-value="1">☆</span>
                    <span class="star" data-value="2">☆</span>
                    <span class="star" data-value="3">☆</span>
                    <span class="star" data-value="4">☆</span>
                    <span class="star" data-value="5">☆</span>
                </div>
                <input type="hidden" name="instructor_knowledgeable_rating" id="instructor_knowledgeable_rating" required>
            </div>
            
            <div class="cfp-rating-field">
                <label>How many stars would you give this program? <span class="required">*</span></label>
                <div class="cfp-star-rating" data-field="overall">
                    <span class="star" data-value="1">☆</span>
                    <span class="star" data-value="2">☆</span>
                    <span class="star" data-value="3">☆</span>
                    <span class="star" data-value="4">☆</span>
                    <span class="star" data-value="5">☆</span>
                </div>
                <input type="hidden" name="overall_rating" id="overall_rating" required>
            </div>
            
            <div class="cfp-form-field">
                <label class="cfp-checkbox-label">
                    <input type="checkbox" name="email_newsletter" value="1">
                    Click here to subscribe to our email list for new courses and coupon codes!
                </label>
            </div>
            
            <p class="cfp-submit">
                <input type="submit" name="cfp_signin_submit" value="Submit" class="button">
            </p>
        </form>
        
        <style>
        .cfp-workshop-signin-form {
            max-width: 700px;
            margin: 20px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .cfp-form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .cfp-form-field {
            flex: 1;
            margin-bottom: 20px;
        }
        .cfp-form-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .cfp-form-field input[type="text"],
        .cfp-form-field input[type="email"],
        .cfp-form-field input[type="date"],
        .cfp-form-field select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .cfp-form-field input:focus,
        .cfp-form-field select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        .cfp-workshop-signin-form h3 {
            margin: 30px 0 20px;
            font-size: 20px;
            color: #333;
        }
        .cfp-rating-field {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .cfp-rating-field label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: #333;
        }
        .cfp-star-rating {
            display: flex;
            gap: 5px;
            font-size: 28px;
        }
        .cfp-star-rating .star {
            cursor: pointer;
            color: #ddd;
            transition: color 0.2s;
        }
        .cfp-star-rating .star:hover,
        .cfp-star-rating .star.active {
            color: #ffc107;
        }
        .cfp-star-rating .star.filled {
            color: #ffc107;
        }
        .cfp-checkbox-label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }
        .cfp-checkbox-label input[type="checkbox"] {
            margin-right: 8px;
        }
        .required {
            color: #d00;
        }
        .cfp-notice {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .cfp-notice.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .cfp-submit {
            margin-top: 30px;
        }
        .cfp-submit .button {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .cfp-submit .button:hover {
            background: #45a049;
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle star ratings
            const starRatings = document.querySelectorAll('.cfp-star-rating');
            
            starRatings.forEach(function(rating) {
                const stars = rating.querySelectorAll('.star');
                const field = rating.dataset.field;
                const input = document.getElementById(field + '_rating');
                
                stars.forEach(function(star, index) {
                    star.addEventListener('click', function() {
                        const value = index + 1;
                        input.value = value;
                        
                        // Update visual state
                        stars.forEach(function(s, i) {
                            if (i < value) {
                                s.classList.add('filled');
                                s.textContent = '★';
                            } else {
                                s.classList.remove('filled');
                                s.textContent = '☆';
                            }
                        });
                    });
                    
                    star.addEventListener('mouseenter', function() {
                        const value = index + 1;
                        stars.forEach(function(s, i) {
                            if (i < value) {
                                s.classList.add('active');
                            } else {
                                s.classList.remove('active');
                            }
                        });
                    });
                });
                
                rating.addEventListener('mouseleave', function() {
                    stars.forEach(function(s) {
                        s.classList.remove('active');
                    });
                });
            });
        });
        </script>
    </div>
    <?php
    
    return ob_get_clean();
}

// Process public sign-in form submission
function cfpew_process_signin() {
    global $wpdb;
    $signins_table = $wpdb->prefix . 'cfp_workshop_signins';
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    // Debug mode - uncomment to see what's being submitted
    // error_log('Sign-in form submitted with data: ' . print_r($_POST, true));
    
    // Find the workshop based on date and affiliation
    $workshop_date = sanitize_text_field($_POST['workshop_date']);
    $affiliation = sanitize_text_field($_POST['affiliation']);
    
    $workshop = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $workshops_table 
        WHERE seminar_date = %s AND customer = %s",
        $workshop_date, $affiliation
    ));
    
    if (!$workshop) {
        // Create a placeholder workshop if not found
        $wpdb->insert($workshops_table, array(
            'seminar_date' => $workshop_date,
            'customer' => $affiliation,
            'instructor' => 'TBD - Auto-created',
            'notes' => 'Auto-created from sign-in form submission',
            'created_at' => current_time('mysql')
        ));
        $workshop_id = $wpdb->insert_id;
        
        // Log the creation
        error_log("Created new workshop with ID: $workshop_id for $affiliation on $workshop_date");
    } else {
        $workshop_id = $workshop->id;
    }
    
    // Validate required fields
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $cfp_id = sanitize_text_field($_POST['cfp_id']);
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($cfp_id)) {
        wp_die('Missing required fields. Please go back and complete all required fields.');
    }
    
    // Check for duplicate sign-in
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $signins_table 
        WHERE workshop_id = %d AND email = %s",
        $workshop_id, $email
    ));
    
    if (!$existing) {
        // Prepare sign-in data
        $signin_data = array(
            'workshop_id' => $workshop_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'cfp_id' => $cfp_id,
            'affiliation' => $affiliation,
            'workshop_date' => $workshop_date,
            'learning_objectives_rating' => !empty($_POST['learning_objectives_rating']) ? intval($_POST['learning_objectives_rating']) : 0,
            'content_organized_rating' => !empty($_POST['content_organized_rating']) ? intval($_POST['content_organized_rating']) : 0,
            'content_relevant_rating' => !empty($_POST['content_relevant_rating']) ? intval($_POST['content_relevant_rating']) : 0,
            'activities_helpful_rating' => !empty($_POST['activities_helpful_rating']) ? intval($_POST['activities_helpful_rating']) : 0,
            'instructor_knowledgeable_rating' => !empty($_POST['instructor_knowledgeable_rating']) ? intval($_POST['instructor_knowledgeable_rating']) : 0,
            'overall_rating' => !empty($_POST['overall_rating']) ? intval($_POST['overall_rating']) : 0,
            'email_newsletter' => isset($_POST['email_newsletter']) ? 1 : 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'completion_date' => current_time('mysql')
        );
        
        // Insert sign-in record
        $result = $wpdb->insert($signins_table, $signin_data);
        
        if ($result === false) {
            // Log database error
            error_log('Failed to insert sign-in: ' . $wpdb->last_error);
            wp_die('There was an error saving your sign-in. Please try again or contact support.');
        }
    }
    
    // Redirect to prevent form resubmission
    wp_redirect(add_query_arg('signin_success', '1', $_SERVER['REQUEST_URI']));
    exit;
}

// Add admin notice for plugin activation
add_action('admin_notices', 'cfpew_admin_notices');
function cfpew_admin_notices() {
    if (get_transient('cfpew_activation_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>CFP Ethics Workshops Manager activated!</strong></p>
            <p>To get started:</p>
            <ol>
                <li>Go to <a href="<?php echo admin_url('admin.php?page=cfp-workshops-import'); ?>">CFP Workshops → Import Data</a> to import your historical data</li>
                <li>Add the shortcode <code>[cfp_workshop_signin]</code> to any page where attendees should sign in</li>
                <li>View and export sign-ins from <a href="<?php echo admin_url('admin.php?page=cfp-workshops-signins'); ?>">CFP Workshops → Sign-ins</a></li>
            </ol>
        </div>
        <?php
        delete_transient('cfpew_activation_notice');
    }
}