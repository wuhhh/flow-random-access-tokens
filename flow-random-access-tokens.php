<?php
/*
Plugin Name: Flow: Random Access Tokens
Plugin URI: https://github.com/wuhhh/flow-random-access-tokens
Description: Create and store random access tokens against posts and users
Version: 0.1
Author: Huw Roberts
Author URI: http://www.rootsy.co.uk
Copyright: Huw Roberts
Text Domain: flow-act-quotes
 */

if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

class FlowRandTokens
{

    /**
     * Returns the instance.
     *
     * @access public
     * @return object
     */
    public static function get_instance()
    {
        
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self;
            $instance->setup();
        }

        return $instance;

    }

    /**
     * Constructor method.
     *
     * @access private
     * @return void
     */
    private function __construct()
    {}

    public function setup()
    {

        add_action('init', array($this, 'initialize_plugin'), 1, 0);

    }

    /**
     * Initialize
     */
    public function initialize_plugin()
    {

        // Global defaults
        $this->path = trailingslashit(plugin_dir_path(__FILE__));
        $this->url = trailingslashit(plugin_dir_url(__FILE__));

        // Include the random_compat lib:
        //  https://github.com/paragonie/random_compat
        require_once $this->path . "lib/random_compat/lib/random.php";

        // Register callbacks for post and user save actions
        add_action('user_register', array($this, '_create_user_meta_token'), 10, 1);
        add_action('profile_update', array($this, '_update_user'), 10, 2);
        add_action('save_post', array($this, '_create_post_meta_token'), 10, 3);

        // Register meta keys so they can be used in REST API
        //  Note: as of WordPress 4.9.4 for _any_ post type, first param is post
        $args = array(
            'type' => 'string',
            'description' => 'Flow random access token',
            'single' => true,
            'show_in_rest' => true,
        );
        register_meta('post', 'flow_rand_tok', $args);

    }

    /**
     * Create the user meta token for all users
     */
    public function _create_user_meta_token($user_id)
    {

        // Make and store the token
        $token = $this->_generate_token(9);

        // Loop until we get a unique result
        while ($this->_exists_user_meta_token($token)) {

            $token = $this->_generate_token(9);

        }

        update_user_meta($user_id, 'flow_rand_tok', $token);

    }

    /**
     * Check the token meta exists when a user profile is updated
     * (Added in order to create tokens for already created users)
     */
    public function _update_user($user_id, $old_user_data)
    {

        $current = get_user_meta($user_id, 'flow_rand_tok', true);

        if (empty($current)) {

            $this->_create_user_meta_token($user_id);

        }

    }

    /**
     * Create the post meta token for matching post types
     */
    public function _create_post_meta_token($post_id, $post, $update)
    {

        $post_type = get_post_type($post_id);

        $valid_post_types = array('job_sheet', 'act');

        // Return if post type doesn't match valid array
        if (!in_array($post_type, $valid_post_types)) {
            return;
        }

        // Updating, check a token exists
        if ($update) {

            $current = get_post_meta($post_id, 'flow_rand_tok', true);

            // Token exists, return
            if (!empty($current)) {
                return;
            }

        }

        // Make and store the token
        $token = $this->_generate_token(9);

        // Loop until we get a unique result
        while ($this->_exists_post_meta_token($token)) {

            $token = $this->_generate_token(9);

        }

        update_post_meta($post_id, 'flow_rand_tok', $token);

    }

    /**
     * Check if user meta token already exists (for any user)
     */
    public function _exists_user_meta_token($tok)
    {

        global $wpdb;

        $query = $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->usermeta WHERE meta_key = '%s' AND meta_value = '%s'", 'flow_rand_tok', $tok);
        $result = $wpdb->get_var($query);

        if ($result > 0) {
            return true;
        }

        return false;

    }

    /**
     * Check if post meta token already exists (for any post)
     */
    public function _exists_post_meta_token($tok)
    {

        global $wpdb;

        $query = $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '%s' AND meta_value = '%s'", 'flow_rand_tok', $tok);
        $result = $wpdb->get_var($query);

        if ($result > 0) {
            return true;
        }

        return false;

    }

    /**
     * Adapted base64_encode suitable for URLs
     */
    public function _base64url_encode($data)
    {

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

    }

    /**
     * Adapted base64_decode suitable for URLs
     */
    public function _base64url_decode($data)
    {

        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));

    }

    /**
     * Get a random token (uses the random_compat lib)
     */
    public function _generate_token($len)
    {

        return $this->_base64url_encode(random_bytes($len));

    }

    /**
     * Get user ID from token
     */
    public function get_user_id_from_token($tok)
    {

        global $wpdb;

        $query = $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '%s' AND meta_value = '%s'", 'flow_rand_tok', $tok);
        $result = $wpdb->get_var($query);

        if ($result === null) {
            return false;
        }

        return $result;

    }

    /**
     * Get post ID from token
     */
    public function get_post_id_from_token($tok)
    {

        global $wpdb;

        $query = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '%s' AND meta_value = '%s'", 'flow_rand_tok', $tok);
        $result = $wpdb->get_var($query);

        if ($result === null) {
            return false;
        }

        return $result;

    }

    /**
     * Get token from user ID
     */
    public function get_token_from_user_id($user_id) {

        global $wpdb;

        $query  = $wpdb->prepare("SELECT meta_value FROM $wpdb->usermeta WHERE user_id = '%d' AND meta_key = 'flow_rand_tok'", $user_id);
        $result = $wpdb->get_var($query);

        if ($result === null) {
            return false;
        }

        return $result;

    }

}

/**
 * Gets the instance of the `FlowTCPDF` class.
 *
 * @access public
 * @return object
 */
function flow_rand_tokens()
{
    return FlowRandTokens::get_instance();
}

// Let's roll!
flow_rand_tokens();