<?php


/*
 * @wordpress-plugin
 * Plugin Name: Ride Leader Plugin
 * Description: Interface with CSO Roster API for Ride Leaders
 * Version: 1.0 Alpha
 * Author: Mark Pemburn
 * Author URI: http://www.pemburnia.com/
*/

class RideLeader
{

    protected $apiUrl;
    protected $memberList;
    protected $devMode = true;
    protected $devApiUrl = 'https://cso_roster.test/api';

    public static function register()
    {
        $instance = new self;
        $instance->loadSettings();
        $instance->loadListing();
        $instance->enqueueAssets();

        add_action( 'init', array( $instance, 'registerShortcodes' ) );
        // Set up AJAX handlers
        add_action('wp_ajax_ride_leader_verify', [$instance, 'verifyMemberInRoster']);
        add_action('wp_ajax_ride_leader_add_guest', [$instance, 'addGuest']);
        add_action('wp_ajax_nopriv_ride_leader_verify', [$instance, 'verifyMemberInRoster']);
        add_action('wp_ajax_nopriv_ride_leader_add_guest', [$instance, 'addGuest']);
    }

    private function __construct()
    {
    }

    /**
     *
     */
    public function enqueueAssets()
    {
        $version = '1.09';
        wp_enqueue_style( 'jquery-ui'. 'http://code.jquery.com/ui/1.9.1/themes/base/jquery-ui.css' );
        wp_enqueue_style('bootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css');
        wp_enqueue_style('typeahead', plugin_dir_url(__FILE__) . 'css/jquery.typeahead.css', '', $version);
        wp_enqueue_style('ride_leader', plugin_dir_url(__FILE__) . 'css/ride_leader.css', '', $version);

        wp_enqueue_script('bootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js');
        wp_register_script('typeahead', plugin_dir_url(__FILE__) . 'js/jquery.typeahead.js', '', $version, true);
        wp_register_script('ride_leader', plugin_dir_url(__FILE__) . 'js/ride_leader.js', '', $version, true);
        wp_enqueue_script('ride_leader');
        wp_enqueue_script('typeahead');

        wp_register_script('ajax-js', null);
        wp_localize_script('ajax-js', 'leaderNamespace', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'memberList' => json_decode($this->memberList)
        ]);
        wp_enqueue_script('ajax-js');
    }

    public function leaderFormHandler( $att, $content ) {
        ob_start();

        include 'leader_form.php';

        $output = ob_get_clean();

        return $output;
    }

    public function loadListing()
    {
        $url = $this->apiUrl . '/member/list';
        $response = $this->makeApiCall('GET', $url);

        $this->memberList = $response['body'];
    }

    public function registerShortcodes() {
        add_shortcode( 'ride-leader', array( $this, 'leaderFormHandler' ));
    }

    public function addGuest()
    {
        $data = $_POST['data'];
        parse_str($data, $parsed);

        $url = $this->apiUrl . '/guest/add';

        $response = $this->makeApiCall('POST', $url, $parsed);
        $success = $this->getResponseSuccess($response);

        wp_send_json([
            'success' => $success,
            'action' => 'update',
            'data' => $response
        ]);

        die();
    }

    protected function getResponseSuccess($response)
    {
        $success = false;

        $is200 = (isset($response['response'])) ? ($response['response']['code'] == 200) : false;
        if ($is200) {
            $success = (isset($response['body'])) ? json_decode($response['body'])->success : false;
        }

        return $success;
    }

    protected function loadSettings()
    {
        $option = get_option('roster_option_name');

        $settings = (!empty($option)) ? (object) $option : null;

        if (!is_null($settings)) {
            $this->apiUrl = (!$this->devMode) ? $settings->api_uri : $this->devApiUrl;
        }

    }

    protected function makeApiCall($action, $url, $data = [])
    {
        $response = null;

        // TODO: Future security enhancement
        $username = 'your-username';
        $password = 'your-password';
        $headers = array( 'Authorization' => 'Basic ' . base64_encode( "$username:$password" ) );
        if ($action == 'GET') {
            $response = wp_remote_get( $url, [
                'headers' => $headers,
                'sslverify' => false
            ] );
        }
        if ($action == 'POST') {
            $response = wp_remote_post( $url, [
                'headers' => $headers,
                'body' => $data,
                'sslverify' => false,
                'timeout' => 45,
            ] );
        }

        return $response;
    }

}
// Load as singleton to add actions and enqueue assets
RideLeader::register();
