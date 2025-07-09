<?php
/**
 * Plugin Name: WooCommerce to Mautic Integration
 * Description: Simple integration to send WooCommerce order data to Mautic
 * Version: 1.0.0
 * Author: Marko Tiosavljevic
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooMauticIntegration {
    
    private $option_name = 'woo_mautic_settings';
    private $log_option = 'woo_mautic_logs';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('woocommerce_order_status_completed', array($this, 'send_to_mautic'));
        add_action('woocommerce_order_status_processing', array($this, 'send_to_mautic'));
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>WooCommerce to Mautic Integration requires WooCommerce to be installed and active.</p></div>';
    }
    
    public function admin_menu() {
        add_options_page(
            'Mautic Integration',
            'Mautic Integration',
            'manage_options',
            'woo-mautic-integration',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            $settings = array(
                'mautic_url' => sanitize_url($_POST['mautic_url']),
                'username' => sanitize_text_field($_POST['username']),
                'password' => sanitize_text_field($_POST['password']),
                'enabled' => isset($_POST['enabled']) ? 1 : 0
            );
            update_option($this->option_name, $settings);
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        if (isset($_POST['clear_logs'])) {
            delete_option($this->log_option);
            echo '<div class="notice notice-success"><p>Logs cleared!</p></div>';
        }
        
        $settings = get_option($this->option_name, array());
        $logs = get_option($this->log_option, array());
        
        ?>
        <div class="wrap">
            <h1>WooCommerce to Mautic Integration</h1>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="mautic_url">Mautic URL</label></th>
                        <td><input type="url" name="mautic_url" id="mautic_url" value="<?php echo esc_attr($settings['mautic_url'] ?? ''); ?>" class="regular-text" placeholder="https://your-mautic.com" /></td>
                    </tr>
                    <tr>
                        <th><label for="username">Username</label></th>
                        <td><input type="text" name="username" id="username" value="<?php echo esc_attr($settings['username'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="password">Password</label></th>
                        <td><input type="password" name="password" id="password" value="<?php echo esc_attr($settings['password'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="enabled">Enable Integration</label></th>
                        <td><input type="checkbox" name="enabled" id="enabled" value="1" <?php checked($settings['enabled'] ?? 0, 1); ?> /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h2>Activity Logs</h2>
            <form method="post" style="margin-bottom: 20px;">
                <input type="submit" name="clear_logs" class="button" value="Clear Logs" />
            </form>
            
            <div style="background: #f1f1f1; padding: 15px; border-radius: 4px; max-height: 400px; overflow-y: auto;">
                <?php if (empty($logs)): ?>
                    <p><em>No logs available</em></p>
                <?php else: ?>
                    <?php foreach (array_reverse($logs) as $log): ?>
                        <div style="margin-bottom: 10px; padding: 10px; background: white; border-radius: 3px;">
                            <strong><?php echo esc_html($log['timestamp']); ?></strong> - 
                            <span style="color: <?php echo $log['success'] ? 'green' : 'red'; ?>;">
                                <?php echo $log['success'] ? 'SUCCESS' : 'ERROR'; ?>
                            </span><br>
                            <small><?php echo esc_html($log['message']); ?></small>
                            <?php if (!empty($log['data'])): ?>
                                <details style="margin-top: 5px;">
                                    <summary>Data sent</summary>
                                    <pre style="font-size: 11px; margin: 5px 0; background: #f9f9f9; padding: 5px;"><?php echo esc_html(json_encode($log['data'], JSON_PRETTY_PRINT)); ?></pre>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function send_to_mautic($order_id) {
        $settings = get_option($this->option_name, array());
        
        if (empty($settings['enabled']) || empty($settings['mautic_url']) || empty($settings['username']) || empty($settings['password'])) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Prepare contact data
        $contact_data = array(
            'email' => $order->get_billing_email(),
            'firstname' => $order->get_billing_first_name(),
            'lastname' => $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'company' => $order->get_billing_company(),
            'address1' => $order->get_billing_address_1(),
            'address2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'zipcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
            'order_total' => $order->get_total(),
            'order_id' => $order_id,
            'order_status' => $order->get_status(),
            'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'customer_id' => $order->get_customer_id(),
            'payment_method' => $order->get_payment_method_title(),
            'currency' => $order->get_currency()
        );
        
        // Add order items
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total(),
                'sku' => $product ? $product->get_sku() : ''
            );
        }
        $contact_data['order_items'] = json_encode($items);
        
        // Send to Mautic
        $response = $this->send_to_mautic_api($contact_data, $settings);
        
        // Log the attempt
        $this->log_activity($response['success'], $response['message'], $contact_data);
    }
    
    private function send_to_mautic_api($data, $settings) {
        $mautic_url = rtrim($settings['mautic_url'], '/');
        $auth = base64_encode($settings['username'] . ':' . $settings['password']);
        
        // First, try to create/update contact
        $contact_response = wp_remote_post($mautic_url . '/api/contacts/new', array(
            'headers' => array(
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($contact_response)) {
            return array(
                'success' => false,
                'message' => 'Connection error: ' . $contact_response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($contact_response);
        $response_body = wp_remote_retrieve_body($contact_response);
        
        if ($response_code >= 200 && $response_code < 300) {
            return array(
                'success' => true,
                'message' => 'Contact successfully sent to Mautic (HTTP ' . $response_code . ')'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Mautic API error (HTTP ' . $response_code . '): ' . $response_body
            );
        }
    }
    
    private function log_activity($success, $message, $data = null) {
        $logs = get_option($this->log_option, array());
        
        $log_entry = array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'success' => $success,
            'message' => $message,
            'data' => $data
        );
        
        $logs[] = $log_entry;
        
        // Keep only last 50 logs
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_option($this->log_option, $logs);
    }
}

// Initialize the plugin
new WooMauticIntegration();
?>
