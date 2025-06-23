<?php
/**
 * Plugin Name: CFP Ethics Workshops Manager
 * Plugin URI: https://bhfe.com
 * Description: Manages CFP Ethics Workshops with historical data, upcoming workshops, and attendance sign-in
 * Version: 1.0.10
 * Author: Skynet
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

// Check for database updates on plugin load
add_action('plugins_loaded', 'cfpew_check_database_version');
function cfpew_check_database_version() {
    $current_version = get_option('cfpew_db_version', '1.0.0');
    $plugin_version = '1.0.10';
    
    if (version_compare($current_version, $plugin_version, '<')) {
        cfpew_create_tables();
        update_option('cfpew_db_version', $plugin_version);
    }
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
        workshop_cost decimal(10,2) DEFAULT NULL,
        workshop_description text DEFAULT NULL,
        materials_files text DEFAULT NULL,
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
    
    // Templates table for PDF and PowerPoint templates
    $templates_table = $wpdb->prefix . 'cfp_workshop_templates';
    $sql_templates = "CREATE TABLE $templates_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        template_name varchar(255) NOT NULL,
        template_type enum('pdf','powerpoint') NOT NULL,
        file_path varchar(500) NOT NULL,
        original_filename varchar(255) NOT NULL,
        field_mappings text DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        upload_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_template_type (template_type)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_workshops);
    dbDelta($sql_signins);
    dbDelta($sql_templates);
}

// Handle downloads early (before any output)
add_action('admin_init', 'cfpew_handle_early_downloads');
function cfpew_handle_early_downloads() {
    // Only process downloads on our admin pages
    if (!is_admin() || !isset($_GET['page'])) return;
    
    $page = $_GET['page'];
    $valid_pages = array('cfp-workshops', 'cfp-workshops-templates', 'cfp-workshops-signins');
    
    if (!in_array($page, $valid_pages)) return;
    
    // Handle workshop materials generation
    if ($page == 'cfp-workshops' && isset($_GET['action']) && $_GET['action'] == 'generate_materials' && isset($_GET['id'])) {
        cfpew_generate_workshop_materials(intval($_GET['id']));
        exit;
    }
    
    // Handle CSV export from sign-ins page
    if ($page == 'cfp-workshops-signins' && isset($_GET['action']) && $_GET['action'] == 'export') {
        $workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : null;
        cfpew_export_signins($workshop_id);
        exit;
    }
    
    // Handle template downloads  
    if ($page == 'cfp-workshops-templates' && isset($_GET['action']) && $_GET['action'] == 'download_template' && isset($_GET['template_id'])) {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cfp_workshop_templates';
        $template_id = intval($_GET['template_id']);
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $templates_table WHERE id = %d", $template_id));
        
        if ($template && file_exists($template->file_path)) {
            cfpew_download_template_file($template->file_path, $template->original_filename);
        } else {
            wp_die('Template file not found');
        }
    }
}

// Add admin menu
add_action('admin_menu', 'cfpew_admin_menu');
function cfpew_admin_menu() {
    add_menu_page(
        'CFP Ethics Workshops',
        'CFP Ethics Workshops',
        'manage_options',
        'cfp-workshops',
        'cfpew_workshops_page',
        'dashicons-welcome-learn-more',
        30
    );
    
    add_submenu_page(
        'cfp-workshops',
        'Dashboard',
        'Dashboard',
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
}

// Workshops list page
function cfpew_workshops_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cfp_workshops';
    
    // Note: Generate materials action is now handled early in admin_init hook
    
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $wpdb->delete($table_name, array('id' => intval($_GET['id'])));
        echo '<div class="notice notice-success"><p>Workshop deleted successfully!</p></div>';
    }
    
    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Refactored: Lean filter logic for uninvoiced workshops
    $show_uninvoiced = !empty($_GET['show_uninvoiced']);
    $where = $show_uninvoiced ? "WHERE invoice_sent_flag = 0" : '';
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
    $workshops = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name $where ORDER BY seminar_date DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));
    
    ?>
    <div class="wrap">
        <h1>CFP Ethics Workshops <a href="?page=cfp-workshops-add" class="page-title-action">Add New</a></h1>
        
        <!-- Lean filter UI -->
        <form method="get" style="margin-bottom: 1em;">
            <input type="hidden" name="page" value="cfp-workshops">
            <label>
                <input type="checkbox" name="show_uninvoiced" value="1" <?php checked($show_uninvoiced, 1); ?> onchange="this.form.submit()">
                Show only workshops where invoice has not been sent
            </label>
        </form>
        
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
                    <td>
                        <?php
                        $invoice_sent_flag = isset($workshop->invoice_sent_flag) ? $workshop->invoice_sent_flag : 0;
                        $invoice_sent = $workshop->invoice_sent;
                        if ($invoice_sent_flag && $invoice_sent) {
                            echo '<span style="color: green; font-weight: bold;">' . esc_html($invoice_sent) . '</span>';
                        } elseif ($invoice_sent_flag) {
                            echo '<span style="color: orange; font-weight: bold;">Sent (no date)</span>';
                        } else {
                            echo '<span style="color: red; font-weight: bold;">Not Sent</span>';
                        }
                        ?>
                        <br>$<?php echo number_format($workshop->invoice_amount, 2); ?>
                    </td>
                    <td>
                        <a href="?page=cfp-workshops-add&id=<?php echo $workshop->id; ?>" class="button button-small">Edit</a>
                        <a href="?page=cfp-workshops&action=delete&id=<?php echo $workshop->id; ?>" 
                           class="button button-small" onclick="return confirm('Are you sure?')">Delete</a>
                        <br><br>
                        <a href="?page=cfp-workshops&action=generate_materials&id=<?php echo $workshop->id; ?>" 
                           class="button button-primary button-small">📄 Generate Materials</a>
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
            'location' => sanitize_text_field($_POST['location']),
            'all_materials_sent' => !empty($_POST['all_materials_sent']) ? sanitize_text_field($_POST['all_materials_sent']) : null,
            'attendees_count' => intval($_POST['attendees_count']),
            'roster_received' => sanitize_text_field($_POST['roster_received']),
            'batch_number' => intval($_POST['batch_number']),
            'batch_date' => !empty($_POST['batch_date']) ? sanitize_text_field($_POST['batch_date']) : null,
            'invoice_sent_flag' => isset($_POST['invoice_sent_flag']) ? 1 : 0,
            'invoice_sent' => (isset($_POST['invoice_sent_flag']) && !empty($_POST['invoice_sent'])) ? sanitize_text_field($_POST['invoice_sent']) : null,
            'invoice_amount' => floatval($_POST['invoice_amount']),
            'invoice_received' => !empty($_POST['invoice_received']) ? sanitize_text_field($_POST['invoice_received']) : null,
            'settlement_report' => sanitize_text_field($_POST['settlement_report']),
            'evals_to_cfpb' => sanitize_text_field($_POST['evals_to_cfpb']),
            'notes' => sanitize_textarea_field($_POST['notes']),
            'workshop_cost' => floatval($_POST['workshop_cost']),
            'workshop_description' => sanitize_textarea_field($_POST['workshop_description']),
            'materials_files' => sanitize_textarea_field($_POST['materials_files']),
            'invoice_unknown' => isset($_POST['invoice_unknown']) ? 1 : 0
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
                    <th><label for="all_materials_sent">All Materials Sent</label></th>
                    <td><input type="date" name="all_materials_sent" id="all_materials_sent" class="regular-text" 
                             value="<?php echo $workshop ? esc_attr($workshop->all_materials_sent) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="attendees_count"># of Attendees</label></th>
                    <td>
                        <?php
                        // Auto-calculate attendee count if editing an existing workshop
                        $auto_attendees_count = '';
                        if ($workshop && isset($workshop->id)) {
                            $signins_table = $wpdb->prefix . 'cfp_workshop_signins';
                            $auto_attendees_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $signins_table WHERE workshop_id = %d", $workshop->id));
                        }
                        ?>
                        <input type="number" name="attendees_count" id="attendees_count" class="small-text" 
                            value="<?php echo $workshop ? esc_attr($auto_attendees_count) : ''; ?>">
                        <span class="description">This is the number of people who signed in for this workshop.</span>
                    </td>
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
                    <th><label for="invoice_sent_flag">Invoice Sent?</label></th>
                    <td>
                        <input type="checkbox" name="invoice_sent_flag" id="invoice_sent_flag" value="1" <?php if ($workshop && !empty($workshop->invoice_sent_flag)) echo 'checked'; ?> onchange="document.getElementById('invoice_sent_date_row').style.display = this.checked ? '' : 'none';">
                        <span class="description">Check if invoice has been sent. Optionally enter a date.</span>
                    </td>
                </tr>
                <tr id="invoice_sent_date_row" style="display:<?php echo ($workshop && !empty($workshop->invoice_sent_flag)) ? '' : 'none'; ?>;">
                    <th><label for="invoice_sent">Invoice Sent Date</label></th>
                    <td>
                        <input type="date" name="invoice_sent" id="invoice_sent" class="regular-text" value="<?php echo $workshop ? esc_attr($workshop->invoice_sent) : ''; ?>">
                        <span class="description">Optional: Enter the date the invoice was sent.</span>
                    </td>
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
            
            <h2>Workshop Dashboard Information</h2>
            <table class="form-table">
                <tr>
                    <th><label for="workshop_cost">Workshop Cost ($)</label></th>
                    <td><input type="number" name="workshop_cost" id="workshop_cost" class="regular-text" step="0.01"
                             value="<?php echo $workshop ? esc_attr($workshop->workshop_cost) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="workshop_description">Workshop Description</label></th>
                    <td><textarea name="workshop_description" id="workshop_description" class="large-text" rows="5"
                        placeholder="Description that will appear on the workshop dashboard"><?php 
                        echo $workshop ? esc_textarea($workshop->workshop_description) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="materials_files">Materials & Files</label></th>
                    <td><textarea name="materials_files" id="materials_files" class="large-text" rows="5"
                        placeholder="Enter file URLs or upload instructions, one per line"><?php 
                        echo $workshop ? esc_textarea($workshop->materials_files) : ''; ?></textarea>
                        <p class="description">Enter file URLs or instructions for downloading materials. Each line will be a separate item.</p>
                    </td>
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
                <?php else: ?>
                    <a href="?page=cfp-workshops-signins&action=export" 
                       class="button">Export All Sign-ins to CSV</a>
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
                    <th><label for="activities_helpful_rating">The activities incorporated in the program helped illustrate how the new Code and Standards would be applied <span class="required">*</span></label></th>
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
                    <th><label for="instructor_knowledgeable_rating">The instructor was knowledgeable about the new Code and Standards <span class="required">*</span></label></th>
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
                    <th><label for="overall_rating">How many stars would you give this program? <span class="required">*</span></label></th>
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
function cfpew_export_signins($workshop_id = null) {
    global $wpdb;
    $signins_table = $wpdb->prefix . 'cfp_workshop_signins';
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    // Get workshop info if specific workshop
    $workshop = null;
    if ($workshop_id) {
        $workshop = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $workshops_table WHERE id = %d", 
            $workshop_id
        ));
        
        if (!$workshop) {
            wp_die('Workshop not found');
        }
    }
    
    // Get sign-ins with workshop info
    $query = "SELECT s.*, w.seminar_date, w.customer, w.instructor, w.location, w.time_location 
              FROM $signins_table s 
              LEFT JOIN $workshops_table w ON s.workshop_id = w.id";
    
    if ($workshop_id) {
        $query .= $wpdb->prepare(" WHERE s.workshop_id = %d", $workshop_id);
    }
    
    $query .= " ORDER BY w.seminar_date DESC, s.last_name, s.first_name";
    
    $signins = $wpdb->get_results($query);
    
    // Set headers for CSV download
    $filename = $workshop_id 
        ? 'cfp-ethics-signins-' . $workshop->seminar_date . '-' . sanitize_title($workshop->customer) . '.csv'
        : 'cfp-ethics-signins-all-workshops-' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    if ($workshop_id) {
        // Add workshop info at top for single workshop export
        fputcsv($output, array('CFP Ethics Workshop Sign-in Report'));
        fputcsv($output, array('Workshop Date:', $workshop->seminar_date));
        fputcsv($output, array('FPA Chapter:', $workshop->customer));
        fputcsv($output, array('Instructor:', $workshop->instructor));
        fputcsv($output, array('Total Attendees:', count($signins)));
        fputcsv($output, array('')); // Empty row
    }
    
    // Column headers
    $headers = array(
        'Workshop Date',
        'FPA Chapter',
        'Instructor',
        'Location',
        'Time/Location Details',
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
    );
    
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($signins as $signin) {
        fputcsv($output, array(
            $signin->seminar_date,
            $signin->customer,
            $signin->instructor,
            $signin->location,
            $signin->time_location,
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
    exit;
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
            'location' => isset($data[12]) ? $data[12] : '',
            'all_materials_sent' => !empty($data[14]) ? date('Y-m-d', strtotime($data[14])) : null,
            'attendees_count' => isset($data[15]) ? intval($data[15]) : 0,
            'roster_received' => isset($data[16]) ? $data[16] : '',
            'batch_number' => isset($data[17]) ? intval($data[17]) : 0,
            'batch_date' => !empty($data[18]) ? date('Y-m-d', strtotime($data[18])) : null,
            'invoice_sent_flag' => isset($data[20]) ? 1 : 0,
            'invoice_sent' => (isset($data[20]) && !empty($data[20])) ? sanitize_text_field($data[20]) : null,
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

// =============================================================================
// WORKSHOP MATERIALS GENERATION SYSTEM
// =============================================================================

// Templates management page
function cfpew_templates_page() {
    global $wpdb;
    $templates_table = $wpdb->prefix . 'cfp_workshop_templates';
    
    // Note: Template download action is now handled early in admin_init hook
    
    // Debug information
    $debug_info = array();
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $debug_info[] = 'POST request detected';
        $debug_info[] = 'Upload template button: ' . (isset($_POST['cfpew_upload_template']) ? 'YES' : 'NO');
        $debug_info[] = 'Nonce present: ' . (isset($_POST['cfpew_template_nonce']) ? 'YES' : 'NO');
        $debug_info[] = 'File present: ' . (isset($_FILES['template_file']) ? 'YES' : 'NO');
        if (isset($_FILES['template_file'])) {
            $debug_info[] = 'File error code: ' . $_FILES['template_file']['error'];
            $debug_info[] = 'File size: ' . $_FILES['template_file']['size'] . ' bytes';
            $debug_info[] = 'File name: ' . $_FILES['template_file']['name'];
        }
    }
    
    // Handle template upload - check for either button or form submission indicators
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['cfpew_upload_template']) || (isset($_POST['cfpew_template_nonce']) && isset($_FILES['template_file'])))) {
        cfpew_handle_template_upload();
    } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
        echo '<div class="notice notice-warning"><p>POST request received but upload not processed. Debug info: ' . implode(', ', $debug_info) . '</p></div>';
    }
    
    // Handle template deletion
    if (isset($_GET['action']) && $_GET['action'] == 'delete_template' && isset($_GET['template_id'])) {
        cfpew_delete_template(intval($_GET['template_id']));
    }
    
    // Handle manual table creation
    if (isset($_GET['action']) && $_GET['action'] == 'create_tables' && isset($_GET['cfpew_nonce']) && wp_verify_nonce($_GET['cfpew_nonce'], 'cfpew_create_tables')) {
        cfpew_create_tables();
        echo '<div class="notice notice-success"><p>Database tables created/updated successfully!</p></div>';
    }
    
    // Check if templates table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$templates_table'") == $templates_table;
    
    // Get existing templates
    $templates = array();
    if ($table_exists) {
        $templates = $wpdb->get_results("SELECT * FROM $templates_table ORDER BY template_type, template_name");
    }
    
    ?>
    <div class="wrap">
        <h1>Workshop Materials Templates</h1>
        <p class="description">Upload and manage PDF and PowerPoint templates for workshop materials generation.</p>
        
        <?php if (!$table_exists): ?>
            <!-- Database Fix Notice -->
            <div class="notice notice-error">
                <p><strong>Database Table Missing!</strong> The templates table doesn't exist.<br>
                <a href="<?php echo wp_nonce_url(add_query_arg('action', 'create_tables'), 'cfpew_create_tables', 'cfpew_nonce'); ?>" 
                   class="button button-primary">Create Database Tables</a></p>
            </div>
        <?php endif; ?>
        
        <!-- Server Information -->
        <div class="notice notice-info">
            <p><strong>Server Upload Limits:</strong><br>
            Max Upload Size: <?php echo round(cfpew_get_max_upload_size() / 1024 / 1024, 1); ?>MB 
            (upload_max_filesize: <?php echo ini_get('upload_max_filesize'); ?>, 
            post_max_size: <?php echo ini_get('post_max_size'); ?>, 
            memory_limit: <?php echo ini_get('memory_limit'); ?>)
            </p>
        </div>
        
        <div class="cfp-templates-container">
            <!-- Upload Section -->
            <div class="cfp-upload-section">
                <h2>Upload New Template</h2>
                <?php if ($table_exists): ?>
                <form method="post" enctype="multipart/form-data" class="cfp-upload-form">
                <?php else: ?>
                <div class="notice notice-warning inline"><p>Please create the database tables first before uploading templates.</p></div>
                <form method="post" enctype="multipart/form-data" class="cfp-upload-form" style="opacity:0.5; pointer-events:none;">
                <?php endif; ?>
                    <?php wp_nonce_field('cfpew_upload_template', 'cfpew_template_nonce'); ?>
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo cfpew_get_max_upload_size(); ?>">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="template_name">Template Name</label></th>
                            <td>
                                <input type="text" name="template_name" id="template_name" class="regular-text" required>
                                <p class="description">Give your template a descriptive name (e.g., "Workshop Course Materials PDF")</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="template_type">Template Type</label></th>
                            <td>
                                <select name="template_type" id="template_type" required>
                                    <option value="">Select type...</option>
                                    <option value="pdf">PDF Template</option>
                                    <option value="powerpoint">PowerPoint Template</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="template_file">Template File</label></th>
                            <td>
                                <input type="file" name="template_file" id="template_file" accept=".pdf,.ppt,.pptx" required>
                                <p class="description">
                                    Upload your PDF (.pdf) or PowerPoint (.ppt, .pptx) template file<br>
                                    <strong>Maximum file size: <?php echo round(cfpew_get_max_upload_size() / 1024 / 1024, 1); ?>MB</strong>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="field_mappings">Field Mappings</label></th>
                            <td>
                                <textarea name="field_mappings" id="field_mappings" class="large-text" rows="10" placeholder="Enter field mappings (one per line):
{{workshop_date}} - Workshop date
{{workshop_time}} - Workshop time  
{{location}} - Workshop location
{{instructor_name}} - Instructor name
{{chapter_name}} - Chapter/customer name
{{contact_email}} - Contact email
{{contact_phone}} - Contact phone
{{workshop_cost}} - Workshop cost
{{registration_link}} - Registration link
{{webinar_link}} - Webinar link"></textarea>
                                <p class="description">Define which placeholders in your template should be replaced with workshop data</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="cfpew_upload_template" class="button-primary" value="Upload Template" id="cfp-upload-btn">
                        <span id="cfp-upload-progress" style="display:none; margin-left: 10px;">
                            <span class="spinner is-active"></span> Uploading... Please wait.
                        </span>
                    </p>
                </form>
            </div>
            
            <!-- Templates List -->
            <div class="cfp-templates-list">
                <h2>Existing Templates</h2>
                
                <?php if ($templates): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Template Name</th>
                                <th>Type</th>
                                <th>Original Filename</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><strong><?php echo esc_html($template->template_name); ?></strong></td>
                                <td>
                                    <span class="cfp-template-type <?php echo esc_attr($template->template_type); ?>">
                                        <?php echo ucfirst($template->template_type); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($template->original_filename); ?></td>
                                <td><?php echo date('M j, Y', strtotime($template->upload_date)); ?></td>
                                <td>
                                    <a href="?page=cfp-workshops-templates&action=download_template&template_id=<?php echo $template->id; ?>" 
                                       class="button button-small">Download</a>
                                    <a href="?page=cfp-workshops-templates&action=delete_template&template_id=<?php echo $template->id; ?>" 
                                       class="button button-small" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif ($table_exists): ?>
                    <div class="notice notice-info">
                        <p>No templates uploaded yet. Upload your first template above to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning">
                        <p>Database table not found. Please create the database tables first.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .cfp-templates-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .cfp-upload-section, .cfp-templates-list {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
        }
        
        .cfp-upload-section h2, .cfp-templates-list h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .cfp-template-type {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .cfp-template-type.pdf {
            background: #dc3545;
            color: white;
        }
        
        .cfp-template-type.powerpoint {
            background: #d63384;
            color: white;
        }
        
        @media (max-width: 1200px) {
            .cfp-templates-container {
                grid-template-columns: 1fr;
            }
        }
        
        #cfp-upload-progress .spinner {
            float: none;
            margin: 0 5px 0 0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#cfp-upload-btn').closest('form').on('submit', function(e) {
                var $form = $(this);
                var fileInput = $('#template_file')[0];
                
                if (fileInput.files.length > 0) {
                    var fileSize = fileInput.files[0].size;
                    var maxSize = <?php echo cfpew_get_max_upload_size(); ?>;
                    
                    if (fileSize > maxSize) {
                        var maxMB = Math.round(maxSize / 1024 / 1024 * 10) / 10;
                        var fileMB = Math.round(fileSize / 1024 / 1024 * 10) / 10;
                        alert('File too large (' + fileMB + 'MB). Maximum size: ' + maxMB + 'MB');
                        return false;
                    }
                    
                    // Add hidden field to ensure upload is detected
                    if (!$form.find('input[name="cfpew_upload_template"]').length) {
                        $form.append('<input type="hidden" name="cfpew_upload_template" value="1">');
                    }
                    
                    // Show progress but don't disable button until after a brief delay
                    $('#cfp-upload-progress').show();
                    setTimeout(function() {
                        $('#cfp-upload-btn').prop('disabled', true).val('Uploading...');
                    }, 100);
                }
            });
        });
        </script>
    </div>
    <?php
}

// Handle template upload
function cfpew_handle_template_upload() {
    // Log upload attempt
    error_log('CFP Workshop: Template upload attempt started');
    
    if (!isset($_POST['cfpew_template_nonce']) || !wp_verify_nonce($_POST['cfpew_template_nonce'], 'cfpew_upload_template')) {
        error_log('CFP Workshop: Nonce verification failed');
        echo '<div class="notice notice-error"><p>Security check failed. Please try again.</p></div>';
        return;
    }
    
    error_log('CFP Workshop: Nonce verification passed');
    
    // Check if file was uploaded
    if (!isset($_FILES['template_file'])) {
        error_log('CFP Workshop: No file in $_FILES array');
        echo '<div class="notice notice-error"><p>No file was selected for upload.</p></div>';
        return;
    }
    
    $uploaded_file = $_FILES['template_file'];
    error_log('CFP Workshop: File upload details - ' . print_r($uploaded_file, true));
    
    // Detailed error checking
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        $error_message = cfpew_get_upload_error_message($uploaded_file['error']);
        error_log('CFP Workshop: Upload error - Code: ' . $uploaded_file['error'] . ', Message: ' . $error_message);
        echo '<div class="notice notice-error"><p>' . $error_message . '</p></div>';
        return;
    }
    
    // Check file size (default limit and actual size)
    $max_size = cfpew_get_max_upload_size();
    if ($uploaded_file['size'] > $max_size) {
        $max_mb = round($max_size / 1024 / 1024, 1);
        $actual_mb = round($uploaded_file['size'] / 1024 / 1024, 1);
        echo '<div class="notice notice-error"><p>File too large. Maximum size: ' . $max_mb . 'MB, your file: ' . $actual_mb . 'MB</p></div>';
        return;
    }
    
    // Validate file type
    $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    $template_type = sanitize_text_field($_POST['template_type']);
    
    $allowed_extensions = array('pdf', 'ppt', 'pptx');
    if (!in_array($file_extension, $allowed_extensions)) {
        echo '<div class="notice notice-error"><p>Invalid file type: .' . esc_html($file_extension) . '. Please upload PDF, PPT, or PPTX files only.</p></div>';
        return;
    }
    
    // Validate template type matches file
    if ($template_type == 'pdf' && $file_extension !== 'pdf') {
        echo '<div class="notice notice-error"><p>Template type "PDF" selected but file is .' . esc_html($file_extension) . '</p></div>';
        return;
    }
    if ($template_type == 'powerpoint' && !in_array($file_extension, array('ppt', 'pptx'))) {
        echo '<div class="notice notice-error"><p>Template type "PowerPoint" selected but file is .' . esc_html($file_extension) . '</p></div>';
        return;
    }
    
    // Create uploads directory
    $upload_dir = wp_upload_dir();
    $cfp_dir = $upload_dir['basedir'] . '/cfp-workshop-templates/';
    
    if (!file_exists($cfp_dir)) {
        if (!wp_mkdir_p($cfp_dir)) {
            echo '<div class="notice notice-error"><p>Could not create upload directory. Please check permissions.</p></div>';
            return;
        }
    }
    
    // Check directory permissions
    if (!is_writable($cfp_dir)) {
        echo '<div class="notice notice-error"><p>Upload directory is not writable. Please check permissions.</p></div>';
        return;
    }
    
    // Generate unique filename
    $filename = uniqid('cfp_template_') . '.' . $file_extension;
    $file_path = $cfp_dir . $filename;
    
    // Attempt to move uploaded file
    if (move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
        // Verify file was actually saved
        if (!file_exists($file_path)) {
            echo '<div class="notice notice-error"><p>File upload completed but file not found. Please try again.</p></div>';
            return;
        }
        
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cfp_workshop_templates';
        
        $result = $wpdb->insert($templates_table, array(
            'template_name' => sanitize_text_field($_POST['template_name']),
            'template_type' => $template_type,
            'file_path' => $file_path,
            'original_filename' => sanitize_file_name($uploaded_file['name']),
            'field_mappings' => sanitize_textarea_field($_POST['field_mappings']),
            'upload_date' => current_time('mysql')
        ));
        
        if ($result) {
            $file_mb = round(filesize($file_path) / 1024 / 1024, 1);
            echo '<div class="notice notice-success"><p>Template uploaded successfully! File size: ' . $file_mb . 'MB</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Database error: Could not save template information. Error: ' . $wpdb->last_error . '</p></div>';
            // Clean up uploaded file if database insert failed
            unlink($file_path);
        }
    } else {
        echo '<div class="notice notice-error"><p>Failed to save uploaded file. Error details:<br>';
        echo 'Temporary file: ' . esc_html($uploaded_file['tmp_name']) . '<br>';
        echo 'Target path: ' . esc_html($file_path) . '<br>';
        echo 'Directory writable: ' . (is_writable($cfp_dir) ? 'Yes' : 'No') . '<br>';
        echo 'Please check server permissions and try again.</p></div>';
    }
}

// Get upload error message
function cfpew_get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File is too large (exceeds server upload_max_filesize limit)';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File is too large (exceeds form MAX_FILE_SIZE limit)';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Server missing temporary upload directory';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk (server permissions issue)';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload stopped by server extension';
        default:
            return 'Unknown upload error (code: ' . $error_code . ')';
    }
}

// Get maximum upload size
function cfpew_get_max_upload_size() {
    $max_upload = cfpew_parse_size(ini_get('upload_max_filesize'));
    $max_post = cfpew_parse_size(ini_get('post_max_size'));
    $memory_limit = cfpew_parse_size(ini_get('memory_limit'));
    
    return min($max_upload, $max_post, $memory_limit);
}

// Parse size string (like "32M") to bytes
function cfpew_parse_size($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    } else {
        return round($size);
    }
}

// Delete template
function cfpew_delete_template($template_id) {
    global $wpdb;
    $templates_table = $wpdb->prefix . 'cfp_workshop_templates';
    
    $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $templates_table WHERE id = %d", $template_id));
    
    if ($template) {
        // Delete file
        if (file_exists($template->file_path)) {
            unlink($template->file_path);
        }
        
        // Delete database record
        $wpdb->delete($templates_table, array('id' => $template_id));
        echo '<div class="notice notice-success"><p>Template deleted successfully!</p></div>';
    }
}

// Generate workshop materials
function cfpew_generate_workshop_materials($workshop_id) {
    global $wpdb;
    
    // Get workshop data
    $workshop = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cfp_workshops WHERE id = %d", 
        $workshop_id
    ));
    
    if (!$workshop) {
        wp_die('Workshop not found');
    }
    
    // Get available templates
    $templates = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}cfp_workshop_templates WHERE is_active = 1 ORDER BY template_type"
    );
    
    if (empty($templates)) {
        wp_die('No templates available. Please upload templates first.');
    }
    
    // Include required libraries
    cfpew_include_pdf_libraries();
    
    $generated_files = array();
    $has_errors = false;
    
    foreach ($templates as $template) {
        try {
            if ($template->template_type == 'pdf') {
                $result = cfpew_generate_pdf_materials($workshop, $template);
            } else {
                $result = cfpew_generate_powerpoint_materials($workshop, $template);
            }
            
            if ($result) {
                $generated_files[] = $result;
            }
        } catch (Exception $e) {
            $has_errors = true;
            error_log('CFP Workshop Materials Generation Error: ' . $e->getMessage());
        }
    }
    
    if (empty($generated_files)) {
        wp_die('Failed to generate materials. Please check your templates and try again.');
    }
    
    // If multiple files, create ZIP
    if (count($generated_files) > 1) {
        $zip_file = cfpew_create_materials_zip($workshop, $generated_files);
        cfpew_download_file($zip_file, "workshop-materials-{$workshop->customer}-" . date('Y-m-d', strtotime($workshop->seminar_date)) . '.zip');
    } else {
        // Single file download
        $file = $generated_files[0];
        $filename = basename($file['path']);
        cfpew_download_file($file['path'], $filename);
    }
}

// Include PDF libraries (using TCPDF)
function cfpew_include_pdf_libraries() {
    // Check if composer autoloader exists
    $autoload_path = CFPEW_PLUGIN_PATH . 'vendor/autoload.php';
    if (file_exists($autoload_path)) {
        require_once $autoload_path;
    }
    
    // For now, we'll use native PHP functionality
    return true;
}

// Generate PDF materials
function cfpew_generate_pdf_materials($workshop, $template) {
    // Get template data
    $field_mappings = cfpew_parse_field_mappings($template->field_mappings);
    $replacement_data = cfpew_get_workshop_replacement_data($workshop);
    
    // For now, we'll create a simple text-based approach
    // In production, you'd use FPDI + TCPDF to overlay text on existing PDFs
    
    $upload_dir = wp_upload_dir();
    $output_dir = $upload_dir['basedir'] . '/cfp-generated-materials/';
    if (!file_exists($output_dir)) {
        wp_mkdir_p($output_dir);
    }
    
    $output_filename = 'workshop-materials-' . $workshop->id . '-' . uniqid() . '.pdf';
    $output_path = $output_dir . $output_filename;
    
    // Simple PDF generation (you would replace this with TCPDF/FPDI)
    $content = cfpew_generate_simple_pdf_content($workshop, $field_mappings, $replacement_data);
    
    // For demonstration, create a text file (replace with actual PDF generation)
    file_put_contents(str_replace('.pdf', '.txt', $output_path), $content);
    
    return array(
        'path' => str_replace('.pdf', '.txt', $output_path),
        'name' => str_replace('.pdf', '.txt', $output_filename),
        'type' => 'pdf'
    );
}

// Generate PowerPoint materials
function cfpew_generate_powerpoint_materials($workshop, $template) {
    // DEBUG: Test logging first
    error_log('=== CFP WORKSHOP DEBUG TEST - PowerPoint Generation Started ===');
    error_log('Debug log location test - Current time: ' . current_time('mysql'));
    error_log('WordPress debug log path: ' . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'ENABLED' : 'DISABLED'));
    
    $field_mappings = cfpew_parse_field_mappings($template->field_mappings);
    $replacement_data = cfpew_get_workshop_replacement_data($workshop);
    
    // DEBUG: Log the workshop data and replacement data for troubleshooting
    error_log('PowerPoint Generation - Workshop ID: ' . $workshop->id);
    error_log('PowerPoint Generation - Workshop customer: ' . $workshop->customer);
    error_log('PowerPoint Generation - Workshop seminar_date: ' . $workshop->seminar_date);
    error_log('PowerPoint Generation - Workshop instructor: ' . $workshop->instructor);
    error_log('PowerPoint Generation - Replacement data: ' . print_r($replacement_data, true));
    error_log('PowerPoint Generation - Field mappings: ' . print_r($field_mappings, true));
    
    $upload_dir = wp_upload_dir();
    $output_dir = $upload_dir['basedir'] . '/cfp-generated-materials/';
    if (!file_exists($output_dir)) {
        wp_mkdir_p($output_dir);
    }
    
    $output_filename = 'workshop-slides-' . $workshop->id . '-' . uniqid() . '.pptx';
    $output_path = $output_dir . $output_filename;
    
    // Try to process the actual PowerPoint template
    if (cfpew_process_powerpoint_template($template->file_path, $output_path, $replacement_data)) {
        return array(
            'path' => $output_path,
            'name' => $output_filename,
            'type' => 'powerpoint'
        );
    } else {
        // Fallback: Generate a new PowerPoint from scratch
        if (cfpew_create_powerpoint_from_scratch($output_path, $workshop, $replacement_data)) {
            return array(
                'path' => $output_path,
                'name' => $output_filename,
                'type' => 'powerpoint'
            );
        }
        
        // Final fallback: Enhanced text content for debugging
        $content = cfpew_generate_detailed_ppt_content($workshop, $field_mappings, $replacement_data);
        file_put_contents(str_replace('.pptx', '.txt', $output_path), $content);
        
        return array(
            'path' => str_replace('.pptx', '.txt', $output_path),
            'name' => str_replace('.pptx', '.txt', $output_filename),
            'type' => 'powerpoint'
        );
    }
}

// Parse field mappings from template
function cfpew_parse_field_mappings($mappings_text) {
    $mappings = array();
    $lines = explode("\n", $mappings_text);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '{{') === false) continue;
        
        // Extract placeholder like {{workshop_date}}
        preg_match('/\{\{([^}]+)\}\}/', $line, $matches);
        if (isset($matches[1])) {
            $placeholder = '{{' . $matches[1] . '}}';
            $description = trim(str_replace($placeholder, '', $line));
            $mappings[$matches[1]] = array(
                'placeholder' => $placeholder,
                'description' => $description
            );
        }
    }
    
    return $mappings;
}

// Get workshop data for replacements
function cfpew_get_workshop_replacement_data($workshop) {
    return array(
        'workshop_date' => date('F j, Y', strtotime($workshop->seminar_date)),
        'workshop_time' => $workshop->time_location,
        'location' => $workshop->location ?: $workshop->time_location,
        'instructor_name' => $workshop->instructor,
        'chapter_name' => $workshop->customer,
        'contact_email' => $workshop->email,
        'contact_phone' => $workshop->phone,
        'contact_name' => $workshop->contact_name,
        'workshop_cost' => $workshop->workshop_cost ? '$' . number_format($workshop->workshop_cost, 2) : '',
        'webinar_link' => $workshop->webinar_signin_link,
        'workshop_description' => $workshop->workshop_description,
        'instructor_cfp_id' => $workshop->instructor_cfp_id,
        'attendees_count' => $workshop->attendees_count
    );
}

// Generate simple PDF content (placeholder)
function cfpew_generate_simple_pdf_content($workshop, $mappings, $data) {
    $content = "WORKSHOP MATERIALS\n";
    $content .= "==================\n\n";
    $content .= "Generated from template with workshop data:\n\n";
    
    foreach ($data as $key => $value) {
        if (!empty($value)) {
            $content .= ucwords(str_replace('_', ' ', $key)) . ": " . $value . "\n";
        }
    }
    
    $content .= "\n\n[This is a placeholder. In production, this would be a properly formatted PDF with your template design and the workshop data overlaid on the specified fields.]\n";
    
    return $content;
}

// Process PowerPoint template with real data replacement
function cfpew_process_powerpoint_template($template_path, $output_path, $replacement_data) {
    if (!file_exists($template_path)) {
        return false;
    }
    
    try {
        // PowerPoint files are ZIP archives
        $zip = new ZipArchive();
        
        if ($zip->open($template_path) !== TRUE) {
            return false;
        }
        
        // Extract template to temporary directory
        $temp_dir = sys_get_temp_dir() . '/cfp_ppt_' . uniqid();
        $zip->extractTo($temp_dir);
        $zip->close();
        
        // Process slide content files
        $slide_files = glob($temp_dir . '/ppt/slides/slide*.xml');
        $processed = false;
        
        foreach ($slide_files as $slide_file) {
            if (cfpew_process_slide_xml($slide_file, $replacement_data)) {
                $processed = true;
            }
        }
        
        // Also process slide masters and layouts
        $master_files = glob($temp_dir . '/ppt/slideMasters/slideMaster*.xml');
        foreach ($master_files as $master_file) {
            cfpew_process_slide_xml($master_file, $replacement_data);
        }
        
        $layout_files = glob($temp_dir . '/ppt/slideLayouts/slideLayout*.xml');
        foreach ($layout_files as $layout_file) {
            cfpew_process_slide_xml($layout_file, $replacement_data);
        }
        
        if ($processed) {
            // Recreate the PowerPoint file
            $new_zip = new ZipArchive();
            if ($new_zip->open($output_path, ZipArchive::CREATE) === TRUE) {
                cfpew_add_directory_to_zip($new_zip, $temp_dir, '');
                $new_zip->close();
                
                // Clean up temporary directory
                cfpew_remove_directory($temp_dir);
                return true;
            }
        }
        
        // Clean up temporary directory
        cfpew_remove_directory($temp_dir);
        
    } catch (Exception $e) {
        error_log('PowerPoint processing error: ' . $e->getMessage());
    }
    
    return false;
}

// Process individual slide XML files
function cfpew_process_slide_xml($slide_file, $replacement_data) {
    if (!file_exists($slide_file)) {
        return false;
    }

    $content = file_get_contents($slide_file);
    $original_content = $content;
    
    // DEBUG: Log replacement data for troubleshooting
    error_log('PowerPoint Processing - Available replacement data: ' . print_r($replacement_data, true));
    error_log('PowerPoint Processing - Processing file: ' . $slide_file);
    
    // DEBUG: Log what placeholders are actually in the file
    preg_match_all('/\{\{[^}]+\}\}/', $content, $found_placeholders);
    if (!empty($found_placeholders[0])) {
        error_log('PowerPoint Processing - Placeholders found in file: ' . implode(', ', array_unique($found_placeholders[0])));
    } else {
        error_log('PowerPoint Processing - No {{placeholder}} patterns found in file');
    }
    
    // Also check for other common patterns
    preg_match_all('/\{[^}]+\}/', $content, $single_brace);
    preg_match_all('/\[[^\]]+\]/', $content, $square_brackets);
    preg_match_all('/%[^%]+%/', $content, $percent_signs);
    
    if (!empty($single_brace[0])) {
        error_log('PowerPoint Processing - Single brace patterns found: ' . implode(', ', array_unique($single_brace[0])));
    }
    if (!empty($square_brackets[0])) {
        error_log('PowerPoint Processing - Square bracket patterns found: ' . implode(', ', array_unique($square_brackets[0])));
    }
    if (!empty($percent_signs[0])) {
        error_log('PowerPoint Processing - Percent sign patterns found: ' . implode(', ', array_unique($percent_signs[0])));
    }
    
    $replacements_made = array();
    
    // Replace placeholders in the XML content
    foreach ($replacement_data as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        if (strpos($content, $placeholder) !== false) {
            $content = str_replace($placeholder, htmlspecialchars($value), $content);
            $replacements_made[] = $placeholder . ' -> ' . $value;
        }
    }

    // Also try common variations
    foreach ($replacement_data as $key => $value) {
        $variations = array(
            '{{ ' . $key . ' }}',
            '{{' . strtoupper($key) . '}}',
            '{{ ' . strtoupper($key) . ' }}',
            '{' . $key . '}',
            '[' . $key . ']',
            '%' . $key . '%'
        );
        
        foreach ($variations as $variation) {
            if (strpos($content, $variation) !== false) {
                $content = str_replace($variation, htmlspecialchars($value), $content);
                $replacements_made[] = $variation . ' -> ' . $value;
            }
        }
    }
    
    // DEBUG: Log what replacements were made
    if (!empty($replacements_made)) {
        error_log('PowerPoint Processing - Replacements made: ' . implode(', ', $replacements_made));
    } else {
        error_log('PowerPoint Processing - No replacements made in file: ' . $slide_file);
    }

    if ($content !== $original_content) {
        file_put_contents($slide_file, $content);
        return true;
    }

    return false;
}

// Create PowerPoint from scratch if template processing fails
function cfpew_create_powerpoint_from_scratch($output_path, $workshop, $replacement_data) {
    // Create a minimal PowerPoint structure
    $ppt_structure = cfpew_get_minimal_pptx_structure($workshop, $replacement_data);
    
    if (cfpew_create_pptx_zip($output_path, $ppt_structure)) {
        return true;
    }
    
    return false;
}

// Helper function to add directory to ZIP
function cfpew_add_directory_to_zip($zip, $dir, $zip_path) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $file_path = $file->getRealPath();
            $relative_path = $zip_path . substr($file_path, strlen($dir) + 1);
            $relative_path = str_replace('\\', '/', $relative_path);
            $zip->addFile($file_path, $relative_path);
        }
    }
}

// Helper function to remove directory
function cfpew_remove_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            cfpew_remove_directory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Generate detailed PowerPoint content for fallback
function cfpew_generate_detailed_ppt_content($workshop, $mappings, $data) {
    $content = "WORKSHOP PRESENTATION SLIDES - DETAILED GENERATION LOG\n";
    $content .= "=====================================================\n\n";
    $content .= "Template Processing Result: PowerPoint generation attempted\n";
    $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    $content .= "WORKSHOP DETAILS:\n";
    $content .= "-----------------\n";
    foreach ($data as $key => $value) {
        if (!empty($value)) {
            $content .= sprintf("%-20s: %s\n", ucwords(str_replace('_', ' ', $key)), $value);
        }
    }
    
    $content .= "\nFIELD MAPPINGS FOUND:\n";
    $content .= "---------------------\n";
    if (!empty($mappings)) {
        foreach ($mappings as $key => $mapping) {
            $content .= sprintf("%-20s: %s\n", $mapping['placeholder'], $mapping['description']);
        }
    } else {
        $content .= "No field mappings defined in template.\n";
    }
    
    $content .= "\nNOTE: This is a fallback text file. To generate actual PowerPoint files:\n";
    $content .= "1. Ensure your PowerPoint template contains placeholders like {{workshop_date}}\n";
    $content .= "2. Verify the template file is a valid .pptx file\n";
    $content .= "3. Check server has ZipArchive extension enabled\n";
    $content .= "4. For advanced features, install PHPPresentation via Composer\n\n";
    
    $content .= "TROUBLESHOOTING:\n";
    $content .= "----------------\n";
    $content .= "- Template path: exists and readable\n";
    $content .= "- PHP ZipArchive: " . (class_exists('ZipArchive') ? 'Available' : 'NOT AVAILABLE') . "\n";
    $content .= "- Temp directory: " . (is_writable(sys_get_temp_dir()) ? 'Writable' : 'NOT WRITABLE') . "\n";
    $content .= "- Output directory: " . (is_writable(dirname($workshop->id)) ? 'Writable' : 'Check permissions') . "\n";
    
    return $content;
}

// Get minimal PowerPoint structure for creating from scratch
function cfpew_get_minimal_pptx_structure($workshop, $replacement_data) {
    $structure = array();
    
    // [Content_Types].xml
    $structure['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-presentationml.presentation.main+xml"/>
    <Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-presentationml.slide+xml"/>
    <Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
</Types>';

    // _rels/.rels
    $structure['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>';

    // ppt/_rels/presentation.xml.rels
    $structure['ppt/_rels/presentation.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
</Relationships>';

    // ppt/presentation.xml
    $structure['ppt/presentation.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
    <p:sldMasterIdLst/>
    <p:sldIdLst>
        <p:sldId id="256" r:id="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>
    </p:sldIdLst>
    <p:sldSz cx="9144000" cy="6858000"/>
</p:presentation>';

    // ppt/slides/slide1.xml with workshop data
    $slide_content = cfpew_generate_slide_xml($workshop, $replacement_data);
    $structure['ppt/slides/slide1.xml'] = $slide_content;

    // ppt/theme/theme1.xml (basic theme)
    $structure['ppt/theme/theme1.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">
    <a:themeElements>
        <a:clrScheme name="Office">
            <a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>
            <a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>
        </a:clrScheme>
        <a:fontScheme name="Office">
            <a:majorFont><a:latin typeface="Calibri Light"/></a:majorFont>
            <a:minorFont><a:latin typeface="Calibri"/></a:minorFont>
        </a:fontScheme>
        <a:fmtScheme name="Office"/>
    </a:themeElements>
</a:theme>';

    return $structure;
}

// Generate slide XML with workshop data
function cfpew_generate_slide_xml($workshop, $replacement_data) {
    $title = !empty($replacement_data['chapter_name']) ? $replacement_data['chapter_name'] . ' Workshop' : 'Ethics Workshop';
    $date = !empty($replacement_data['workshop_date']) ? $replacement_data['workshop_date'] : '';
    $instructor = !empty($replacement_data['instructor_name']) ? 'Instructor: ' . $replacement_data['instructor_name'] : '';
    $location = !empty($replacement_data['location']) ? 'Location: ' . $replacement_data['location'] : '';
    
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <p:cSld>
        <p:spTree>
            <p:nvGrpSpPr>
                <p:cNvPr id="1" name=""/>
                <p:cNvGrpSpPr/>
                <p:nvPr/>
            </p:nvGrpSpPr>
            <p:grpSpPr>
                <a:xfrm>
                    <a:off x="0" y="0"/>
                    <a:ext cx="0" cy="0"/>
                    <a:chOff x="0" y="0"/>
                    <a:chExt cx="0" cy="0"/>
                </a:xfrm>
            </p:grpSpPr>
            <p:sp>
                <p:nvSpPr>
                    <p:cNvPr id="2" name="Title"/>
                    <p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>
                    <p:nvPr><p:ph type="ctrTitle"/></p:nvPr>
                </p:nvSpPr>
                <p:spPr/>
                <p:txBody>
                    <a:bodyPr/>
                    <a:lstStyle/>
                    <a:p>
                        <a:r>
                            <a:rPr lang="en-US" sz="4400" b="1"/>
                            <a:t>' . htmlspecialchars($title) . '</a:t>
                        </a:r>
                    </a:p>
                </p:txBody>
            </p:sp>
            <p:sp>
                <p:nvSpPr>
                    <p:cNvPr id="3" name="Content"/>
                    <p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>
                    <p:nvPr><p:ph type="body" idx="1"/></p:nvPr>
                </p:nvSpPr>
                <p:spPr/>
                <p:txBody>
                    <a:bodyPr/>
                    <a:lstStyle/>
                    <a:p>
                        <a:r>
                            <a:rPr lang="en-US" sz="2800"/>
                            <a:t>' . htmlspecialchars($date) . '</a:t>
                        </a:r>
                    </a:p>
                    <a:p>
                        <a:r>
                            <a:rPr lang="en-US" sz="2400"/>
                            <a:t>' . htmlspecialchars($instructor) . '</a:t>
                        </a:r>
                    </a:p>
                    <a:p>
                        <a:r>
                            <a:rPr lang="en-US" sz="2400"/>
                            <a:t>' . htmlspecialchars($location) . '</a:t>
                        </a:r>
                    </a:p>
                </p:txBody>
            </p:sp>
        </p:spTree>
    </p:cSld>
</p:sld>';
}

// Create PPTX ZIP file from structure
function cfpew_create_pptx_zip($output_path, $structure) {
    $zip = new ZipArchive();
    
    if ($zip->open($output_path, ZipArchive::CREATE) !== TRUE) {
        return false;
    }
    
    foreach ($structure as $file_path => $content) {
        // Create directories if needed
        $dir_path = dirname($file_path);
        if ($dir_path !== '.' && $dir_path !== '') {
            $zip->addEmptyDir($dir_path);
        }
        
        $zip->addFromString($file_path, $content);
    }
    
    $zip->close();
    return true;
}

// Create ZIP file with multiple materials
function cfpew_create_materials_zip($workshop, $files) {
    $upload_dir = wp_upload_dir();
    $output_dir = $upload_dir['basedir'] . '/cfp-generated-materials/';
    
    $zip_filename = 'workshop-materials-' . $workshop->id . '-' . uniqid() . '.zip';
    $zip_path = $output_dir . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }
        $zip->close();
        return $zip_path;
    }
    
    return false;
}

// Download file
function cfpew_download_file($file_path, $download_name) {
    if (!file_exists($file_path)) {
        wp_die('File not found');
    }
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($file_path);
    
    // Clean up - delete generated file after download
    unlink($file_path);
    
    exit;
}

// Download template file (without deleting it)
function cfpew_download_template_file($file_path, $download_name) {
    if (!file_exists($file_path)) {
        wp_die('Template file not found');
    }
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($file_path);
    
    // Don't delete template files - they should persist
    exit;
}

// Admin Dashboard page
function cfpew_dashboard_page() {
    global $wpdb;
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    // Get filter parameters
    $show_upcoming = !isset($_GET['show_past']) || $_GET['show_past'] != '1';
    $chapter_filter = isset($_GET['chapter']) ? sanitize_text_field($_GET['chapter']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    // Build query
    $where_conditions = array();
    $query_params = array();
    
    if ($show_upcoming) {
        $where_conditions[] = "seminar_date >= CURDATE()";
    } else {
        $where_conditions[] = "seminar_date < CURDATE()";
    }
    
    if (!empty($chapter_filter)) {
        $where_conditions[] = "customer = %s";
        $query_params[] = $chapter_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $query = "SELECT * FROM $workshops_table $where_clause ORDER BY seminar_date DESC LIMIT %d";
    $query_params[] = $limit;
    
    $workshops = $wpdb->get_results($wpdb->prepare($query, $query_params));
    
    // Get unique chapters for filter
    $chapters = $wpdb->get_col("SELECT DISTINCT customer FROM $workshops_table ORDER BY customer");
    
    ?>
    <div class="wrap cfp-workshop-dashboard-admin">
        <!-- Removed the card/summary/info box (cfp-admin-header) -->
        <!-- Filters -->
        <div class="cfp-admin-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="cfp-workshops-dashboard">
                
                <div class="filter-group">
                    <label for="timeframe">Show:</label>
                    <select name="show_past" id="timeframe" onchange="this.form.submit()">
                        <option value="0" <?php selected(!isset($_GET['show_past']) || $_GET['show_past'] != '1'); ?>>Upcoming Workshops</option>
                        <option value="1" <?php selected(isset($_GET['show_past']) && $_GET['show_past'] == '1'); ?>>Past Workshops</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="chapter">Chapter:</label>
                    <select name="chapter" id="chapter" onchange="this.form.submit()">
                        <option value="">All Chapters</option>
                        <?php foreach ($chapters as $chapter): ?>
                            <option value="<?php echo esc_attr($chapter); ?>" <?php selected($chapter_filter, $chapter); ?>>
                                <?php echo esc_html($chapter); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="limit">Limit:</label>
                    <select name="limit" id="limit" onchange="this.form.submit()">
                        <option value="25" <?php selected($limit, 25); ?>>25</option>
                        <option value="50" <?php selected($limit, 50); ?>>50</option>
                        <option value="100" <?php selected($limit, 100); ?>>100</option>
                    </select>
                </div>
                
                <button type="submit" class="button">Apply Filters</button>
            </form>
        </div>
        
        <!-- Workshop Cards -->
        <?php if ($workshops): ?>
            <div class="cfp-admin-workshops-grid">
                <?php foreach ($workshops as $workshop): ?>
                    <div class="cfp-admin-workshop-card">
                        <div class="workshop-header">
                            <h3><?php echo esc_html($workshop->customer); ?></h3>
                            <div class="workshop-date">
                                <strong><?php echo date('F j, Y', strtotime($workshop->seminar_date)); ?></strong>
                                <?php if (strtotime($workshop->seminar_date) >= strtotime('today')): ?>
                                    <span class="status-badge upcoming">Upcoming</span>
                                <?php else: ?>
                                    <span class="status-badge past">Past</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="workshop-details">
                            <?php if ($workshop->instructor): ?>
                                <div class="detail-item">
                                    <strong>Instructor:</strong> <?php echo esc_html($workshop->instructor); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($workshop->location || $workshop->time_location): ?>
                                <div class="detail-item">
                                    <strong>Location:</strong> <?php echo esc_html($workshop->location ?: $workshop->time_location); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($workshop->attendees_count > 0): ?>
                                <div class="detail-item">
                                    <strong>Attendees:</strong> <?php echo esc_html($workshop->attendees_count); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($workshop->workshop_cost > 0): ?>
                                <div class="detail-item cost-item">
                                    <strong>Cost:</strong> $<?php echo number_format($workshop->workshop_cost, 2); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($workshop->workshop_description): ?>
                                <div class="detail-item description-item">
                                    <strong>Description:</strong>
                                    <div class="description-content">
                                        <?php echo nl2br(esc_html($workshop->workshop_description)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($workshop->materials_files): ?>
                            <div class="workshop-materials">
                                <h4>Workshop Materials</h4>
                                <div class="materials-list">
                                    <?php 
                                    $files = explode("\n", $workshop->materials_files);
                                    foreach ($files as $file) {
                                        $file = trim($file);
                                        if (empty($file)) continue;
                                        
                                        if (filter_var($file, FILTER_VALIDATE_URL)) {
                                            $filename = basename(parse_url($file, PHP_URL_PATH)) ?: 'Download File';
                                            echo '<div class="file-item">';
                                            echo '<a href="' . esc_url($file) . '" target="_blank" class="file-link">';
                                            echo '<span class="dashicons dashicons-media-document"></span> ' . esc_html($filename);
                                            echo '</a></div>';
                                        } else {
                                            echo '<div class="file-item">';
                                            echo '<span class="dashicons dashicons-media-document"></span> ' . esc_html($file);
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="workshop-contact">
                            <?php if ($workshop->email): ?>
                                <div class="contact-item">
                                    <strong>Contact:</strong> 
                                    <a href="mailto:<?php echo esc_attr($workshop->email); ?>">
                                        <?php echo esc_html($workshop->contact_name ?: $workshop->email); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($workshop->webinar_signin_link): ?>
                                <div class="contact-item">
                                    <a href="<?php echo esc_url($workshop->webinar_signin_link); ?>" target="_blank" class="button button-primary button-small">
                                        Join Webinar
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="workshop-actions">
                            <a href="?page=cfp-workshops-add&id=<?php echo $workshop->id; ?>" class="button button-small">Edit Workshop</a>
                            <a href="?page=cfp-workshops-signins&workshop_id=<?php echo $workshop->id; ?>" class="button button-small">View Sign-ins</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="cfp-no-workshops">
                <div class="notice notice-warning">
                    <p>No workshops found matching your criteria.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <style>
        .cfp-workshop-dashboard-admin {
            margin-right: 20px;
        }
        
        .cfp-admin-filters {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .cfp-admin-filters form {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 600;
            white-space: nowrap;
        }
        
        .cfp-admin-workshops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .cfp-admin-workshop-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .workshop-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .workshop-header h3 {
            margin: 0;
            color: #23282d;
            font-size: 18px;
        }
        
        .workshop-date {
            text-align: right;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 5px;
        }
        
        .status-badge.upcoming {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-badge.past {
            background: #f8d7da;
            color: #721c24;
        }
        
        .workshop-details {
            margin-bottom: 15px;
        }
        
        .detail-item {
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .cost-item {
            background: #e8f5e8;
            padding: 8px;
            border-radius: 3px;
            border-left: 3px solid #46b450;
        }
        
        .description-item {
            background: #f7f7f7;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
        }
        
        .description-content {
            margin-top: 5px;
            color: #555;
        }
        
        .workshop-materials {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 3px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .workshop-materials h4 {
            margin: 0 0 10px;
            color: #856404;
        }
        
        .materials-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
        }
        
        .file-link {
            color: #0073aa;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .file-link:hover {
            color: #005177;
        }
        
        .workshop-contact {
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .contact-item {
            margin-bottom: 8px;
        }
        
        .workshop-actions {
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .cfp-no-workshops {
            text-align: center;
            padding: 40px;
        }
        
        @media (max-width: 768px) {
            .cfp-admin-workshops-grid {
                grid-template-columns: 1fr;
            }
            
            .cfp-admin-filters form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
            }
        }
        </style>
    </div>
    <?php
}

// Shortcode for public sign-in form
add_shortcode('cfp_workshop_signin', 'cfpew_signin_shortcode');
add_shortcode('cfp_signin', 'cfpew_signin_shortcode'); // Alias for easier use

// Shortcode for workshop dashboard (commented out - using admin dashboard instead)
// add_shortcode('cfp_workshop_dashboard', 'cfpew_dashboard_shortcode');

// Debug: Log when shortcodes are registered
error_log('CFP Workshop: Shortcodes registered - cfp_workshop_signin and cfp_signin');
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
        
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="cfp_signin_submit">
            <?php wp_nonce_field('cfp_signin_nonce', 'cfp_signin_nonce'); ?>
            <?php wp_nonce_field('cfp_workshop_nonce', 'cfp_workshop_nonce'); ?>
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
                <label for="workshop_date">Date of Workshop <span class="required">*</span></label>
                <input type="date" name="workshop_date" id="workshop_date" required>
            </div>
            
            <div class="cfp-form-field" id="affiliation-field" style="display: none;">
                <label for="affiliation">Select Workshop <span class="required">*</span></label>
                <select name="affiliation" id="affiliation" required>
                    <option value="">-- Select Workshop --</option>
                </select>
                <div class="cfp-loading" id="affiliation-loading" style="display: none;">
                    Loading workshops for selected date...
                </div>
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
        .cfp-loading {
            font-style: italic;
            color: #666;
            margin-top: 5px;
            font-size: 14px;
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('CFP Workshop: Sign-in form JavaScript loaded');
            
            // Debug form submission
            const form = document.querySelector('.cfp-workshop-signin-form form');
            if (form) {
                console.log('CFP Workshop: Form found, action URL:', form.action);
                form.addEventListener('submit', function(e) {
                    console.log('CFP Workshop: Form being submitted to:', form.action);
                    console.log('CFP Workshop: Form method:', form.method);
                    console.log('CFP Workshop: Form data:', new FormData(form));
                });
            } else {
                console.log('CFP Workshop: ERROR - Form not found!');
            }
            
            // Handle workshop date selection
            const workshopDateInput = document.getElementById('workshop_date');
            const affiliationField = document.getElementById('affiliation-field');
            const affiliationSelect = document.getElementById('affiliation');
            const affiliationLoading = document.getElementById('affiliation-loading');
            
            if (workshopDateInput) {
                workshopDateInput.addEventListener('change', function() {
                    const selectedDate = this.value;
                    console.log('CFP Workshop: Date selected:', selectedDate);
                    
                    if (selectedDate) {
                        // Show loading
                        affiliationLoading.style.display = 'block';
                        affiliationField.style.display = 'block';
                        
                        // Clear existing options
                        affiliationSelect.innerHTML = '<option value="">-- Loading workshops... --</option>';
                        
                        // Get nonce value
                        const nonceField = document.querySelector('input[name="cfp_workshop_nonce"]');
                        const nonce = nonceField ? nonceField.value : '';
                        
                        // Make AJAX request
                        const formData = new FormData();
                        formData.append('action', 'cfp_get_workshops_by_date');
                        formData.append('date', selectedDate);
                        formData.append('nonce', nonce);
                        
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            console.log('CFP Workshop: AJAX response:', data);
                            affiliationLoading.style.display = 'none';
                            
                            if (data.success) {
                                // Clear and populate options
                                affiliationSelect.innerHTML = '<option value="">-- Select Workshop --</option>';
                                
                                data.data.forEach(function(workshop) {
                                    const option = document.createElement('option');
                                    option.value = workshop.value;
                                    option.textContent = workshop.label;
                                    affiliationSelect.appendChild(option);
                                });
                                
                                console.log('CFP Workshop: Loaded', data.data.length, 'workshops for date');
                            } else {
                                affiliationSelect.innerHTML = '<option value="">No workshops found for this date</option>';
                                console.log('CFP Workshop: No workshops found for date:', data.data);
                            }
                        })
                        .catch(error => {
                            console.error('CFP Workshop: AJAX error:', error);
                            affiliationLoading.style.display = 'none';
                            affiliationSelect.innerHTML = '<option value="">Error loading workshops</option>';
                        });
                    } else {
                        // Hide affiliation field if no date selected
                        affiliationField.style.display = 'none';
                        affiliationSelect.innerHTML = '<option value="">-- Select Workshop --</option>';
                    }
                });
            }
            
            // Handle star ratings
            const starRatings = document.querySelectorAll('.cfp-star-rating');
            console.log('CFP Workshop: Found', starRatings.length, 'star rating elements');
            
            starRatings.forEach(function(rating) {
                const stars = rating.querySelectorAll('.star');
                const field = rating.dataset.field;
                const input = document.getElementById(field + '_rating');
                
                console.log('CFP Workshop: Setting up rating for field:', field, 'Found', stars.length, 'stars, input element:', input);
                
                stars.forEach(function(star, index) {
                    star.addEventListener('click', function() {
                        const value = index + 1;
                        input.value = value;
                        console.log('Set ' + field + ' rating to: ' + value); // Debug log
                        
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
            
            // Add form validation
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('Form submitted'); // Debug log
                    
                    // Check if all required ratings are filled
                    const requiredRatings = [
                        'learning_objectives_rating',
                        'content_organized_rating',
                        'content_relevant_rating', 
                        'activities_helpful_rating',
                        'instructor_knowledgeable_rating',
                        'overall_rating'
                    ];
                    
                    let missingRatings = [];
                    requiredRatings.forEach(function(ratingName) {
                        const input = document.getElementById(ratingName);
                        if (!input.value || parseInt(input.value) < 1) {
                            missingRatings.push(ratingName.replace('_rating', '').replace('_', ' '));
                        }
                    });
                    
                    if (missingRatings.length > 0) {
                        e.preventDefault();
                        alert('Please provide ratings for all questions: ' + missingRatings.join(', '));
                        return false;
                    }
                    
                    console.log('All validations passed'); // Debug log
                });
            }
        });
        </script>
    </div>
    <?php
    
    return ob_get_clean();
}

// Workshop dashboard shortcode
function cfpew_dashboard_shortcode($atts) {
    global $wpdb;
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'show_upcoming' => 'true',
        'show_past' => 'false',
        'chapter' => '', // Filter by specific chapter
        'limit' => '10'
    ), $atts);
    
    // Build query based on attributes
    $where_conditions = array();
    $params = array();
    
    if ($atts['chapter']) {
        $where_conditions[] = "customer = %s";
        $params[] = $atts['chapter'];
    }
    
    if ($atts['show_upcoming'] === 'true' && $atts['show_past'] === 'false') {
        $where_conditions[] = "seminar_date >= %s";
        $params[] = current_time('Y-m-d');
    } elseif ($atts['show_past'] === 'true' && $atts['show_upcoming'] === 'false') {
        $where_conditions[] = "seminar_date < %s";
        $params[] = current_time('Y-m-d');
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "SELECT * FROM $workshops_table $where_clause ORDER BY seminar_date DESC LIMIT %d";
    $params[] = intval($atts['limit']);
    
    $workshops = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    
    ob_start();
    ?>
    <div class="cfp-workshop-dashboard">
        <div class="cfp-dashboard-header">
            <h2>CFP Ethics Workshops</h2>
            <p>Continuing Education for Certified Financial Planners</p>
        </div>
        
        <?php if ($workshops): ?>
            <div class="cfp-workshops-grid">
                <?php foreach ($workshops as $workshop): ?>
                    <div class="cfp-workshop-card">
                        <div class="cfp-workshop-header">
                            <h3><?php echo esc_html($workshop->customer); ?></h3>
                            <div class="cfp-workshop-date">
                                <strong><?php echo date('F j, Y', strtotime($workshop->seminar_date)); ?></strong>
                            </div>
                        </div>
                        
                        <div class="cfp-workshop-details">
                            <?php if ($workshop->instructor): ?>
                                <div class="cfp-workshop-instructor">
                                    <strong>Instructor:</strong> <?php echo esc_html($workshop->instructor); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($workshop->location || $workshop->time_location): ?>
                                <div class="cfp-workshop-location">
                                    <strong>Location:</strong> <?php echo esc_html($workshop->location ?: $workshop->time_location); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($workshop->workshop_cost): ?>
                                <div class="cfp-workshop-cost">
                                    <strong>Cost:</strong> $<?php echo number_format($workshop->workshop_cost, 2); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($workshop->workshop_description): ?>
                                <div class="cfp-workshop-description">
                                    <?php echo nl2br(esc_html($workshop->workshop_description)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($workshop->materials_files): ?>
                            <div class="cfp-workshop-materials">
                                <h4>Workshop Materials</h4>
                                <div class="cfp-materials-list">
                                    <?php 
                                    $files = explode("\n", $workshop->materials_files);
                                    foreach ($files as $file) {
                                        $file = trim($file);
                                        if (empty($file)) continue;
                                        
                                        // Check if it's a URL
                                        if (filter_var($file, FILTER_VALIDATE_URL)) {
                                            $filename = basename(parse_url($file, PHP_URL_PATH)) ?: 'Download File';
                                            echo '<div class="cfp-file-item">';
                                            echo '<a href="' . esc_url($file) . '" target="_blank" class="cfp-download-link">';
                                            echo '<span class="cfp-file-icon">📄</span> ' . esc_html($filename);
                                            echo '</a></div>';
                                        } else {
                                            echo '<div class="cfp-file-item">';
                                            echo '<span class="cfp-file-icon">📄</span> ' . esc_html($file);
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($workshop->email): ?>
                            <div class="cfp-workshop-contact">
                                <strong>Contact:</strong> 
                                <a href="mailto:<?php echo esc_attr($workshop->email); ?>">
                                    <?php echo esc_html($workshop->contact_name ?: $workshop->email); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="cfp-workshop-actions">
                            <?php if (strtotime($workshop->seminar_date) >= strtotime('today')): ?>
                                <a href="#signin" class="cfp-action-button cfp-signin-button">Sign In to Workshop</a>
                            <?php endif; ?>
                            <?php if ($workshop->webinar_signin_link): ?>
                                <a href="<?php echo esc_url($workshop->webinar_signin_link); ?>" target="_blank" class="cfp-action-button cfp-webinar-button">Join Webinar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="cfp-no-workshops">
                <p>No workshops found matching your criteria.</p>
            </div>
        <?php endif; ?>
        
        <style>
        .cfp-workshop-dashboard {
            max-width: 1200px;
            margin: 20px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .cfp-dashboard-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
        }
        
        .cfp-dashboard-header h2 {
            margin: 0 0 10px;
            font-size: 28px;
        }
        
        .cfp-dashboard-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .cfp-workshops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .cfp-workshop-card {
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .cfp-workshop-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .cfp-workshop-header {
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .cfp-workshop-header h3 {
            margin: 0 0 8px;
            color: #2c3e50;
            font-size: 20px;
        }
        
        .cfp-workshop-date {
            color: #7c4dff;
            font-size: 16px;
        }
        
        .cfp-workshop-details > div {
            margin-bottom: 12px;
            line-height: 1.5;
        }
        
        .cfp-workshop-cost {
            font-size: 18px;
            color: #27ae60;
        }
        
        .cfp-workshop-description {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 4px solid #7c4dff;
        }
        
        .cfp-workshop-materials {
            margin: 20px 0;
            padding: 15px;
            background: #fff8e1;
            border-radius: 4px;
            border-left: 4px solid #ffc107;
        }
        
        .cfp-workshop-materials h4 {
            margin: 0 0 15px;
            color: #e65100;
        }
        
        .cfp-materials-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .cfp-file-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
        }
        
        .cfp-file-icon {
            margin-right: 8px;
            font-size: 16px;
        }
        
        .cfp-download-link {
            color: #1976d2;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .cfp-download-link:hover {
            color: #0d47a1;
            text-decoration: underline;
        }
        
        .cfp-workshop-contact {
            margin: 15px 0;
            padding: 10px;
            background: #e3f2fd;
            border-radius: 4px;
        }
        
        .cfp-workshop-contact a {
            color: #1976d2;
            text-decoration: none;
        }
        
        .cfp-workshop-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .cfp-action-button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            text-align: center;
            transition: background-color 0.2s;
            cursor: pointer;
        }
        
        .cfp-signin-button {
            background: #4caf50;
            color: white;
        }
        
        .cfp-signin-button:hover {
            background: #45a049;
        }
        
        .cfp-webinar-button {
            background: #2196f3;
            color: white;
        }
        
        .cfp-webinar-button:hover {
            background: #1976d2;
        }
        
        .cfp-no-workshops {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .cfp-workshops-grid {
                grid-template-columns: 1fr;
            }
            
            .cfp-workshop-actions {
                flex-direction: column;
            }
            
            .cfp-action-button {
                width: 100%;
            }
        }
        </style>
    </div>
    <?php
    
    return ob_get_clean();
}

// Register admin-post actions for sign-in form
add_action('admin_post_cfp_signin_submit', 'cfpew_process_signin');
add_action('admin_post_nopriv_cfp_signin_submit', 'cfpew_process_signin');

// Debug: Log when actions are registered
error_log('CFP Workshop: Admin-post actions registered for cfp_signin_submit');

// Process public sign-in form submission
function cfpew_process_signin() {
    global $wpdb;
    $signins_table = $wpdb->prefix . 'cfp_workshop_signins';
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    // Enable debug logging for troubleshooting
    error_log('CFP Workshop Sign-in: Form submitted with data: ' . print_r($_POST, true));
    
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['cfp_signin_nonce'], 'cfp_signin_nonce')) {
        error_log('CFP Workshop Sign-in: ERROR - Nonce verification failed');
        wp_die('Security check failed');
    }
    
    try {
        // Find the workshop based on date and affiliation
        $workshop_date = sanitize_text_field($_POST['workshop_date']);
        $affiliation = sanitize_text_field($_POST['affiliation']);
        
        error_log("CFP Workshop Sign-in: Looking for workshop on $workshop_date for $affiliation");
        
        $workshop = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $workshops_table 
            WHERE seminar_date = %s AND customer = %s",
            $workshop_date, $affiliation
        ));
        
        if (!$workshop) {
            error_log("CFP Workshop Sign-in: No existing workshop found, creating new one");
            
            // Create a placeholder workshop if not found
            $insert_result = $wpdb->insert($workshops_table, array(
                'seminar_date' => $workshop_date,
                'customer' => $affiliation,
                'instructor' => 'TBD - Auto-created',
                'notes' => 'Auto-created from sign-in form submission',
                'created_at' => current_time('mysql')
            ));
            
            if ($insert_result === false) {
                error_log('CFP Workshop Sign-in: Failed to create workshop: ' . $wpdb->last_error);
                wp_die('Error creating workshop record. Please contact support. Error: ' . $wpdb->last_error);
            }
            
            $workshop_id = $wpdb->insert_id;
            error_log("CFP Workshop Sign-in: Created new workshop with ID: $workshop_id");
        } else {
            $workshop_id = $workshop->id;
            error_log("CFP Workshop Sign-in: Found existing workshop with ID: $workshop_id");
        }
        
        // Validate required fields
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $cfp_id = sanitize_text_field($_POST['cfp_id']);
        
        // Check required ratings
        $required_ratings = array(
            'learning_objectives_rating',
            'content_organized_rating', 
            'content_relevant_rating',
            'activities_helpful_rating',
            'instructor_knowledgeable_rating',
            'overall_rating'
        );
        
        $missing_fields = array();
        if (empty($first_name)) $missing_fields[] = 'First Name';
        if (empty($last_name)) $missing_fields[] = 'Last Name';
        if (empty($email)) $missing_fields[] = 'Email';
        if (empty($cfp_id)) $missing_fields[] = 'CFP ID';
        
        foreach ($required_ratings as $rating_field) {
            if (empty($_POST[$rating_field]) || intval($_POST[$rating_field]) < 1) {
                $missing_fields[] = ucwords(str_replace('_', ' ', str_replace('_rating', '', $rating_field))) . ' Rating';
            }
        }
        
        if (!empty($missing_fields)) {
            $error_msg = 'Missing required fields: ' . implode(', ', $missing_fields) . '. Please go back and complete all required fields.';
            error_log('CFP Workshop Sign-in: Validation failed - ' . $error_msg);
            wp_die($error_msg);
        }
        
        // Check for duplicate sign-in
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $signins_table 
            WHERE workshop_id = %d AND email = %s",
            $workshop_id, $email
        ));
        
        if ($existing) {
            error_log("CFP Workshop Sign-in: Duplicate sign-in attempt for email $email in workshop $workshop_id");
            $redirect_url = wp_get_referer() ? wp_get_referer() : home_url();
            wp_redirect(add_query_arg('signin_success', '1', $redirect_url));
            exit;
        }
        
        // Prepare sign-in data
        $signin_data = array(
            'workshop_id' => $workshop_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'cfp_id' => $cfp_id,
            'affiliation' => $affiliation,
            'workshop_date' => $workshop_date,
            'learning_objectives_rating' => intval($_POST['learning_objectives_rating']),
            'content_organized_rating' => intval($_POST['content_organized_rating']),
            'content_relevant_rating' => intval($_POST['content_relevant_rating']),
            'activities_helpful_rating' => intval($_POST['activities_helpful_rating']),
            'instructor_knowledgeable_rating' => intval($_POST['instructor_knowledgeable_rating']),
            'overall_rating' => intval($_POST['overall_rating']),
            'email_newsletter' => isset($_POST['email_newsletter']) ? 1 : 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'completion_date' => current_time('mysql')
        );
        
        error_log('CFP Workshop Sign-in: Attempting to insert sign-in data: ' . print_r($signin_data, true));
        
        // Insert sign-in record
        $result = $wpdb->insert($signins_table, $signin_data);
        
        if ($result === false) {
            $error_msg = 'Failed to insert sign-in: ' . $wpdb->last_error;
            error_log('CFP Workshop Sign-in: ' . $error_msg);
            wp_die('There was an error saving your sign-in. Please try again or contact support. Error: ' . $wpdb->last_error);
        }
        
        $signin_id = $wpdb->insert_id;
        error_log("CFP Workshop Sign-in: Successfully created sign-in record with ID: $signin_id");
        
        // Redirect to prevent form resubmission
        $redirect_url = wp_get_referer() ? wp_get_referer() : home_url();
        wp_redirect(add_query_arg('signin_success', '1', $redirect_url));
        exit;
        
    } catch (Exception $e) {
        error_log('CFP Workshop Sign-in: Exception occurred - ' . $e->getMessage());
        wp_die('An unexpected error occurred during sign-in. Please try again or contact support. Error: ' . $e->getMessage());
    }
}

// AJAX endpoint to get workshops for a specific date
add_action('wp_ajax_cfp_get_workshops_by_date', 'cfpew_get_workshops_by_date');
add_action('wp_ajax_nopriv_cfp_get_workshops_by_date', 'cfpew_get_workshops_by_date');
function cfpew_get_workshops_by_date() {
    global $wpdb;
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'cfp_workshop_nonce')) {
        wp_die('Security check failed');
    }
    
    $date = sanitize_text_field($_POST['date']);
    
    if (empty($date)) {
        wp_send_json_error('Date is required');
    }
    
    // Get workshops for the specified date
    $workshops = $wpdb->get_results($wpdb->prepare(
        "SELECT id, customer, instructor, time_location 
        FROM $workshops_table 
        WHERE seminar_date = %s 
        ORDER BY customer ASC",
        $date
    ));
    
    if (empty($workshops)) {
        wp_send_json_error('No workshops found for this date');
    }
    
    $workshop_options = array();
    foreach ($workshops as $workshop) {
        $label = $workshop->customer;
        if ($workshop->instructor) {
            $label .= ' (Instructor: ' . $workshop->instructor . ')';
        }
        if ($workshop->time_location) {
            $label .= ' - ' . $workshop->time_location;
        }
        
        $workshop_options[] = array(
            'value' => $workshop->customer,
            'label' => $label
        );
    }
    
    wp_send_json_success($workshop_options);
}

// Debug function to check sign-ins (temporary)
add_action('wp_ajax_cfp_debug_signins', 'cfpew_debug_signins');
add_action('wp_ajax_nopriv_cfp_debug_signins', 'cfpew_debug_signins');
function cfpew_debug_signins() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    global $wpdb;
    $signins_table = $wpdb->prefix . 'cfp_workshop_signins';
    $workshops_table = $wpdb->prefix . 'cfp_workshops';
    
    echo "<style>table { border-collapse: collapse; margin: 10px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #f5f5f5; }</style>";
    
    echo "<h2>CFP Workshop Database Debug</h2>";
    echo "<p><strong>Generated:</strong> " . current_time('Y-m-d H:i:s') . "</p>";
    
    // Check if tables exist
    echo "<h3>Database Tables Status</h3>";
    $signins_exists = $wpdb->get_var("SHOW TABLES LIKE '$signins_table'") == $signins_table;
    $workshops_exists = $wpdb->get_var("SHOW TABLES LIKE '$workshops_table'") == $workshops_table;
    
    echo "<ul>";
    echo "<li>Sign-ins table ($signins_table): " . ($signins_exists ? "✅ EXISTS" : "❌ MISSING") . "</li>";
    echo "<li>Workshops table ($workshops_table): " . ($workshops_exists ? "✅ EXISTS" : "❌ MISSING") . "</li>";
    echo "</ul>";
    
    if (!$signins_exists || !$workshops_exists) {
        echo "<p><strong>⚠️ Missing tables detected!</strong> <a href='" . admin_url('admin.php?page=cfp-workshops-templates&action=create_tables&cfpew_nonce=' . wp_create_nonce('cfpew_create_tables')) . "'>Click here to create missing tables</a></p>";
    }
    
    // Show table structures
    if ($signins_exists) {
        echo "<h3>Sign-ins Table Structure</h3>";
        $columns = $wpdb->get_results("DESCRIBE $signins_table");
        if ($columns) {
            echo "<table><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            foreach ($columns as $col) {
                echo "<tr><td>{$col->Field}</td><td>{$col->Type}</td><td>{$col->Null}</td><td>{$col->Key}</td><td>{$col->Default}</td></tr>";
            }
            echo "</table>";
        }
    }
    
    echo "<h3>Recent Sign-ins (Last 10)</h3>";
    if ($signins_exists) {
        $signins = $wpdb->get_results("SELECT s.*, w.customer, w.seminar_date 
                                      FROM $signins_table s 
                                      LEFT JOIN $workshops_table w ON s.workshop_id = w.id 
                                      ORDER BY s.completion_date DESC LIMIT 10");
        
        if ($signins) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>CFP ID</th><th>Workshop</th><th>Workshop Date</th><th>Sign-in Date</th><th>Overall Rating</th></tr>";
            foreach ($signins as $signin) {
                echo "<tr>";
                echo "<td>{$signin->id}</td>";
                echo "<td>{$signin->first_name} {$signin->last_name}</td>";
                echo "<td>{$signin->email}</td>";
                echo "<td>{$signin->cfp_id}</td>";
                echo "<td>{$signin->customer}</td>";
                echo "<td>{$signin->seminar_date}</td>";
                echo "<td>{$signin->completion_date}</td>";
                echo "<td>" . ($signin->overall_rating ? str_repeat('⭐', $signin->overall_rating) : 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ No sign-ins found in database.</p>";
            
            // Check total count
            $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $signins_table");
            echo "<p><strong>Total sign-ins in database:</strong> $total_count</p>";
        }
    } else {
        echo "<p>❌ Sign-ins table does not exist.</p>";
    }
    
    echo "<h3>Recent Workshops (Last 10)</h3>";
    if ($workshops_exists) {
        $workshops = $wpdb->get_results("SELECT * FROM $workshops_table ORDER BY seminar_date DESC LIMIT 10");
        
        if ($workshops) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Date</th><th>Customer</th><th>Instructor</th><th>Created</th><th>Notes</th></tr>";
            foreach ($workshops as $workshop) {
                echo "<tr>";
                echo "<td>{$workshop->id}</td>";
                echo "<td>{$workshop->seminar_date}</td>";
                echo "<td>{$workshop->customer}</td>";
                echo "<td>{$workshop->instructor}</td>";
                echo "<td>{$workshop->created_at}</td>";
                echo "<td>" . substr($workshop->notes, 0, 50) . (strlen($workshop->notes) > 50 ? '...' : '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            $total_workshops = $wpdb->get_var("SELECT COUNT(*) FROM $workshops_table");
            echo "<p><strong>Total workshops in database:</strong> $total_workshops</p>";
        } else {
            echo "<p>❌ No workshops found in database.</p>";
        }
    } else {
        echo "<p>❌ Workshops table does not exist.</p>";
    }
    
    // Show recent WordPress error log entries related to CFP
    echo "<h3>Recent Error Log Entries</h3>";
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $lines = explode("\n", $log_content);
        $cfp_lines = array_filter($lines, function($line) {
            return strpos($line, 'CFP Workshop') !== false;
        });
        
        if (!empty($cfp_lines)) {
            $recent_entries = array_slice(array_reverse($cfp_lines), 0, 10);
            echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: scroll;'>";
            foreach ($recent_entries as $entry) {
                echo esc_html($entry) . "\n";
            }
            echo "</pre>";
        } else {
            echo "<p>No CFP Workshop entries found in error log.</p>";
        }
    } else {
        echo "<p>Debug log file not found at: $log_file</p>";
        echo "<p>WordPress debug logging may not be enabled.</p>";
    }
    
    wp_die();
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
                <li>Add the shortcode <code>[cfp_workshop_dashboard]</code> to create a public workshop information page</li>
                <li>Add the shortcode <code>[cfp_workshop_signin]</code> to any page where attendees should sign in</li>
                <li>View and export sign-ins from <a href="<?php echo admin_url('admin.php?page=cfp-workshops-signins'); ?>">CFP Workshops → Sign-ins</a></li>
            </ol>
        </div>
        <?php
        delete_transient('cfpew_activation_notice');
    }
}

// Handle invoice_unknown checkbox submission on All Workshops page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cfpew_invoice_unknown_id'])) {
    $workshop_id = intval($_POST['cfpew_invoice_unknown_id']);
    $invoice_unknown = isset($_POST['invoice_unknown']) ? 1 : 0;
    $wpdb->update(
        $table_name,
        array('invoice_unknown' => $invoice_unknown),
        array('id' => $workshop_id)
    );
    // Refresh to avoid resubmission
    echo '<script>window.location = window.location.href.split("?")[0] + window.location.search.replace(/&?cfpew_invoice_unknown_id=[^&]*/g, "");</script>';
    exit;
}

// Migration: Ensure 'invoice_unknown' column exists in cfp_workshops table
add_action('plugins_loaded', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'cfp_workshops';
    $column = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'invoice_unknown'");
    if (empty($column)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN invoice_unknown TINYINT(1) NOT NULL DEFAULT 0");
    }
});

// Migration: Ensure 'invoice_sent_flag' column exists
add_action('plugins_loaded', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'cfp_workshops';
    $column = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'invoice_sent_flag'");
    if (empty($column)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN invoice_sent_flag TINYINT(1) NOT NULL DEFAULT 0");
    }
});