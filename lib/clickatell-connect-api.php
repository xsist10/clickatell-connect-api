<?php

/**
 * ClickatellConnectApi is an API by Clickatell (www.clickatell.com) for
 * creating accounts, purchasing credits and sending SMSs. This should be used
 * if you wish to integrate SMS capabilities in an application where the user
 * bears the SMS cost.
 *
 * @package clickatell-connect-api
 * @author  Thomas Shone <xsist10@gmail.com>
 */

/*class ClickatellConnectApiPacket
{
    private $sXml    = '';

    public function reset()
    {
        $this->aPacket = array();
        $this->sXml    = '';
    }

    public function set_xml($aPacket)
    {
        $this->sXml = $aPacket;
    }

    public function set_action($sAction)
    {
        $this->aPacket['Action'] = $sAction;
    }

    public function to_xml()
    {

    }
}*/

class ClickatellPacket
{
    private $oXml;

    function __construct()
    {
        $this->oXml = new SimpleXMLElement("<clickatellsdk></clickatellsdk>");
    }

    private function add_child($oXml, $oObject)
    {
        foreach ($oObject as $sName => $mValue)
        {
            if (is_string($mValue) || is_numeric($mValue))
            {
                $oXml->$sName = $mValue;
            }
            else
            {
                $oXml->$sName = null;
                $this->iteratechildren($oXml->$sName, $mValue);
            }
        }
    }

    function to_xml($oObject)
    {
        $this->add_child($this->oXml, $oObject);
        return $this->oXml->asXML();
    }
}

class ClickatellConnectApi
{
    const CLASS_EXCEPTION   = 'ClickatellConncetApiException';
    const API_ENDPOINT      = 'https://connect.clickatell.com/';

    const USER_AGENT        = 'ClickatellConnectApi';
    const VERSION           = '0.1';

    private $sToken = '';

    private $oPacket = null;

    //==========================================================================
    // Magic Functions

    public function __construct($sToken = '')
    {
        $this->set_token($sToken);
    }

    //==========================================================================
    // Private Functions
    private function _checkToken()
    {
        if (empty($this->sToken))
        {
            throw new ClickatellConncetApiException('No token specified');
        }
    }

    private function _reset()
    {
        $this->oPacket = new stdClass();
    }


    private function _setup()
    {
        $this->_checkToken();
        $this->_reset();
    }

    private function _send()
    {
        $this->_checkToken();

        // Build the request packet
        $oPacket = new ClickatellPacket();
        $sXml = $oPacket->to_xml($this->oPacket);

        // Send request
        $sUrl = self::API_ENDPOINT . $this->sToken;
        $oCurl = curl_init();
        curl_setopt($oCurl, CURLOPT_URL,            $sUrl);
        curl_setopt($oCurl, CURLOPT_USERAGENT,      self::USER_AGENT . ' v' . self::VERSION);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_FRESH_CONNECT,  false);
        curl_setopt($oCurl, CURLOPT_PORT,           443);
        curl_setopt($oCurl, CURLOPT_POST,           true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS,     'XML=' . $sXml);

        $sBody = curl_exec($oCurl);
        curl_close($oCurl);

        // Process the result
        $oResponse = new SimpleXMLElement($sBody);
        // Convert into a normal stdClass
        $oResponse = json_decode(json_encode($oResponse), true);

        // Check for error messages
        if ($oResponse['Result'] == 'Error')
        {
            $sErrorMessage = '';

            !empty($oResponse['Error'])
                && $sErrorMessage = $oResponse['Error'];

            !empty($oResponse['Description'])
                && $sErrorMessage .= ': ' . $oResponse['Description'];

            empty($sErrorMessage)
                && $sErrorMessage = 'Unknown Error';

            throw new ClickatellConncetApiException('Request Failed. ' . $sErrorMessage);
        }

        return $oResponse['Values']['Value'];
    }

    //==========================================================================
    // Public Functions

    public function set_token($sToken)
    {
        $this->sToken = $sToken;
    }

    //--------------------------------------------------------------------------
    // API Calls

    /**
     * This service call returns all country names and internal identification
     * numbers
     */
    public function get_list_country()
    {
        $this->_setup();

        $this->oPacket->Action = 'get_list_country';
        return $this->_send();
    }

    /**
     * This service call returns all country dial prefixes and internal
     * identification numbers.
     */
    public function get_list_country_prefix()
    {
        $this->_setup();

        $this->oPacket->Action = 'get_list_country_prefix';
        return $this->_send();
    }

    /**
     * This service call returns a list of valid Clickatell account types (e.g.
     * International or USA Local) and their respective ID.
     */
    public function get_list_account()
    {
        $this->_setup();

        $this->oPacket->Action = 'get_list_account';
        return $this->_send();
    }

    /**
     * This service call returns country specific terms and conditions based on
     * the country selection. If there are no country specific terms and
     * conditions the system returns the main version.
     */
    public function get_list_terms($ip_address)
    {
        $this->_setup();

        $this->oPacket->Action = 'get_list_terms';
        $this->oPacket->client_ip_address = $ip_address;
        $aResult = $this->_send();
        if (!empty($aResult['URL_location']))
        {
            return file_get_contents($aResult['URL_location']);
        }
        return $aResult;
    }

    /**
     * This service call allows the application or website to register new
     * Clickatell Central accounts which includes all the standard requirements.
     */
    public function register()
    {
        $this->_setup();
    }

    /**
     * This service call allows the application or website to resend the
     * activation email containing the activation URL. As an option, the command
     * allows for the modification of the activation email address.
     */
    public function resend_email_activation()
    {
        $this->_setup();
    }

    /**
     * This service call allows the application or website to validate a user
     * login.
     */
    public function authenticate_user()
    {
        $this->_setup();
    }

    /**
     * This service call allows the application or website to request Clickatell
     * to generate a new password using the existing ‘forgot password’
     * functionality.
     */
    public function forgot_password()
    {
        $this->_setup();
    }

    /**
     * This service call returns a random captcha image in PNG format. The
     * returned image must be URL decoded and rendered before it can be
     * displayed.
     */
    public function get_captcha()
    {
        $this->_setup();
    }

    /**
     * The send_activation_status service can be called to check if a user’s
     * account has been SMS activated.
     */
    public function send_activation_status()
    {
        $this->_setup();
    }

    /**
     * This service call allows the application or website to send the SMS
     * activation code to the mobile number stored with the Clickatell account.
     */
    public function send_activation_sms()
    {
        $this->_setup();
    }

    /**
     * This service call allows the application or website to SMS activate a
     * Clickatell account.
     */
    public function validate_activation_sms()
    {
        $this->_setup();
    }

    /**
     * This service call returns a list of valid MT callback types (methods)
     * (e.g. HTTP GET, HTTP POST) and their respective ID’s.
     */
    public function get_list_callback()
    {
        $this->_setup();
    }

    /**
     * This service call returns a list of valid Clickatell connection types
     * (e.g. HTTP API, SMTP API) and their respective ID’s
     */
    public function get_list_connection()
    {
        $this->_setup();
    }

    /**
     * The create_connection service call allows a user to add new messaging API
     * connections to their accounts through your application or website.
     */
    public function create_connection()
    {
        $this->_setup();
    }

    /**
     * The buy_credits_url service call creates a hyperlink that will go to the
     * user’s Clickatell account and allow them to buy credits.
     */
    public function buy_credits_url()
    {
        $this->_setup();
    }


}

class ClickatellConncetApiException extends Exception {}