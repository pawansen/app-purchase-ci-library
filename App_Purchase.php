<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * App_Purchase class
 * APP Purchase iTune And Play Store Subscription
 *
 * @developer    Pawan Sen, Software Developer
 * @license   Free
 */

class App_Purchase {

    private $_CI;
    private $_PUBLIC_KEY;
    private $_iTUNE_URL;
    private $_iTUNE_PASSWORD;

    public function __construct() {

        $this->_CI = &get_instance();
        $this->_CI->load->config('app_purchase_config');
        $this->_PUBLIC_KEY = $this->_CI->config->item('PUBLIC_KEY');
        $this->_iTUNE_URL = $this->_CI->config->item('iTUNE_URL');
        $this->_iTUNE_PASSWORD = $this->_CI->config->item('iTUNE_PASSWORD');
    }
    
    /**
     * @method verify
     * 
     * call method to both app store check
     * 
     * @param  app = ios or app = android
     * @param  params = array('receipt'=>,'signature'=>'')
     * @access public
     * @return boolean TRUE or FALSE 
     * 
     * if user subscription active boolean TRUE otherwise FALSE
     */
    
    public function verify($app = NULL,$params = array()) {
        
        $device = array('ios','android');
        if(array_search($app, $device)):
            throw new Exception("App required device type ios or android, $php_errormsg");
        endif;
        
        if ($app == 'ios') {
            if(!isset($params['receipt']) && empty($params['receipt'])):
                throw new Exception("App iTune receipt required, $php_errormsg");
            endif;
            $postData = json_encode
                    (
                    array(
                        'receipt-data' => $params['receipt'],
                        'password' => $this->_iTUNE_PASSWORD,
                    )
            );
            return $this->iTuneApp($postData);
        }
        if ($app == 'android') {
            if(!isset($params['receipt']) && empty($params['receipt'])):
                throw new Exception("App Play Store receipt required, $php_errormsg");
            endif;
            if(!isset($params['signature']) && empty($params['signature'])):
                throw new Exception("App Play Store signature required, $php_errormsg");
            endif;
            $receipt = $params['receipt'];
            $signature = $params['signature'];
            return $this->PlayStoreApp($receipt, $signature);
        }
        return 0;
    }

    /**
     * @method iTuneApp
     * 
     * itune Store App subscription verify check
     * 
     * @access protected
     * @return boolean TRUE or FALSE 
     * 
     * if user subscription active boolean TRUE otherwise FALSE
     */
    
    protected function iTuneApp($data) {
        $params = array('http' => array(
                'method' => 'POST',
                'content' => $data
        ));
        if ($optional_headers !== null) {
            $params['http']['header'] = $optional_headers;
        }
        $ctx = stream_context_create($params);
        $fp = @fopen($this->_iTUNE_URL, 'rb', false, $ctx);
        if (!$fp) {
            throw new Exception("Problem with $this->_iTUNE_URL, $php_errormsg");
        }
        $response = @stream_get_contents($fp);
        if ($response === false) {
            throw new Exception("Problem reading data from $this->_iTUNE_URL, $php_errormsg");
        }
        $response_decode = json_decode($response);

        if ($response_decode->status == 0) {

            $single = end($response_decode->latest_receipt_info);

            $timezone = explode(" ", $single->expires_date);
            $t = $timezone[2];
            date_default_timezone_set($t);
            $mil = $single->expires_date_ms;
            $seconds = $mil / 1000;
            $expire_date = strtotime(date("d-m-Y H:i:s", $seconds));
            $current_date = strtotime(date('d-m-Y H:i:s'));
            // if return true boolean user app subscription active
            if ($expire_date < $current_date) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * @method PlayStoreApp
     * 
     * Google Play Store App subscription verify check
     * 
     * @access protected
     * @return boolean TRUE or FALSE 
     * 
     * if user subscription active boolean TRUE otherwise FALSE
     */
    
    protected function PlayStoreApp($responseData, $signature) {
        $responseData = trim($responseData);
        $signature = trim($signature);
        $response = json_decode($responseData);

        //Create an RSA key compatible with openssl_verify from our Google Play sig
        $key = "-----BEGIN PUBLIC KEY-----\n" .
                chunk_split($this->_PUBLIC_KEY, 64, "\n") .
                '-----END PUBLIC KEY-----';
        $key = openssl_get_publickey($key);

        // Pre-add signature to return array before we decode it
        $retArray = array('signature' => $signature);

        //Signature should be in binary format, but it comes as BASE64.
        $signature = base64_decode($signature);

        //Verify the signature
        return $result = openssl_verify($responseData, $signature, $key, OPENSSL_ALGO_SHA1);

        $status = (1 === $result) ? 0 : 1;
        $retArray["status"] = $status;
        return $retArray;
    }

}
