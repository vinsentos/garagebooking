
<?php

class GarageBooking {

    public function __construct() {
        add_action('init', [$this, 'register_booking_post_type']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('garage_booking', [$this, 'render_booking_form']);
        add_action('wp_ajax_fetch_vehicle_details', [$this, 'fetch_vehicle_details']);
        add_action('wp_ajax_nopriv_fetch_vehicle_details', [$this, 'fetch_vehicle_details']);
        add_action('admin_post_save_booking', [$this, 'save_booking']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_booking_post_type() {
        register_post_type('garage_booking', [
            'labels' => [
                'name' => __('Bookings', 'garagebooking'),
                'singular_name' => __('Booking', 'garagebooking')
            ],
            'public' => false,
            'show_ui' => true,
            'capability_type' => 'post',
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-calendar',
        ]);
    }

    public function register_settings() {
        register_setting('garagebooking_settings', 'garagebooking_api_key');
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Garage Booking', 'garagebooking'),
            __('Garage Booking', 'garagebooking'),
            'manage_options',
            'garage-booking',
            [$this, 'admin_page_content'],
            'dashicons-calendar',
            6
        );
        add_submenu_page(
            'garage-booking',
            __('API Settings', 'garagebooking'),
            __('API Settings', 'garagebooking'),
            'manage_options',
            'garage-booking-settings',
            [$this, 'settings_page_content']
        );
    }

    public function admin_page_content() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Garage Booking Admin', 'garagebooking') . '</h1>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . __('Booking ID', 'garagebooking') . '</th><th>' . __('Customer Name', 'garagebooking') . '</th><th>' . __('Vehicle Make', 'garagebooking') . '</th><th>' . __('Service', 'garagebooking') . '</th><th>' . __('Status', 'garagebooking') . '</th></tr></thead>';
        echo '<tbody>';

        $bookings = get_posts(['post_type' => 'garage_booking', 'numberposts' => -1]);
        foreach ($bookings as $booking) {
            $meta = get_post_meta($booking->ID);
            echo '<tr>';
            echo '<td>' . esc_html($booking->ID) . '</td>';
            echo '<td>' . esc_html($meta['customer_name'][0] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($meta['vehicle_make'][0] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($meta['service'][0] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($meta['status'][0] ?? 'N/A') . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    public function settings_page_content() {
        ?>
        <div class="wrap">
            <h1><?php _e('Garage Booking API Settings', 'garagebooking'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('garagebooking_settings');
                do_settings_sections('garagebooking_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('API Key', 'garagebooking'); ?></th>
                        <td>
                            <input type="text" name="garagebooking_api_key" 
                                value="<?php echo esc_attr(get_option('garagebooking_api_key')); ?>" 
                                class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'garagebooking-scripts',
            GARAGEBOOKING_PLUGIN_URL . 'assets/js/garagebooking.js',
            ['jquery'],
            '1.0.3',
            true
        );
        wp_localize_script('garagebooking-scripts', 'garageBooking', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
        wp_enqueue_style(
            'garagebooking-styles',
            GARAGEBOOKING_PLUGIN_URL . 'assets/css/garagebooking.css',
            [],
            '1.0.3'
        );
    }

    public function render_booking_form() {
        ob_start();
        ?>
        <form id="garage-booking-form" method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="save_booking">
            <div id="step-1">
                <h2>Step 1: Vehicle Information</h2>
                <label>Registration Number</label>
                <input type="text" name="registration_number" id="registration_number" required>
                <button type="button" id="fetch-vehicle-details">Fetch Vehicle Details</button>
                <div id="vehicle-details"></div>
            </div>
            <div id="step-2">
                <h2>Service Details</h2>
                <label>Service</label>
                <input type="text" name="service" required>
                <label>Preferred Date/Time</label>
                <input type="datetime-local" name="preferred_datetime" required>
            </div>
            <div id="step-3">
                <h2>Step 3: Personal Information</h2>
                <label>Name</label>
                <input type="text" name="customer_name" required>
                <label>Phone</label>
                <input type="tel" name="customer_phone" required>
                <label>Email (Optional)</label>
                <input type="email" name="customer_email">
            </div>
            <button type="submit">Submit Booking</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function fetch_vehicle_details() {
        $registration_number = sanitize_text_field($_POST['registration_number']);
        $api_key = get_option('garagebooking_api_key');
        $endpoint = "https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles";

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode(['registrationNumber' => $registration_number]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API request failed.']);
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || isset($data['errors'])) {
            wp_send_json_error(['message' => 'Invalid response from API.']);
            return;
        }

        wp_send_json_success([
            'make' => $data['make'] ?? 'N/A',
            'model' => $data['model'] ?? 'N/A',
            'year' => $data['yearOfManufacture'] ?? 'N/A',
            'registration_date' => $data['monthOfFirstRegistration'] ?? 'N/A',
            'mot_expiry' => $data['motExpiryDate'] ?? 'N/A',
        ]);
    }

    public function save_booking() {
        error_log('Save Booking Triggered');
        error_log(print_r($_POST, true)); // Log the POST data

        // Check nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'garage_booking_nonce')) {
            error_log('Nonce verification failed.');
            wp_die('Nonce verification failed.');
        }

        // Insert the booking post
        $post_id = wp_insert_post([
            'post_type' => 'garage_booking',
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($_POST['customer_name']),
        ]);

        if (is_wp_error($post_id)) {
            error_log('Post insertion failed: ' . $post_id->get_error_message());
            wp_die('Post insertion failed.');
        }

        // Save vehicle details and other meta data
        update_post_meta($post_id, 'registration_number', sanitize_text_field($_POST['registration_number']));
        update_post_meta($post_id, 'vehicle_make', sanitize_text_field($_POST['vehicle_make']));
        update_post_meta($post_id, 'vehicle_model', sanitize_text_field($_POST['vehicle_model']));
        update_post_meta($post_id, 'vehicle_year', sanitize_text_field($_POST['vehicle_year']));
        update_post_meta($post_id, 'vehicle_registration_date', sanitize_text_field($_POST['vehicle_registration_date']));
        update_post_meta($post_id, 'vehicle_mot_expiry', sanitize_text_field($_POST['vehicle_mot_expiry']));
        update_post_meta($post_id, 'service', sanitize_text_field($_POST['service']));
        update_post_meta($post_id, 'preferred_datetime', sanitize_text_field($_POST['preferred_datetime']));
        update_post_meta($post_id, 'customer_name', sanitize_text_field($_POST['customer_name']));
        update_post_meta($post_id, 'customer_phone', sanitize_text_field($_POST['customer_phone']));
        update_post_meta($post_id, 'customer_email', sanitize_text_field($_POST['customer_email']));

        // Send email if an email address is provided
        $customer_email = sanitize_email($_POST['customer_email']);
        if ($customer_email) {
            $subject = 'Garage Booking Confirmation';
            $message = sprintf(
                "Dear %s,

Thank you for your booking. Here are your booking details:

" .
                "Registration Number: %s
Make: %s
Model: %s
Year: %s
Service: %s
Preferred Date/Time: %s

" .
                "We look forward to serving you.

Best Regards,
Garage Team",
                sanitize_text_field($_POST['customer_name']),
                sanitize_text_field($_POST['registration_number']),
                sanitize_text_field($_POST['vehicle_make']),
                sanitize_text_field($_POST['vehicle_model']),
                sanitize_text_field($_POST['vehicle_year']),
                sanitize_text_field($_POST['service']),
                sanitize_text_field($_POST['preferred_datetime'])
            );

            wp_mail($customer_email, $subject, $message);
            error_log('Confirmation email sent to ' . $customer_email);
        }

        error_log('Booking saved successfully.');

        // Redirect back to admin page
        wp_redirect(admin_url('admin.php?page=garage-booking'));
        exit;
    }
}
