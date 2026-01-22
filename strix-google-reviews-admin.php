<?php
/**
 * Plugin Name: Strix Google Reviews Admin Panel
 * Plugin URI: https://strixmedia.ru
 * Description: Independent admin panel for configuring Google API keys and managing review display settings
 * Version: 1.0.0
 * Author: Strix Media
 * Author URI: https://strixmedia.ru
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: strix-google-reviews-admin
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class Strix_Google_Reviews_Admin {

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_strix_google_reviews_admin_connect', array($this, 'handle_google_connect'));
        add_action('wp_ajax_strix_google_reviews_admin_get_reviews', array($this, 'handle_get_reviews'));

        // Add admin menu if main plugin is active
        if ($this->is_main_plugin_active()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
    }

    /**
     * Check if main Strix Google Reviews plugin is active
     * Note: Admin panel works independently but can integrate with main plugin if available
     */
    private function is_main_plugin_active() {
        // Admin panel can work independently - return true by default
        // This allows the admin panel to function even without the main plugin

        // Optionally check if main plugin is available for integration
        if (is_plugin_active('strix-google-review/strix-google-reviews.php') && class_exists('TrustindexPlugin_google')) {
            global $trustindexPlugin_google;
            return $trustindexPlugin_google ? true : false;
        }

        // Admin panel works independently
        return true;
    }

    /**
     * Add admin menu and settings
     */
    public function add_admin_menu() {
        // Main menu for admin panel
        add_menu_page(
            __('Strix Reviews Admin', 'strix-google-reviews-admin'),
            __('Strix Reviews Admin', 'strix-google-reviews-admin'),
            'manage_options',
            'strix-google-reviews-admin',
            array($this, 'admin_settings_page'),
            'dashicons-google',
            30
        );

        // Submenu for settings
        add_submenu_page(
            'strix-google-reviews-admin',
            __('Settings', 'strix-google-reviews-admin'),
            __('Settings', 'strix-google-reviews-admin'),
            'manage_options',
            'strix-google-reviews-admin',
            array($this, 'admin_settings_page')
        );

        // Submenu for dashboard
        add_submenu_page(
            'strix-google-reviews-admin',
            __('Dashboard', 'strix-google-reviews-admin'),
            __('Dashboard', 'strix-google-reviews-admin'),
            'manage_options',
            'strix-google-reviews-admin-dashboard',
            array($this, 'admin_dashboard_page')
        );

        // Hidden page for popup connection (legacy)
        add_submenu_page(
            null,
            __('Connect Profile', 'strix-google-reviews-admin'),
            __('Connect Profile', 'strix-google-reviews-admin'),
            'manage_options',
            'strix-google-reviews-admin-connect',
            array($this, 'admin_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'admin_page_strix-google-reviews-admin') {
            // Bootstrap CSS
            wp_enqueue_style('bootstrap', 'https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css', array(), '4.1.3');

            // Font Awesome
            wp_enqueue_style('font-awesome', 'https://cdn.trustindex.io/assets/css/plugins/font-awesome-pro/css/fontawesome.min.css', array(), '1.0.0');
            wp_enqueue_style('font-awesome-regular', 'https://cdn.trustindex.io/assets/css/plugins/font-awesome-pro/css/regular.min.css', array(), '1.0.0');

            // Main admin CSS
            wp_enqueue_style('strix-admin-main', 'https://cdn.trustindex.io/assets/css/main.css', array(), '1.0.0');

            // jQuery and Bootstrap JS
            wp_enqueue_script('jquery');
            wp_enqueue_script('bootstrap-js', 'https://cdn.trustindex.io/assets/plugins/bootstrap/dist/js/bootstrap.bundle.min.js', array('jquery'), '4.1.3', true);

            // Bootstrap Notify
            wp_enqueue_script('bootstrap-notify', 'https://cdn.trustindex.io/assets/plugins/bootstrap-notify/bootstrap-notify.min.js', array('jquery'), '1.0.0', true);

            // Google Maps API with dynamic key
            $api_key = $this->get_google_maps_api_key();
            if ($api_key) {
                wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . urlencode($api_key) . '&callback=initGoogle&libraries=places&v=weekly', array(), '1.0.0', true);
            } else {
                // Fallback for development
                wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyBrTmPaIMGG6NSb6KEcbfhVny314e3_d6c&callback=initGoogle&libraries=places&v=weekly', array(), '1.0.0', true);
            }

            // Admin scripts
            wp_enqueue_script('strix-admin-js', 'https://cdn.trustindex.io/assets/js/admin.js', array('jquery'), '1.0.0', true);
            wp_enqueue_script('strix-admin-custom', plugin_dir_url(__FILE__) . 'assets/js/admin-custom.js', array('jquery'), '1.0.0', true);

            // Localize script
            wp_localize_script('strix-admin-custom', 'strix_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('strix_google_reviews_admin_nonce')
            ));
        }
    }

    /**
     * Admin settings page
     */
    public function admin_settings_page() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }

        // Get current settings
        $settings = $this->get_settings();

        ?>
        <div class="wrap">
            <h1><?php _e('Strix Google Reviews Admin - Settings', 'strix-google-reviews-admin'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('strix_admin_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Google Maps API Key', 'strix-google-reviews-admin'); ?></th>
                        <td>
                            <input type="text" name="google_maps_api_key" value="<?php echo esc_attr($settings['google_maps_api_key'] ?? ''); ?>" class="regular-text">
                            <p class="description"><?php _e('Enter your Google Maps API key for Places API and Maps integration.', 'strix-google-reviews-admin'); ?></p>
                            <p class="description"><?php _e('Get your API key from: <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>', 'strix-google-reviews-admin'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Enable Debug Mode', 'strix-google-reviews-admin'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_mode" value="1" <?php checked($settings['debug_mode'] ?? false); ?>>
                                <?php _e('Enable debug logging for API calls', 'strix-google-reviews-admin'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Reviews Cache Time', 'strix-google-reviews-admin'); ?></th>
                        <td>
                            <input type="number" name="cache_time" value="<?php echo esc_attr($settings['cache_time'] ?? 3600); ?>" class="small-text">
                            <span><?php _e('seconds', 'strix-google-reviews-admin'); ?></span>
                            <p class="description"><?php _e('How long to cache review data (default: 3600 seconds = 1 hour)', 'strix-google-reviews-admin'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Max Reviews to Fetch', 'strix-google-reviews-admin'); ?></th>
                        <td>
                            <input type="number" name="max_reviews" value="<?php echo esc_attr($settings['max_reviews'] ?? 50); ?>" class="small-text" min="1" max="500">
                            <p class="description"><?php _e('Maximum number of reviews to fetch from Google (1-500)', 'strix-google-reviews-admin'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Enable Review Replies', 'strix-google-reviews-admin'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_replies" value="1" <?php checked($settings['enable_replies'] ?? true); ?>>
                                <?php _e('Allow businesses to reply to reviews', 'strix-google-reviews-admin'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'strix-google-reviews-admin')); ?>
            </form>

            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h3><?php _e('API Status Check', 'strix-google-reviews-admin'); ?></h3>
                <p><?php _e('Test your Google Maps API key configuration:', 'strix-google-reviews-admin'); ?></p>
                <button type="button" id="test-api-btn" class="button"><?php _e('Test API Connection', 'strix-google-reviews-admin'); ?></button>
                <div id="api-test-result" style="margin-top: 10px;"></div>
            </div>

            <script>
                document.getElementById('test-api-btn').addEventListener('click', function() {
                    const apiKey = document.querySelector('input[name="google_maps_api_key"]').value;
                    const resultDiv = document.getElementById('api-test-result');

                    if (!apiKey) {
                        resultDiv.innerHTML = '<div class="notice notice-error"><p><?php _e('Please enter an API key first.', 'strix-google-reviews-admin'); ?></p></div>';
                        return;
                    }

                    resultDiv.innerHTML = '<div class="notice notice-info"><p><?php _e('Testing API connection...', 'strix-google-reviews-admin'); ?></p></div>';

                    // Test API call
                    fetch('https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJN1t_tDeuEmsRUsoyG83frY4&fields=name&key=' + apiKey)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'OK') {
                                resultDiv.innerHTML = '<div class="notice notice-success"><p><?php _e('API connection successful!', 'strix-google-reviews-admin'); ?></p></div>';
                            } else {
                                resultDiv.innerHTML = '<div class="notice notice-error"><p><?php printf(__('API Error: %s', 'strix-google-reviews-admin'), data.status); ?></p></div>';
                            }
                        })
                        .catch(error => {
                            resultDiv.innerHTML = '<div class="notice notice-error"><p><?php _e('Connection failed. Check your API key and network.', 'strix-google-reviews-admin'); ?></p></div>';
                        });
                });
            </script>
        </div>
        <?php
    }

    /**
     * Admin dashboard page
     */
    public function admin_dashboard_page() {
        $pageDetails = $this->get_page_details_from_main_plugin();
        $reviews = $this->get_reviews_from_main_plugin();

        ?>
        <div class="wrap">
            <h1><?php _e('Strix Google Reviews Admin - Dashboard', 'strix-google-reviews-admin'); ?></h1>

            <?php if ($pageDetails): ?>
                <div class="notice notice-info">
                    <p><?php printf(__('Connected to: <strong>%s</strong> | Rating: %s (%d reviews)', 'strix-google-reviews-admin'),
                        esc_html($pageDetails['name'] ?? 'Unknown'),
                        number_format($pageDetails['rating_score'] ?? 0, 1),
                        $pageDetails['rating_number'] ?? 0
                    ); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php _e('No business profile connected. Go to the main Strix Google Reviews plugin to connect your profile.', 'strix-google-reviews-admin'); ?></p>
                </div>
            <?php endif; ?>

            <div class="dashboard-cards">
                <div class="card">
                    <h3><?php _e('Quick Stats', 'strix-google-reviews-admin'); ?></h3>
                    <ul>
                        <li><?php printf(__('Total Reviews: %d', 'strix-google-reviews-admin'), count($reviews)); ?></li>
                        <li><?php printf(__('Average Rating: %.1f', 'strix-google-reviews-admin'), $this->calculate_average_rating($reviews)); ?></li>
                        <li><?php printf(__('Positive Reviews: %d', 'strix-google-reviews-admin'), $this->count_positive_reviews($reviews)); ?></li>
                    </ul>
                </div>

                <div class="card">
                    <h3><?php _e('Recent Activity', 'strix-google-reviews-admin'); ?></h3>
                    <?php if (!empty($reviews)): ?>
                        <ul>
                            <?php
                            $recent_reviews = array_slice($reviews, 0, 5);
                            foreach ($recent_reviews as $review): ?>
                                <li>
                                    <strong><?php echo esc_html($review['user'] ?? 'Anonymous'); ?></strong>
                                    (<?php echo str_repeat('★', $review['rating'] ?? 0); ?>)
                                    <br><small><?php echo esc_html(date('M j, Y', $review['time'] ?? time())); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?php _e('No reviews available.', 'strix-google-reviews-admin'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

                <div class="card">
                    <h3><?php _e('System Information', 'strix-google-reviews-admin'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <td><strong><?php _e('Admin Panel Status', 'strix-google-reviews-admin'); ?>:</strong></td>
                            <td><span style="color: green;">✓ <?php _e('Active (Independent)', 'strix-google-reviews-admin'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Main Plugin Status', 'strix-google-reviews-admin'); ?>:</strong></td>
                            <td><?php echo is_plugin_active('strix-google-review/strix-google-reviews.php') ? '<span style="color: green;">✓ Active (Integrated)</span>' : '<span style="color: blue;">ℹ Standalone Mode</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Google API Key', 'strix-google-reviews-admin'); ?>:</strong></td>
                            <td><?php echo !empty($this->get_settings()['google_maps_api_key']) ? '<span style="color: green;">✓ Configured</span>' : '<span style="color: orange;">⚠ Not Set</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('API Testing', 'strix-google-reviews-admin'); ?>:</strong></td>
                            <td><?php echo !empty($this->get_settings()['google_maps_api_key']) ? '<span style="color: blue;">Ready to test</span>' : '<span style="color: gray;">Set API key first</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Cache System', 'strix-google-reviews-admin'); ?>:</strong></td>
                            <td><span style="color: green;"><?php printf(__('✓ Active (%d sec)', 'strix-google-reviews-admin'), $this->get_settings()['cache_time'] ?? 3600); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Review Limits', 'strix-google-reviews-admin'); ?>:</strong></td>
                            <td><span style="color: green;"><?php printf(__('✓ Max %d reviews', 'strix-google-reviews-admin'), $this->get_settings()['max_reviews'] ?? 50); ?></span></td>
                        </tr>
                        <?php if ($pageDetails): ?>
                        <tr>
                            <td><strong><?php _e('Connected Profiles', 'strix-google-reviews-admin'); ?>:</strong></td>
                            <td><?php echo '<span style="color: green;">✓ ' . esc_html($pageDetails['name'] ?? 'Profile connected') . '</span>'; ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
        </div>

        <style>
            .dashboard-cards { display: flex; gap: 20px; margin: 20px 0; }
            .dashboard-cards .card { flex: 1; padding: 20px; background: white; border: 1px solid #ccc; border-radius: 5px; }
            .card { margin: 20px 0; padding: 20px; background: white; border: 1px solid #ccc; border-radius: 5px; }
            .card h3 { margin-top: 0; }
            .card ul { margin: 0; padding-left: 20px; }
            .card li { margin-bottom: 8px; }
        </style>
        <?php
    }

    /**
     * Legacy admin page (popup connection)
     */
    public function admin_page() {
        if (!$this->is_main_plugin_active()) {
            wp_die(__('Main Strix Google Reviews plugin is not active.', 'strix-google-reviews-admin'));
        }

        // Check if we need to show connection interface or dashboard
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'connect';

        if ($action === 'dashboard') {
            $this->render_dashboard_page();
        } else {
            $this->render_admin_page();
        }
    }

    /**
     * Render dashboard page HTML
     */
    private function render_dashboard_page() {
        // Get data from main plugin
        $pageDetails = $this->get_page_details_from_main_plugin();
        $reviews = $this->get_reviews_from_main_plugin();

        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta content="ie=edge" http-equiv="x-ua-compatible">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta name="title" content="Strix Google Reviews Dashboard">
    <meta name="description" content="Manage your Google reviews">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="Strix Media">
    <title><?php _e('Google Reviews Dashboard', 'strix-google-reviews-admin'); ?></title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.trustindex.io/assets/css/plugins/font-awesome-pro/css/fontawesome.min.css">
    <link rel="stylesheet" href="https://cdn.trustindex.io/assets/css/main.css?v=36680">
    <link rel="stylesheet" href="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/css/admin-dashboard.css'); ?>">
    <style>
        .dashboard-card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .rating-stars { color: #ffc107; font-size: 18px; }
        .review-item { border: 1px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .review-author { font-weight: bold; color: #495057; }
        .review-date { color: #6c757d; font-size: 14px; }
        .review-text { margin-top: 10px; line-height: 1.5; }
        .stats-card { text-align: center; padding: 20px; }
        .stats-number { font-size: 2rem; font-weight: bold; color: #007bff; }
        .stats-label { color: #6c757d; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="ti-nav">
        <div class="row">
            <div class="col site-logo">
                <a href="#" onclick="window.close()">
                    <img alt="Strix Reviews" src="https://cdn.trustindex.io/assets/platform/Trustindex/logo-dark.svg">
                </a>
            </div>
            <div class="col-auto">
                <a href="?page=strix-google-reviews-admin&action=connect" class="btn btn-outline-primary">
                    <?php _e('Connect New Profile', 'strix-google-reviews-admin'); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="wrapper">
        <div class="main">
            <div class="content">
                <div class="panel">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="dashboard-header">
                                <div class="container">
                                    <h1><?php _e('Google Reviews Dashboard', 'strix-google-reviews-admin'); ?></h1>
                                    <p class="lead"><?php _e('Monitor and manage your Google Business Profile reviews', 'strix-google-reviews-admin'); ?></p>
                                </div>
                            </div>

                            <?php if ($pageDetails): ?>
                                <!-- Business Profile Overview -->
                                <div class="business-profile-card">
                                    <div class="business-profile-header">
                                        <div class="container">
                                            <h3><?php _e('Business Profile Overview', 'strix-google-reviews-admin'); ?></h3>
                                        </div>
                                    </div>
                                    <div class="business-profile-body">
                                        <div class="container">
                                            <div class="row align-items-center">
                                                <div class="col-md-2 text-center">
                                                    <?php if (!empty($pageDetails['avatar_url'])): ?>
                                                        <img src="<?php echo esc_url($pageDetails['avatar_url']); ?>" class="business-logo" alt="Business Logo">
                                                    <?php else: ?>
                                                        <div class="business-logo bg-secondary d-flex align-items-center justify-content-center text-white" style="font-size: 2rem;">
                                                            <i class="fas fa-building"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-7">
                                                    <h4><?php echo esc_html($pageDetails['name'] ?? 'Business Name'); ?></h4>
                                                    <p class="text-muted mb-3"><?php echo esc_html($pageDetails['address'] ?? ''); ?></p>
                                                    <div class="business-rating">
                                                        <?php
                                                        $rating = $pageDetails['rating_score'] ?? 0;
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            echo $i <= $rating ? '★' : '☆';
                                                        }
                                                        ?>
                                                        <span class="ml-2"><?php echo number_format($rating, 1); ?>/5.0</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="business-stats">
                                                        <div class="business-stat-item">
                                                            <span class="business-stat-number"><?php echo number_format($pageDetails['rating_number'] ?? 0); ?></span>
                                                            <span class="business-stat-label"><?php _e('Reviews', 'strix-google-reviews-admin'); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Statistics Cards -->
                                <div class="dashboard-stats">
                                    <div class="container">
                                        <div class="row">
                                            <div class="col-md-3 col-sm-6 mb-4">
                                                <div class="stat-card">
                                                    <span class="stat-number"><?php echo number_format($pageDetails['rating_number'] ?? 0); ?></span>
                                                    <span class="stat-label"><?php _e('Total Reviews', 'strix-google-reviews-admin'); ?></span>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-4">
                                                <div class="stat-card">
                                                    <span class="stat-number"><?php echo number_format($pageDetails['rating_score'] ?? 0, 1); ?></span>
                                                    <span class="stat-label"><?php _e('Average Rating', 'strix-google-reviews-admin'); ?></span>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-4">
                                                <div class="stat-card">
                                                    <span class="stat-number">
                                                        <?php
                                                        $positive = 0;
                                                        if (!empty($reviews)) {
                                                            foreach ($reviews as $review) {
                                                                if (($review['rating'] ?? 0) >= 4) $positive++;
                                                            }
                                                        }
                                                        echo $positive;
                                                        ?>
                                                    </span>
                                                    <span class="stat-label"><?php _e('Positive Reviews', 'strix-google-reviews-admin'); ?></span>
                                                    <div class="stat-trend positive">
                                                        <i class="fas fa-arrow-up"></i> <?php echo $pageDetails['rating_number'] ? round(($positive / $pageDetails['rating_number']) * 100) : 0; ?>%
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-4">
                                                <div class="stat-card">
                                                    <span class="stat-number">
                                                        <?php
                                                        $lastWeek = 0;
                                                        $weekAgo = strtotime('-1 week');
                                                        if (!empty($reviews)) {
                                                            foreach ($reviews as $review) {
                                                                if (($review['time'] ?? 0) > $weekAgo) $lastWeek++;
                                                            }
                                                        }
                                                        echo $lastWeek;
                                                        ?>
                                                    </span>
                                                    <span class="stat-label"><?php _e('Reviews This Week', 'strix-google-reviews-admin'); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recent Reviews -->
                                <div class="reviews-section">
                                    <div class="reviews-header">
                                        <h3><?php _e('Recent Reviews', 'strix-google-reviews-admin'); ?></h3>
                                        <div class="review-filters">
                                            <button type="button" class="review-filter-btn active" onclick="showAllReviews()"><?php _e('All', 'strix-google-reviews-admin'); ?></button>
                                            <button type="button" class="review-filter-btn" onclick="showPositiveReviews()"><?php _e('Positive', 'strix-google-reviews-admin'); ?></button>
                                            <button type="button" class="review-filter-btn" onclick="showNeutralReviews()"><?php _e('Neutral', 'strix-google-reviews-admin'); ?></button>
                                            <button type="button" class="review-filter-btn" onclick="showNegativeReviews()"><?php _e('Negative', 'strix-google-reviews-admin'); ?></button>
                                        </div>
                                    </div>
                                    <div id="reviews-container">
                                        <?php if (!empty($reviews)): ?>
                                            <?php foreach (array_slice($reviews, 0, 10) as $review): ?>
                                                <div class="review-item" data-rating="<?php echo esc_attr($review['rating'] ?? 0); ?>">
                                                    <div class="review-header">
                                                        <div>
                                                            <div class="review-author"><?php echo esc_html($review['user'] ?? 'Anonymous'); ?></div>
                                                            <div class="review-date"><?php echo esc_html(date('M j, Y', $review['time'] ?? time())); ?></div>
                                                        </div>
                                                        <div class="review-rating">
                                                            <?php
                                                            $rating = $review['rating'] ?? 0;
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                echo $i <= $rating ? '★' : '☆';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <div class="review-text">
                                                        <?php echo esc_html($review['text'] ?? ''); ?>
                                                    </div>
                                                    <?php if (!empty($review['reply'])): ?>
                                                        <div class="review-reply">
                                                            <strong><?php _e('Your Reply:', 'strix-google-reviews-admin'); ?></strong><br>
                                                            <?php echo esc_html($review['reply']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="empty-state">
                                                <div class="empty-state-icon">
                                                    <i class="fas fa-comments"></i>
                                                </div>
                                                <h3><?php _e('No Reviews Yet', 'strix-google-reviews-admin'); ?></h3>
                                                <p><?php _e('Reviews will appear here once customers start leaving feedback on your Google Business Profile.', 'strix-google-reviews-admin'); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (count($reviews) > 10): ?>
                                        <div class="text-center p-4">
                                            <button class="load-more-btn" onclick="loadMoreReviews()"><?php _e('Load More Reviews', 'strix-google-reviews-admin'); ?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <!-- No Profile Connected -->
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <h3><?php _e('No Business Profile Connected', 'strix-google-reviews-admin'); ?></h3>
                                    <p><?php _e('Connect your Google Business Profile to start monitoring reviews and customer feedback.', 'strix-google-reviews-admin'); ?></p>
                                    <a href="?page=strix-google-reviews-admin&action=connect" class="connect-btn"><?php _e('Connect Profile', 'strix-google-reviews-admin'); ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAllReviews() {
            document.querySelectorAll('.review-item').forEach(item => item.style.display = 'block');
            updateButtons('all');
        }

        function showPositiveReviews() {
            document.querySelectorAll('.review-item').forEach(item => {
                item.style.display = (parseInt(item.dataset.rating) >= 4) ? 'block' : 'none';
            });
            updateButtons('positive');
        }

        function showNeutralReviews() {
            document.querySelectorAll('.review-item').forEach(item => {
                item.style.display = (parseInt(item.dataset.rating) === 3) ? 'block' : 'none';
            });
            updateButtons('neutral');
        }

        function showNegativeReviews() {
            document.querySelectorAll('.review-item').forEach(item => {
                item.style.display = (parseInt(item.dataset.rating) <= 2) ? 'block' : 'none';
            });
            updateButtons('negative');
        }

        function updateButtons(active) {
            document.querySelectorAll('.review-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const buttons = document.querySelectorAll('.review-filter-btn');
            if (active === 'all') buttons[0].classList.add('active');
            if (active === 'positive') buttons[1].classList.add('active');
            if (active === 'neutral') buttons[2].classList.add('active');
            if (active === 'negative') buttons[3].classList.add('active');
        }

        function loadMoreReviews() {
            // Implement pagination logic here
            alert('<?php _e('Load more reviews functionality will be implemented', 'strix-google-reviews-admin'); ?>');
        }
    </script>
</body>
</html>
        <?php
    }

    /**
     * Get page details from main plugin (if available)
     */
    private function get_page_details_from_main_plugin() {
        // Check if main plugin is available and active
        if (!is_plugin_active('strix-google-review/strix-google-reviews.php') || !class_exists('TrustindexPlugin_google')) {
            return false; // Main plugin not available
        }

        global $trustindexPlugin_google;
        if (!$trustindexPlugin_google || !method_exists($trustindexPlugin_google, 'getPageDetails')) {
            return false;
        }

        try {
            return $trustindexPlugin_google->getPageDetails();
        } catch (Exception $e) {
            error_log('Strix Admin: Error getting page details: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get reviews from main plugin (if available)
     */
    private function get_reviews_from_main_plugin() {
        // Check if main plugin is available and active
        if (!is_plugin_active('strix-google-review/strix-google-reviews.php') || !class_exists('TrustindexPlugin_google')) {
            return array(); // Main plugin not available
        }

        global $trustindexPlugin_google;
        if (!$trustindexPlugin_google) {
            return array();
        }

        try {
            // Get reviews data - this might need adjustment based on actual method
            $reviews = array();
            $pageDetails = $trustindexPlugin_google->getPageDetails();

            if (!empty($pageDetails['reviews'])) {
                $reviews = $pageDetails['reviews'];
            }

            return $reviews;
        } catch (Exception $e) {
            error_log('Strix Admin: Error getting reviews: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Render admin page HTML
     */
    private function render_admin_page() {
        $pageDetails = $this->get_page_details_from_main_plugin();
        $hasConnectedProfile = !empty($pageDetails) && !empty($pageDetails['name']);

        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta content="ie=edge" http-equiv="x-ua-compatible">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta name="title" content="Strix Google Reviews Admin">
    <meta name="description" content="Connect your Google Business Profile">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="Strix Media">
    <title>Strix Google Reviews Admin</title>
</head>
<body>
    <div class="ti-nav">
        <div class="row">
            <div class="col site-logo">
                <a href="#" class="btn-loading">
                    <img alt="Strix Reviews" src="https://cdn.trustindex.io/assets/platform/Trustindex/logo-dark.svg">
                </a>
            </div>
            <?php if ($hasConnectedProfile): ?>
            <div class="col-auto">
                <a href="?page=strix-google-reviews-admin&action=dashboard" class="btn btn-outline-primary">
                    <?php _e('View Dashboard', 'strix-google-reviews-admin'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="wrapper">
        <div class="mask"></div>
        <div class="main">
            <div class="content">
                <div class="panel">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="content-box">
                                <div class="content-box-header">
                                    <div class="box-title"><?php _e('Connect Google Business Profile', 'strix-google-reviews-admin'); ?></div>
                                    <div class="box-description"><?php _e('Set up the source of your reviews on Google!', 'strix-google-reviews-admin'); ?></div>
                                </div>

                                <?php if ($hasConnectedProfile): ?>
                                <div class="alert alert-info">
                                    <h5><?php _e('Profile Already Connected', 'strix-google-reviews-admin'); ?></h5>
                                    <p><?php printf(__('You have connected: <strong>%s</strong>. You can view your reviews and analytics in the dashboard.', 'strix-google-reviews-admin'), esc_html($pageDetails['name'])); ?></p>
                                    <a href="?page=strix-google-reviews-admin&action=dashboard" class="btn btn-primary"><?php _e('View Dashboard', 'strix-google-reviews-admin'); ?></a>
                                </div>
                                <?php endif; ?>

                                <form action="" method="post" id="form-source">
                                    <input type="hidden" name="source[name]" id="source_name">
                                    <input type="hidden" name="source[description]" id="source_description">
                                    <input type="hidden" name="source[short_term_access_token]" id="source_short_term_access_token">
                                    <input type="hidden" name="source[page_id]" id="source_page_id">
                                    <input type="hidden" name="source[id]" id="source_id">
                                    <input type="hidden" name="source[_csrf_token]" value="<?php echo wp_create_nonce('strix_google_reviews_admin'); ?>" id="source__csrf_token">
                                    <input type="hidden" name="avatar_url">
                                    <input type="hidden" name="review_url">
                                    <input type="hidden" name="write_review_url">
                                    <input type="hidden" name="stat[count]">
                                    <input type="hidden" name="stat[score]">
                                    <input type="hidden" name="stat[details]">
                                    <input type="hidden" name="reviews">
                                    <input type="hidden" name="categories">

                                    <div class="row source-connect">
                                        <label class="col-3"><?php _e('Google Business Profile name or location', 'strix-google-reviews-admin'); ?></label>
                                        <div class="col source-input-container">
                                            <input id="source-google-autocomplete" type="text" class="source-input form-control pac-target-input" placeholder="<?php _e('Type your Google Business Profile name, location, Place ID or Google Maps URL', 'strix-google-reviews-admin'); ?>" autocomplete="off">
                                            <i class="fa fa-spinner fa-spin input-loading-icon"></i>
                                            <small class="form-text text-muted">
                                                <?php _e('Start typing your Google Business Profile name or your location, then select your business from the drop-down list.', 'strix-google-reviews-admin'); ?><br>
                                                <?php _e('Alternatively, you can enter either your Place ID or your Google Maps URL.', 'strix-google-reviews-admin'); ?>
                                            </small>
                                            <small class="form-text source-error error-wrong-link"><?php _e('Please add your URL again: this is not a valid Google page.', 'strix-google-reviews-admin'); ?></small>
                                            <div class="alert alert-danger mt-3 mb-0 source-error error-page-not-found">
                                                <div class="row">
                                                    <div class="col-3">
                                                        <img class="img-fluid" src="https://cdn.trustindex.io/assets/img/trustindex-google-search-2.jpg">
                                                    </div>
                                                    <div class="col">
                                                        <?php _e('We could not find anything with this:', 'strix-google-reviews-admin'); ?> <strong class="source-input-string"></strong>
                                                        <br><br>
                                                        <?php _e('Please provide an URL where you see something like this in the Google Maps.', 'strix-google-reviews-admin'); ?>
                                                        <br><br>
                                                        <strong><?php _e('Or enter the Place ID if you want to be sure.', 'strix-google-reviews-admin'); ?></strong>
                                                        <br>
                                                        <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" class="alert-link" target="_blank"><?php _e('You can find it here after typing the address in the search bar on the map.', 'strix-google-reviews-admin'); ?></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row source-connect mt-2 d-none">
                                        <label class="col-3"><?php _e('Source', 'strix-google-reviews-admin'); ?></label>
                                        <div class="col source-selected-container"></div>
                                    </div>

                                    <div class="d-none" id="source-selected-item-template">
                                        <div class="row source-selected-item">
                                            <div class="col col-auto source-selected-item-image">
                                                <img alt-src="%src%" alt="%name%">
                                            </div>
                                            <div class="col">
                                                <div class="source-selected-item-title">%name%</div>
                                                <div class="source-selected-item-category">%category%</div>
                                                <div class="source-selected-item-description">%description%</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-none" id="source-selected-item-template-with-button">
                                        <div class="row source-selected-item">
                                            <div class="col col-auto source-selected-item-image">
                                                <img alt-src="%src%" alt="%name%">
                                            </div>
                                            <div class="col">
                                                <div class="source-selected-item-title">%name%</div>
                                                <div class="source-selected-item-category">%category%</div>
                                                <div class="source-selected-item-description">%description%</div>
                                            </div>
                                            <div class="col-2 text-right">
                                                <button type="button" href="%url%" class="btn btn-primary btn-source-choose btn-loading"><?php _e('Choose', 'strix-google-reviews-admin'); ?></button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="content-box-footer">
                                        <button type="button" class="btn btn-primary pull-right btn-loading btn-source-connect disabled"><?php _e('Connect', 'strix-google-reviews-admin'); ?></button>
                                        <div class="clear"></div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modal-sys" tabindex="-1" role="dialog" aria-labelledby="System Modal Box" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php _e('Modal title', 'strix-google-reviews-admin'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body"><?php _e('Modal content', 'strix-google-reviews-admin'); ?></div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-dark" data-dismiss="modal"><?php _e('Close', 'strix-google-reviews-admin'); ?></a>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        var get_reviews_limit = 10;

        // Google autocomplete API
        let placeFindedFor = null;
        let cache = [];

        window.initGoogle = function() {
            let input = document.getElementById('source-google-autocomplete');

            // listen to ENTER keypress
            input.addEventListener('keydown', function(event) {
                if (event.keyCode === 13 && document.querySelectorAll('.pac-container .pac-item-selected').length === 0) {
                    let newEvent = new Event('keydown');
                    newEvent.keyCode = 40; // DOWN arrow

                    // there is only 1 prediction, select it
                    if (document.querySelectorAll('.pac-container .pac-item').length === 1) {
                        input.dispatchEvent(newEvent);
                    }
                    else if (document.querySelectorAll('.pac-container .pac-item').length === 0) {
                        event.preventDefault();
                        checkValue();
                    }
                    // more than 1 prediction, display list again
                    else {
                        newEvent.keyCode = 39; // RIGHT arrow
                        setTimeout(function() {
                            input.dispatchEvent(newEvent);
                        }, 10);
                    }
                }
                else if (event.keyCode === 13) {
                    event.preventDefault();
                }
            });

            // register autocomplete API
            let autocomplete = new google.maps.places.Autocomplete(input, {
                fields: ['formatted_address', 'icon', 'name', 'photos', 'place_id', 'types', 'user_ratings_total'],
                types: ['establishment']
            });

            // wait for place selected
            autocomplete.addListener('place_changed', function() {
                let place = autocomplete.getPlace();

                if (place.place_id) {
                    showPlace({
                        page_id: place.place_id,
                        name: place.name,
                        avatar_url: (place.photos && place.photos.length) ? place.photos[0].getUrl() : place.icon,
                        description: place.formatted_address || "",
                        reviews: { score: 0, count: place.user_ratings_total, list: '' },
                        type: place.types.join(', ')
                    });
                    placeFindedFor = input.value;
                }
            });

            // autocomplete changes event
            let createAutocomplateListener = function() {
                let autocompleteContainer = document.querySelector('.pac-container');
                if (autocompleteContainer) {
                    let observer = new MutationObserver(() => {
                        document.querySelectorAll('.source-connect .source-error').forEach(el => el.style.display = 'none');
                        if (document.querySelectorAll('.pac-container .pac-item').length === 0) {
                            setTimeout(checkValue, 350);
                        }
                    });
                    observer.observe(autocompleteContainer, { childList: true });
                    return true;
                }
                return false;
            };

            let autocompleteInterval = setInterval(() => {
                if (createAutocomplateListener()) {
                    clearInterval(autocompleteInterval);
                }
            }, 100);
        };

        // Show place to select
        let showPlace = function(place, doNotShowResult) {
            if (place.name.length && place.name.toLowerCase().search('trustindex') !== -1) {
                return false;
            }

            if (place.page_id[0] !== 'C' && place.page_id.indexOf('&v=') === -1) {
                return false;
            }

            // set source's hidden inputs
            document.getElementById('source_name').value = place.name;
            document.getElementById('source_page_id').value = place.page_id;
            document.querySelector('input[name="avatar_url"]').value = place.avatar_url;
            document.querySelector('input[name="review_url"]').value = place.review_url || '';
            document.querySelector('input[name="write_review_url"]').value = place.write_review_url || '';
            document.querySelector('input[name="stat[count]"]').value = place.reviews.count;
            document.querySelector('input[name="stat[score]"]').value = place.reviews.score;
            document.querySelector('input[name="stat[details]"]').value = JSON.stringify(place.reviews.details || []);
            document.querySelector('input[name="categories"]').value = place.type;

            if (typeof place.reviews.list != 'undefined') {
                document.querySelector('input[name="reviews"]').value = JSON.stringify(place.reviews.list).replace(/^"(.*)"$/, '$1');
            } else {
                document.querySelector('input[name="reviews"]').value = '';
            }

            document.querySelector('input[name="source[description]"]').value = place.description;

            if (!doNotShowResult) {
                let container = document.querySelector('.source-connect .source-selected-container');
                container.innerHTML = document.getElementById('source-selected-item-template').innerHTML
                    .replace(/%src%/g, place.avatar_url).replace('alt-src', 'src')
                    .replace(/%name%/g, place.name)
                    .replace(/%category%/g, place.type)
                    .replace(/%description%/g, place.description);
                container.parentElement.classList.remove('d-none');

                // enable 'Connect' button
                document.querySelector('.btn-source-connect').classList.remove('disabled');
            }
        };

        // Check value function
        let checkValue = function() {
            let value = document.getElementById('source-google-autocomplete').value.trim();
            document.querySelectorAll('.source-connect .source-error').forEach(el => el.style.display = 'none');

            if (document.querySelector('.source-connect .source-input-container').classList.contains('is-loading')) {
                return false;
            }

            // check if url given
            if (value.substr(0, 4) === 'www.' || value.substr(0, 7) === 'http://' || value.substr(0, 8) === 'https://') {
                // check google url
                if (!/^(www\.|https?:\/\/)(www\.)?google\.[^\/]+\/maps/gm.test(value)
                    && !isShoppingUrl(value)
                    && !/^(www\.|https?:\/\/)(www\.)?g\.page\/[^\/]+\/(?:review|share)/gm.test(value)
                    && !/^(www\.|https?:\/\/)(www\.)??maps\.google\.[^\/]+\/maps\?cid=\d+$/gm.test(value)
                    && !/^(www\.|https?:\/\/)(www\.)??maps\.app\.goo\.[^\/]+\/[^?#]*$/gm.test(value)
                ) {
                    document.querySelector('.source-connect .error-wrong-link').style.display = 'block';
                    return;
                }

                document.querySelector('.source-connect .source-input-container').classList.add('is-loading');

                // check shopping
                if (isShoppingUrl(value)) {
                    let regex = /(?:shopping\/ratings\/account\/metrics\?q=|customerreviews\.google\.com\/v\/merchant\?q=)([^&]+&c=\w+&v=\d+)/;
                    let m = regex.exec(value);
                    if (!m || m[1] === undefined || m[1] == "") {
                        document.querySelector('.source-connect .source-input-container').classList.remove('is-loading');
                        document.querySelector('.source-connect .error-wrong-link').style.display = 'block';
                        return;
                    }
                    let pageId = escape(m[1]).replace(/&hl=([a-z_-]+)/, '');
                    getPageDetails(pageId, () => document.querySelector('.source-connect .source-input-container').classList.remove('is-loading'));
                } else {
                    findPlaceRequest(value, () => document.querySelector('.source-connect .source-input-container').classList.remove('is-loading'));
                }
                return false;
            }

            // pageId given
            if (value.substr(0, 4) === 'ChIJ' && value.length >= 20 && value.indexOf(' ') === -1 && value.indexOf('.') === -1) {
                document.querySelector('.source-connect .source-input-container').classList.add('is-loading');
                getPageDetails(value, () => document.querySelector('.source-connect .source-input-container').classList.remove('is-loading'));
                return false;
            }

            // search query not found -> show error message to try Google Maps
            if (value.length > 0 && placeFindedFor !== value) {
                document.querySelector('.source-connect .error-page-not-found').style.display = 'block';
                document.querySelector('.source-connect .error-page-not-found .source-input-string').innerHTML = value;
            }
        };

        let getPageDetails = function(pageId, callback, doNotShowResult) {
            let successHandle = function(res) {
                cache[pageId] = res;

                if (res.success && res.result) {
                    processResult(res, null, doNotShowResult);
                } else if (typeof res.possible_places !== 'undefined' && res.possible_places.length !== 0) {
                    showPossiblePlaces(res.possible_places);
                } else {
                    document.querySelector('.source-connect .error-page-not-found').style.display = 'block';
                    document.querySelector('.source-connect .error-page-not-found .source-input-string').innerHTML = pageId;
                }

                if (callback) {
                    callback();
                }
            };

            if (typeof cache[pageId] !== 'undefined') {
                successHandle(cache[pageId]);
                return true;
            }

            // AJAX call to get page details
            let xhr = new XMLHttpRequest();
            xhr.open('GET', 'https://admin.trustindex.io/api/getPageDetails?page_id=' + encodeURIComponent(pageId) + '&platform=google&reviews=10');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    let res = JSON.parse(xhr.responseText);
                    successHandle(res);
                }
            };
            xhr.send();
        };

        let processResult = function(object, url, doNotShowResult) {
            if (object.result && object.result.name) {
                object.result.description = object.result.address || object.result.website;
                showPlace(object.result, doNotShowResult);
            } else if (typeof object.possible_places !== 'undefined') {
                showPossiblePlaces(object.possible_places);
            } else {
                document.querySelector('.source-connect .error-page-not-found').style.display = 'block';
                document.querySelector('.source-connect .error-page-not-found .source-input-string').innerHTML = url;
            }
        };

        let showPossiblePlaces = function(places) {
            let html = "";
            for (let i in places) {
                let item = places[i];
                html += document.getElementById('source-selected-item-template-with-button').innerHTML
                    .replace(/\%src\%/g, item.avatar_url).replace('alt-src', 'src')
                    .replace(/\%name\%/g, item.name)
                    .replace(/\%url\%/g, item.url)
                    .replace(/\%description\%/g, (item.address || "") ? '<a href="'+ item.url +'" target="_blank">'+ item.address +'</a>' : '')
                    .replace(/\%category\%/g, item.type || "");
            }

            let container = document.querySelector('.source-connect .source-selected-container');
            container.innerHTML = html;
            container.parentElement.classList.remove('d-none');
            container.querySelectorAll('.source-selected-item').forEach(el => el.style.display = 'block');

            // disable 'Connect' button
            document.querySelector('.btn-source-connect').classList.add('disabled');
        };

        let findPlaceRequest = function(url, callback) {
            if (typeof cache[url] !== 'undefined') {
                processResult(cache[url], url);
                if (callback) callback();
                return true;
            }

            let xhr = new XMLHttpRequest();
            xhr.open('POST', 'https://admin.trustindex.io/api/findPlaceId');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    let r = JSON.parse(xhr.responseText);
                    cache[url] = r;
                    processResult(r, url);
                    if (callback) callback();
                }
            };
            xhr.send('url=' + encodeURIComponent(url) + '&reviews=10');
        };

        let isShoppingUrl = function(value) {
            return (/^(www\.|https?:\/\/)(www\.)?google\.[^\/]+\/shopping\//gm.test(value) || /customerreviews\.google\.com\/v\/merchant/.test(value));
        };

        // Event listeners
        document.getElementById('source-google-autocomplete').addEventListener('paste', () => setTimeout(checkValue, 50));
        document.getElementById('source-google-autocomplete').addEventListener('change', checkValue);

        // Connect button
        document.querySelector('.btn-source-connect').addEventListener('click', function(e) {
            e.preventDefault();
            this.blur();

            let pageId = document.getElementById('source_page_id').value;
            getPageDetails(pageId, () => {
                setTimeout(() => {
                    // Submit form data to main plugin
                    let formData = new FormData(document.getElementById('form-source'));

                    fetch(strix_admin_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': strix_admin_ajax.nonce
                        },
                        body: new URLSearchParams({
                            action: 'strix_google_reviews_admin_connect',
                            ...Object.fromEntries(formData)
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && window.opener) {
                            let place = {
                                id: document.getElementById('source_page_id').value,
                                name: document.getElementById('source_name').value,
                                avatar_url: document.querySelector('input[name="avatar_url"]').value,
                                review_url: document.querySelector('input[name="review_url"]').value,
                                write_review_url: document.querySelector('input[name="write_review_url"]').value,
                                address: document.querySelector('input[name="source[description]"]').value,
                                rating_number: document.querySelector('input[name="stat[count]"]').value,
                                rating_numbers: [],
                                rating_numbers_last: [],
                                rating_score: document.querySelector('input[name="stat[score]"]').value,
                                request_id: data.request_id || "",
                                timestamp: data.timestamp || 0
                            };

                            if (document.querySelector('input[name="reviews"]').value.trim()) {
                                place.reviews = JSON.parse(document.querySelector('input[name="reviews"]').value.trim());
                            }

                            if (document.querySelector('input[name="stat[details]"]').value.trim()) {
                                let tmp = JSON.parse(document.querySelector('input[name="stat[details]"]').value.trim());
                                place.rating_numbers = tmp['count-by-rating'];
                                place.rating_numbers_last = tmp['count-by-rating-last'];
                            }

                            if (typeof place.reviews === 'undefined' || !place.reviews) {
                                place.reviews = [];
                            }

                            window.opener.postMessage(place, '*');
                            window.close();
                        }
                    });
                }, 100);
            }, true);
        });

        // possible places choose
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-source-choose')) {
                e.preventDefault();
                let btn = e.target;
                let container = btn.closest('.source-selected-item');

                // remove other rows
                let otherSources = container.closest('.source-selected-container').querySelectorAll('.source-selected-item');
                otherSources.forEach(el => {
                    if (el !== container) el.style.display = 'none';
                });

                // loading animation
                btn.classList.add('btn-loading-animation', 'disabled');
                btn.disabled = true;

                // get place ID
                findPlaceRequest(btn.getAttribute('href'), () => {});

                // restore source connect button to save source
                document.querySelector('.btn-source-connect').classList.remove('disabled');
                document.querySelector('.btn-source-connect').disabled = false;
            }
        });
    </script>
</body>
</html>
        <?php
    }

    /**
     * Save admin settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'strix_admin_settings')) {
            wp_die(__('Security check failed', 'strix-google-reviews-admin'));
        }

        $settings = array(
            'google_maps_api_key' => sanitize_text_field($_POST['google_maps_api_key'] ?? ''),
            'debug_mode' => isset($_POST['debug_mode']) ? 1 : 0,
            'cache_time' => intval($_POST['cache_time'] ?? 3600),
            'max_reviews' => intval($_POST['max_reviews'] ?? 50),
            'enable_replies' => isset($_POST['enable_replies']) ? 1 : 0,
        );

        update_option('strix_google_reviews_admin_settings', $settings);

        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'strix-google-reviews-admin') . '</p></div>';
    }

    /**
     * Get admin settings
     */
    private function get_settings() {
        return get_option('strix_google_reviews_admin_settings', array(
            'google_maps_api_key' => '',
            'debug_mode' => 0,
            'cache_time' => 3600,
            'max_reviews' => 50,
            'enable_replies' => 1,
        ));
    }

    /**
     * Get Google Maps API key
     */
    public function get_google_maps_api_key() {
        $settings = $this->get_settings();
        return $settings['google_maps_api_key'] ?? '';
    }

    /**
     * Calculate average rating from reviews
     */
    private function calculate_average_rating($reviews) {
        if (empty($reviews)) return 0;

        $total = 0;
        foreach ($reviews as $review) {
            $total += $review['rating'] ?? 0;
        }

        return $total / count($reviews);
    }

    /**
     * Count positive reviews (4+ stars)
     */
    private function count_positive_reviews($reviews) {
        if (empty($reviews)) return 0;

        $count = 0;
        foreach ($reviews as $review) {
            if (($review['rating'] ?? 0) >= 4) $count++;
        }

        return $count;
    }

    /**
     * Handle Google connect AJAX
     */
    public function handle_google_connect() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'strix_google_reviews_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Get form data
        $place_data = array(
            'id' => sanitize_text_field($_POST['source']['page_id'] ?? ''),
            'name' => sanitize_text_field($_POST['source']['name'] ?? ''),
            'avatar_url' => esc_url_raw($_POST['avatar_url'] ?? ''),
            'review_url' => esc_url_raw($_POST['review_url'] ?? ''),
            'write_review_url' => esc_url_raw($_POST['write_review_url'] ?? ''),
            'address' => sanitize_text_field($_POST['source']['description'] ?? ''),
            'rating_number' => intval($_POST['stat']['count'] ?? 0),
            'rating_score' => floatval($_POST['stat']['score'] ?? 0),
            'categories' => sanitize_text_field($_POST['categories'] ?? ''),
            'reviews' => array(),
            'rating_numbers' => array(),
            'rating_numbers_last' => array()
        );

        // Parse reviews if present
        if (!empty($_POST['reviews'])) {
            $reviews = json_decode(stripslashes($_POST['reviews']), true);
            if (is_array($reviews)) {
                $place_data['reviews'] = $reviews;
            }
        }

        // Parse rating details if present
        if (!empty($_POST['stat']['details'])) {
            $details = json_decode(stripslashes($_POST['stat']['details']), true);
            if (is_array($details)) {
                $place_data['rating_numbers'] = $details['count-by-rating'] ?? array();
                $place_data['rating_numbers_last'] = $details['count-by-rating-last'] ?? array();
            }
        }

        // Here you would typically save to database or send to main plugin
        // For now, just return success with request ID
        wp_send_json_success(array(
            'request_id' => 'req_' . time(),
            'timestamp' => time(),
            'place_data' => $place_data
        ));
    }

    /**
     * Handle get reviews AJAX
     */
    public function handle_get_reviews() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'strix_google_reviews_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // This would integrate with Trustindex API to get reviews
        // For now, return mock data
        wp_send_json_success(array(
            'reviews' => array(),
            'message' => 'Reviews retrieved successfully'
        ));
    }
}

/**
 * Get Google Maps API key for external use
 */
function strix_get_google_maps_api_key() {
    if (class_exists('Strix_Google_Reviews_Admin')) {
        $admin = Strix_Google_Reviews_Admin::get_instance();
        return $admin->get_google_maps_api_key();
    }
    return '';
}

/**
 * Initialize the plugin
 */
function strix_google_reviews_admin_init() {
    Strix_Google_Reviews_Admin::get_instance();
}
add_action('plugins_loaded', 'strix_google_reviews_admin_init');

/**
 * Activation hook
 */
function strix_google_reviews_admin_activate() {
    // Admin panel works independently - no dependency on main plugin required
    // This allows developers to configure API keys and test functionality separately

    // Optional: Check if WordPress environment is compatible
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        wp_die(__('Strix Google Reviews Admin requires PHP 7.4 or higher.', 'strix-google-reviews-admin'));
    }

    // Optional: Check if WordPress version is compatible
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        wp_die(__('Strix Google Reviews Admin requires WordPress 5.0 or higher.', 'strix-google-reviews-admin'));
    }
}
register_activation_hook(__FILE__, 'strix_google_reviews_admin_activate');

/**
 * Deactivation hook
 */
function strix_google_reviews_admin_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'strix_google_reviews_admin_deactivate');