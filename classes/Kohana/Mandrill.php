<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * @author DevSide based on Wes Widner's work
 * @year 2014
 */

class Kohana_Mandrill
{

    // Mandrill instances
    protected static $_instance;
    private static $required_key = array('key');

    /**
     * Singleton pattern
     *
     * @return Mandrill
     */
    public static function instance()
    {
        if (!isset(Mandrill::$_instance)) {
            // Load the configuration for this type
            $config = Kohana::$config->load('mandrill');

            // Create a new session instance
            Mandrill::$_instance = new Mandrill($config);
        }

        return Mandrill::$_instance;
    }


    private $_config;
    private $_api_calls;

    /**
     * Loads Mandrill and configuration options.
     *
     * @param   array $config Config Options
     * @throws Kohana_Kohana_Exception
     */
    public function __construct($config = array())
    {
        // Save the config in the object
        $this->_config = $config;

        $this->_api_calls = array(
            /* Users Calls */
            'users' => array(
                'info' => self::$required_key,
                'ping' => self::$required_key,
                'senders' => self::$required_key,
                'disable-sender' => array_merge_recursive(self::$required_key, array('domain')),
                'verify-sender' => array_merge_recursive(self::$required_key, array('email'))
            ),

            /* Messages Calls */
            'messages' => array(
                'send' => array_merge_recursive(self::$required_key, array('message'), array('async')),
                'send-template' => array_merge_recursive(self::$required_key, array('template_name', 'template_content', 'message')),
                'search' => array_merge_recursive(self::$required_key, array('query', 'date_from', 'date_to', 'tags', 'senders', 'limit'))
            ),

            /* Tags Calls */
            'tags' => array(
                'list' => self::$required_key,
                'info' => array_merge_recursive(self::$required_key, array('tag')),
                'time-series' => array_merge_recursive(self::$required_key, array('tag')),
                'all-time-series' => self::$required_key
            ),

            /* Senders Calls */
            'senders' => array(
                'list' => self::$required_key,
                'info' => array_merge_recursive(self::$required_key, array('address')),
                'time-series' => array_merge_recursive(self::$required_key, array('address'))
            ),

            /* Urls Calls */
            'urls' => array(
                'list' => self::$required_key,
                'search' => array_merge_recursive(self::$required_key, array('q')),
                'time-series' => array_merge_recursive(self::$required_key, array('url'))
            ),

            /* Templates Calls */
            'templates' => array(
                'add' => array_merge_recursive(self::$required_key, array('name', 'code')),
                'info' => array_merge_recursive(self::$required_key, array('name')),
                'update' => array_merge_recursive(self::$required_key, array('name', 'code')),
                'delete' => array_merge_recursive(self::$required_key, array('name')),
                'list' => self::$required_key
            ),

            /* Webhooks Calls */
            'webhooks' => array(
                'list' => self::$required_key,
                'add' => array_merge_recursive(self::$required_key, array('url', 'events')),
                'info' => array_merge_recursive(self::$required_key, array('id')),
                'update' => array_merge_recursive(self::$required_key, array('id', 'url', 'events')),
                'delete' => array_merge_recursive(self::$required_key, array('id'))
            )
        );

        if (!isset($this->_config['api_key']))
            throw new Kohana_Kohana_Exception("Mandrill need an api_key.");
    }


    /**
     * Validates the user's parameters against known valid Mandrill API calls
     * @param string $call_type The type of Mandrill call to make, ex. 'users' or 'tags'
     * @param string $call The call to make, ex. 'ping' or 'info'
     * @param mixed $data An associative array of options that correspond with the Mandrill API call being made
     * @throws Kohana_Exception
     * @return bool True or false for successful validation
     */
    private function _validate_call(&$call_type, &$call, &$data)
    {

        if (!array_key_exists($call_type, $this->_api_calls))
            throw new Kohana_Exception('Invalid call type.');

        if (!array_key_exists($call, $this->_api_calls[$call_type]))
            throw new Kohana_Exception("Invalid call for call type $call_type");

        $diff_keys = array_diff(array_keys($data), $this->_api_calls[$call_type][$call]);

        if (count($diff_keys) > 0)
            throw new Kohana_Exception('Invalid keys in call: ' . implode(',', $diff_keys));

        //TODO: actually validate the fields

        return true;
    }


    /**
     * The main method which makes the curl request to the Mandrill API
     * @param mixed $data An associative array of options that correspond with the Mandrill API call being made
     * @throws Kohana_Exception
     * @return mixed The response from the server.
     */
    public function call($data)
    {

        if (!is_array($data))
            throw new Kohana_Exception('Must pass one associative array with proper values set.');

        if (!array_key_exists('type', $data))
            throw new Kohana_Exception('API call type must be set.');

        if (!array_key_exists('call', $data))
            throw new Kohana_Exception('API call must be set.');


        $call_type = $data['type'];
        $call = $data['call'];

        unset($data['type']);
        unset($data['call']);

        if (!$this->_validate_call($call_type, $call, $data))
            throw new Kohana_Exception('Error validation call');

        $data['key'] = $this->_config['api_key'];

        $data_string = json_encode($data);

        $parsed_url = sprintf($this->_config['api_url'], $call_type, $call);

        $ch = curl_init($parsed_url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mandrill-PHP/1.0.36');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        if(curl_error($ch)) {
            throw new Kohana_Exception("API call to $parsed_url failed: " . curl_error($ch));
        }

        $resultJSON = json_decode($result, true);

        if($resultJSON === null)
            throw new Kohana_Exception('We were unable to decode the JSON response from the Mandrill API: ' . $result);

        if(floor($info['http_code'] / 100) >= 4) {
            throw new Kohana_Exception('Status code '.$info['http_code']);
        }

        return $resultJSON;
    }

    /**
     * Return true if Mandrill's signature is verified
     * http://help.mandrill.com/entries/23704122-Authenticating-webhook-requests
     * @return bool True if Mandrill signature verified
     */
    public function checkWebhookSignature()
    {
        $post_data = $_POST;
        $signed_data = $this->_config['webhooks_url'];
        ksort($post_data);
        foreach ($post_data as $key => $value) {
            $signed_data .= $key;
            $signed_data .= $value;
        }
        return (base64_encode(hash_hmac('sha1', $signed_data, $this->_config['webhooks_key'], true)) === $_SERVER['HTTP_X_MANDRILL_SIGNATURE']);
    }

}