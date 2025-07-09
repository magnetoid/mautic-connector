<?php
/**
 * Plugin Name: WooCommerce to Mautic Integration
 * Description: Simple plugin to transfer WooCommerce data to Mautic
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceMauticIntegration {
    
    private $mautic_url;
    private $mautic_username;
    private $mautic_password;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('woocommerce_order_status_completed', array($this, 'sync_order_to_mautic'));
        add_action('woocommerce_new_customer', array($this, 'sync_customer_to_mautic'));
        add_action('user_register', array($this, 'sync_user_registration'));
    }
    
    public function init() {
        $this->mautic_url = get_option('wc_mautic_url', '');
        $this->mautic_username = get_option('wc_mautic_username', '');
        $this->mautic_password = get_option('wc_mautic_password', '');
    }
    
    public function admin_menu() {
        add_options_page(
            'WooCommerce Mautic Settings',
            'WC Mautic',
            'manage_options',
            'wc-mautic-settings',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            update_option('wc_mautic_url', sanitize_text_field($_POST['mautic_url']));
            update_option('wc_mautic_username', sanitize_text_field($_POST['mautic_username']));
            update_option('wc_mautic_password', sanitize_text_field($_POST['mautic_password']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $mautic_url = get_option('wc_mautic_url', '');
        $mautic_username = get_option('wc_mautic_username', '');
        $mautic_password = get_option('wc_mautic_password', '');
        
        ?>
        <div class="wrap">
            <h1>WooCommerce to Mautic Settings</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Mautic URL</th>
                        <td><input type="url" name="mautic_url" value="<?php echo esc_attr($mautic_url); ?>" class="regular-text" placeholder="https://your-mautic-instance.com" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Mautic Username</th>
                        <td><input type="text" name="mautic_username" value="<?php echo esc_attr($mautic_username); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Mautic Password</th>
                        <td><input type="password" name="mautic_password" value="<?php echo esc_attr($mautic_password); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <hr>
            <h2>Manual Sync</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=wc-mautic-settings&action=sync_all_customers'); ?>" class="button button-secondary">Sync All Customers</a>
                <a href="<?php echo admin_url('admin.php?page=wc-mautic-settings&action=sync_all_orders'); ?>" class="button button-secondary">Sync All Orders</a>
            </p>
        </div>
        <?php
        
        // Handle manual sync actions
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'sync_all_customers') {
                $this->sync_all_customers();
            } elseif ($_GET['action'] === 'sync_all_orders') {
                $this->sync_all_orders();
            }
        }
    }
    
    private function get_mautic_auth_token() {
        if (empty($this->mautic_url) || empty($this->mautic_username) || empty($this->mautic_password)) {
            return false;
        }
        
        $auth_url = rtrim($this->mautic_url, '/') . '/api/oauth/v2/token';
        
        $response = wp_remote_post($auth_url, array(
            'body' => array(
                'grant_type' => 'password',
                'username' => $this->mautic_username,
                'password' => $this->mautic_password,
                'client_id' => '1_3bcbxd9e24g0c4cc0gwcocgsggkwcokgcgskwg8gc80ok48cs',
                'client_secret' => '4ok2x70rlfokc8g0wws8c8kwcokwgcwsokk0gkkg0g8s4c0cs'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['access_token']) ? $body['access_token'] : false;
    }
    
    private function make_mautic_request($endpoint, $data = array(), $method = 'POST') {
        $token = $this->get_mautic_auth_token();
        if (!$token) {
            return false;
        }
        
        $url = rtrim($this->mautic_url, '/') . '/api/' . ltrim($endpoint, '/');
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    public function sync_customer_to_mautic($customer_id) {
        $customer = new WC_Customer($customer_id);
        $this->sync_contact_to_mautic($customer);
    }
    
    public function sync_user_registration($user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            $customer = new WC_Customer($user_id);
            $this->sync_contact_to_mautic($customer);
        }
    }
    
    private function sync_contact_to_mautic($customer) {
        $contact_data = array(
            'firstname' => $customer->get_first_name(),
            'lastname' => $customer->get_last_name(),
            'email' => $customer->get_email(),
            'phone' => $customer->get_billing_phone(),
            'address1' => $customer->get_billing_address_1(),
            'address2' => $customer->get_billing_address_2(),
            'city' => $customer->get_billing_city(),
            'state' => $customer->get_billing_state(),
            'zipcode' => $customer->get_billing_postcode(),
            'country' => $customer->get_billing_country(),
            'company' => $customer->get_billing_company(),
            'woocommerce_customer_id' => $customer->get_id(),
            'date_added' => $customer->get_date_created() ? $customer->get_date_created()->format('Y-m-d H:i:s') : '',
            'tags' => array('woocommerce', 'customer')
        );
        
        // Remove empty values
        $contact_data = array_filter($contact_data, function($value) {
            return !empty($value);
        });
        
        $response = $this->make_mautic_request('contacts/new', $contact_data);
        
        if ($response && isset($response['contact']['id'])) {
            update_user_meta($customer->get_id(), 'mautic_contact_id', $response['contact']['id']);
        }
        
        return $response;
    }
    
    public function sync_order_to_mautic($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // First, ensure the customer exists in Mautic
        $customer_id = $order->get_customer_id();
        if ($customer_id) {
            $customer = new WC_Customer($customer_id);
            $this->sync_contact_to_mautic($customer);
            $mautic_contact_id = get_user_meta($customer_id, 'mautic_contact_id', true);
        } else {
            // Guest customer - create contact from order data
            $contact_data = array(
                'firstname' => $order->get_billing_first_name(),
                'lastname' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'address1' => $order->get_billing_address_1(),
                'address2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'zipcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'company' => $order->get_billing_company(),
                'tags' => array('woocommerce', 'guest-customer')
            );
            
            $contact_data = array_filter($contact_data, function($value) {
                return !empty($value);
            });
            
            $response = $this->make_mautic_request('contacts/new', $contact_data);
            $mautic_contact_id = $response && isset($response['contact']['id']) ? $response['contact']['id'] : null;
        }
        
        if (!$mautic_contact_id) {
            return false;
        }
        
        // Prepare order data
        $order_items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $order_items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total(),
                'product_id' => $product ? $product->get_id() : 0,
                'sku' => $product ? $product->get_sku() : ''
            );
        }
        
        // Create custom fields data for the order
        $order_data = array(
            'woocommerce_order_id' => $order_id,
            'woocommerce_order_number' => $order->get_order_number(),
            'woocommerce_order_status' => $order->get_status(),
            'woocommerce_order_total' => $order->get_total(),
            'woocommerce_order_currency' => $order->get_currency(),
            'woocommerce_order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'woocommerce_payment_method' => $order->get_payment_method_title(),
            'woocommerce_shipping_method' => $order->get_shipping_method(),
            'woocommerce_order_items' => json_encode($order_items),
            'woocommerce_customer_note' => $order->get_customer_note(),
            'tags' => array('woocommerce', 'order-completed')
        );
        
        // Update contact with order information
        $update_response = $this->make_mautic_request('contacts/' . $mautic_contact_id . '/edit', $order_data, 'PATCH');
        
        // Also create a custom event for the order
        $event_data = array(
            'name' => 'WooCommerce Order Completed',
            'type' => 'woocommerce.order.completed',
            'contact' => $mautic_contact_id,
            'properties' => array(
                'order_id' => $order_id,
                'order_total' => $order->get_total(),
                'order_currency' => $order->get_currency(),
                'payment_method' => $order->get_payment_method_title(),
                'items_count' => count($order_items)
            )
        );
        
        $this->make_mautic_request('events/new', $event_data);
        
        return $update_response;
    }
    
    public function sync_all_customers() {
        $customers = get_users(array('role' => 'customer'));
        $synced = 0;
        
        foreach ($customers as $user) {
            $customer = new WC_Customer($user->ID);
            if ($this->sync_contact_to_mautic($customer)) {
                $synced++;
            }
        }
        
        echo '<div class="notice notice-success"><p>Synced ' . $synced . ' customers to Mautic.</p></div>';
    }
    
    public function sync_all_orders() {
        $orders = wc_get_orders(array(
            'status' => 'completed',
            'limit' => 100
        ));
        
        $synced = 0;
        
        foreach ($orders as $order) {
            if ($this->sync_order_to_mautic($order->get_id())) {
                $synced++;
            }
        }
        
        echo '<div class="notice notice-success"><p>Synced ' . $synced . ' orders to Mautic.</p></div>';
    }
}

// Initialize the plugin
new WooCommerceMauticIntegration();

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create any necessary database tables or options here
    add_option('wc_mautic_url', '');
    add_option('wc_mautic_username', '');
    add_option('wc_mautic_password', '');
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up if necessary
});
?>
