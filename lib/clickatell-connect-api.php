<?php

/**
 * ClickatellConnectApi is an API by Clickatell (www.clickatell.com) for
 * creating accounts, purchasing credits and sending SMSs. This should be used
 * if you wish to integrate SMS capabilities in an application where the user
 * bears the SMS cost.
 *
 * http://www.shone.co.za
 *
 * @copyright Thomas Shone 2012
 * @license Licensed under the Creative Commons Attribution 3.0 Unported License.
 * @package clickatell-connect-api
 * @author  Thomas Shone <xsist10@gmail.com>
 */

/**
 * This class is used to convert a stdClass object into an XML string for
 * communication with Clickatell's Connect API
 */
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

    const RESULT_SUCCESS    = 'Success';
    const RESULT_FAILED     = 'Error';

    private $sToken = '';
    private $bTest  = false;

    private $oPacket = null;

    private $sCaptchaId = '';

    //==========================================================================
    // Magic Functions

    public function __construct($sToken = '', $bTest = false)
    {
        $this->set_token($sToken);
        $this->set_test($bTest);
    }

    //==========================================================================
    // Private Functions

    /**
     * Ensure that we have a token, otherwise our request will fail
     *
     * @throws ClickatellConncetApiException
     */
    private function _checkToken()
    {
        if (empty($this->sToken))
        {
            throw new ClickatellConncetApiException('No token specified');
        }
    }

    /**
     * Reset our request packet
     */
    private function _reset()
    {
        $this->oPacket = new stdClass();
    }

    /**
     * Get ready for an API call
     *
     * @throws ClickatellConncetApiException
     */
    private function _setup()
    {
        $this->_checkToken();
        $this->_reset();
    }

    /**
     * Loops through an array of fields and makes sure that required fields are
     * set
     *
     * @param array   $aData
     * @param array   $aFields
     * @throws ClickatellConncetApiException
     */
    private function _checkFields($aData, $aFields)
    {
        if (empty($aData) || !is_array($aData))
        {
            throw new ClickatellConncetApiException('Invalid data type passed. Expecting an array');
        }

        $bOk = true;
        $aMissingFields = array();
        foreach ($aFields as $sField)
        {
            if (empty($aData[$sField]))
            {
                $aMissingFields[] = $sField;
                $bOk = false;
            }
        }

        if (!$bOk)
        {
            throw new ClickatellConncetApiException('Missing required field(s): ' . implode(', ', $aMissingFields));
        }
    }

    /**
     * Build Packet from data array and fields
     *
     * @param array $aData
     * @param array $aFields
     */
    private function _buildPacket($aData, $aFields)
    {
        foreach ($aFields as $sField)
        {
            if (!empty($aData[$sField]))
            {
                $this->oPacket->$sField = $aData[$sField];
            }
        }
    }

    /**
     * Send a request off to Clickatell's API using the currenct packet
     *
     * @return array
     * @throws ClickatellConncetApiException
     */
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
        $aResponse = json_decode(json_encode($oResponse), true);

        // Check for error messages
        if ($aResponse['Result'] == self::RESULT_FAILED)
        {
            $sErrorMessage = '';
            if (!empty($aResponse['Error']))
            {
                if (is_array($aResponse['Error']))
                {
                    foreach ($aResponse['Error'] as $iIndex => $iCode)
                    {
                        if ($iCode == '400')
                        {
                            $aErrorMessages[] = ' Please call get_captcha(), view the image and then pass the captcha value in the captcha_code field.';
                        }
                        else
                        {
                            $aErrorMessages[] = $iCode . (!empty($aResponse['Description'][$iIndex]) ? ' - ' . $aResponse['Description'][$iIndex] : '');
                        }
                    }

                    $sErrorMessage = implode(', ', $aErrorMessages);
                }
                else
                {
                    if ($aResponse['Error'] == '400')
                    {
                        $sErrorMessage = ' Please call get_captcha(), view the image and then pass the captcha value in the captcha_code field.';
                    }
                    else
                    {
                        $sErrorMessage = $aResponse['Error'] . (!empty($aResponse['Description']) ? ' - ' . $aResponse['Description'] : '');
                    }
                }
            }

            empty($sErrorMessage)
                && $sErrorMessage = 'Unknown Error';

            throw new ClickatellConncetApiException('Request Failed: ' . $sErrorMessage);
        }

        if (isset($aResponse['Values']['Value']))
        {
            return $aResponse['Values']['Value'];
        }
        else
        {
            return $aResponse;
        }
    }

    //==========================================================================
    // Public Functions

    public function set_token($sToken)
    {
        $this->sToken = $sToken;
    }

    public function set_test($bTest)
    {
        $this->bTest = $bTest;
    }

    //--------------------------------------------------------------------------
    // API Calls

    /**
     * This service call returns all country names and internal identification
     * numbers
     *
     * @return array
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
     *
     * @return array
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
     *
     * @return array
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
     *
     * @param string  $ip_address
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function get_list_terms($ip_address)
    {
        $this->_setup();

        if (!$ip_address || long2ip(ip2long($ip_address)) != $ip_address)
        {
            throw new ClickatellConncetApiException('Invalid IP address specified');
        }

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
     *
     * @param array $aData
     * @return boolean
     * @throws ClickatellConncetApiException
     */
    public function register($aData)
    {
        if (!$this->sCaptchaId)
        {
            throw new ClickatellConncetApiException('No captcha generated. Use the get_captcha() function to generate a captcha.');
        }

        $aRequiredFields = array(
            'user',
            'fname',
            'sname',
            'password',
            'email_address',
            'mobile_number',
            'country_id',
            'captcha_code'
        );
        $this->_checkFields($aData, $aRequiredFields);

        // The Country Field is not a number. Check if it matches a country name
        if (!is_numeric($aData['country_id']))
        {
            $aCountryList = $this->get_list_country();
            foreach ($aCountryList as $aCountry)
            {
                if ($aCountry['name'] == $aData['country_id'])
                {
                    $aData['country_id'] = $aCountry['country_id'];
                }
            }
        }

        // Still no luck?
        if (!is_numeric($aData['country_id']))
        {
            throw new ClickatellConncetApiException('Invalid country specified');
        }

        $this->_setup();

        $aFields = array_merge(array(
            'account_id',
            'company',
            'coupon_code',
            'activation_redirect',
            'weekly_update',
            'email_format',
        ), $aRequiredFields);
        $this->_buildPacket($aData, $aFields);

        $this->oPacket->Action       = 'register';
        $this->oPacket->test_mode    = $this->bTest ? 1 : 0;
        $this->oPacket->accept_terms = 1;
        $this->oPacket->force_create = 1;
        $this->oPacket->captcha_id = $this->sCaptchaId;
        $this->sCaptchaId = '';

        $aResult = $this->_send();
        return $aResult['Result'] == self::RESULT_SUCCESS;
    }

    /**
     * This service call allows the application or website to resend the
     * activation email containing the activation URL. As an option, the command
     * allows for the modification of the activation email address.
     *
     * @param array $aData
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function resend_email_activation($aData = array())
    {
        $this->_setup();

        $aRequiredFields = array('user', 'password', 'email_address');
        $this->_checkFields($aData, $aRequiredFields);
        $this->_buildPacket($aData, $aRequiredFields);

        $this->oPacket->Action = 'resend_email_activation';
        return $this->_send();
    }

    /**
     * This service call allows the application or website to validate a user
     * login.
     */
    public function authenticate_user($aData = array())
    {
        $this->_setup();

        $aRequiredFields = array('user', 'password');
        $this->_checkFields($aData, $aRequiredFields);
        $this->_buildPacket($aData, $aRequiredFields);

        $this->oPacket->Action = 'authenticate_user';
        return $this->_send();
    }

    /**
     * This service call allows the application or website to request Clickatell
     * to generate a new password using the existing ‘forgot password’
     * functionality.
     *
     * @param array $aData
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function forgot_password($aData = array())
    {
        if (!$this->sCaptchaId)
        {
            throw new ClickatellConncetApiException('Require Captcha Code');
        }

        $this->_setup();

        $aRequiredFields = array('user', 'email_address', 'captcha_code');
        $this->_checkFields($aData, $aRequiredFields);
        $this->_buildPacket($aData, $aRequiredFields);

        $this->oPacket->captcha_id = $this->sCaptchaId;
        $this->sCaptchaId = '';

        $this->oPacket->Action = 'forgot_password';
        return $this->_send();
    }

    /**
     * This service call returns a random captcha image in PNG format. The
     * returned image must be URL decoded and rendered before it can be
     * displayed.
     *
     * @return  string  RAW PNG image
     */
    public function get_captcha()
    {
        $this->_setup();

        $this->oPacket->Action = 'get_captcha';
        $aResult = $this->_send();

        $this->sCaptchaId = $aResult['captcha_id'];
        return urldecode($aResult['captcha_image']);
    }

    /**
     * The sms_activation_status service can be called to check if a user’s
     * account has been SMS activated.
     *
     * @param array $aData
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function sms_activation_status($aData = array())
    {
        $this->_setup();

        $aRequiredFields = array('user', 'password');
        $this->_checkFields($aData, $aRequiredFields);
        $this->_buildPacket($aData, $aRequiredFields);

        if ($this->sCaptchaId && !empty($aData['captcha_code']))
        {
            $this->oPacket->captcha_id = $this->sCaptchaId;
            $this->oPacket->captcha_code = $aData['captcha_code'];
            $this->sCaptchaId = '';
        }

        $this->oPacket->Action = 'sms_activation_status';
        try
        {
            $aResult = $this->_send();
            return $aResult['Result'] == self::RESULT_SUCCESS;
        }
        catch (ClickatellConncetApiException $oException)
        {
            if ($oException->getMessage() == "Request Failed: 104 - Not SMS activated")
            {
                return false;
            }
            else
            {
                throw $oException;
            }
        }
    }

    /**
     * This service call allows the application or website to send the SMS
     * activation code to the mobile number stored with the Clickatell account.
     *
     * @param array $aData
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function send_activation_sms($aData = array())
    {
        $this->_setup();

        $aRequiredFields = array('user', 'password');
        $this->_checkFields($aData, $aRequiredFields);
        $this->_buildPacket($aData, $aRequiredFields);

        if ($this->sCaptchaId && !empty($aData['captcha_code']))
        {
            $this->oPacket->captcha_id = $this->sCaptchaId;
            $this->oPacket->captcha_code = $aData['captcha_code'];
            $this->sCaptchaId = '';
        }

        $this->oPacket->Action = 'send_activation_sms';
        return $this->_send();
    }

    /**
     * This service call allows the application or website to SMS activate a
     * Clickatell account.
     *
     * @param array $aData
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function validate_activation_sms($aData = array())
    {
        $this->_setup();

        $aRequiredFields = array('user', 'password', 'sms_activation_code');
        $this->_checkFields($aData, $aRequiredFields);
        $this->_buildPacket($aData, $aRequiredFields);

        if ($this->sCaptchaId && !empty($aData['captcha_code']))
        {
            $this->oPacket->captcha_id = $this->sCaptchaId;
            $this->oPacket->captcha_code = $aData['captcha_code'];
            $this->sCaptchaId = '';
        }

        $this->oPacket->Action = 'validate_activation_sms';
        return $this->_send();
    }

    /**
     * This service call returns a list of valid MT callback types (methods)
     * (e.g. HTTP GET, HTTP POST) and their respective ID’s.
     *
     * @param array $aData
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function get_list_callback()
    {
        $this->_setup();

        $this->oPacket->Action = 'get_list_callback';
        return $this->_send();
    }

    /**
     * This service call returns a list of valid Clickatell connection types
     * (e.g. HTTP API, SMTP API) and their respective ID’s
     *
     * @param array $aData
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function get_list_connection()
    {
        $this->_setup();

        $this->oPacket->Action = 'get_list_connection';
        return $this->_send();
    }

    /**
     * The create_connection service call allows a user to add new messaging API
     * connections to their accounts through your application or website.
     *
     * @param array $aData
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function create_connection($aData = array())
    {
        $this->_setup();

        $aRequiredFields = array('user', 'password');
        $this->_checkFields($aData, $aRequiredFields);

        $aFields = array_merge(array(
            'connection_id',
            'ftp_password',
            'api_description',
            'ip_address',
            'Dial_prefix',
            'callback_url',
            'callback_type_id',
            'callback_username',
            'callback_password',
        ), $aRequiredFields);
        $this->_buildPacket($aData, $aFields);

        if ($this->sCaptchaId && !empty($aData['captcha_code']))
        {
            $this->oPacket->captcha_id = $this->sCaptchaId;
            $this->oPacket->captcha_code = $aData['captcha_code'];
            $this->sCaptchaId = '';
        }

        $this->oPacket->Action = 'create_connection';
        return $this->_send();
    }

    /**
     * The buy_credits_url service call creates a hyperlink that will go to the
     * user’s Clickatell account and allow them to buy credits.
     *
     * @param array $aData
     * @return array
     * @throws ClickatellConncetApiException
     */
    public function buy_credits_url($aData = array())
    {
        $this->_setup();

        $aRequiredFields = array('user', 'password');
        $this->_checkFields($aData, $aRequiredFields);
        $this->_buildPacket($aData, $aRequiredFields);

        if ($this->sCaptchaId && !empty($aData['captcha_code']))
        {
            $this->oPacket->captcha_id = $this->sCaptchaId;
            $this->oPacket->captcha_code = $aData['captcha_code'];
            $this->sCaptchaId = '';
        }

        $this->oPacket->Action = 'buy_credits_url';
        return $this->_send();
    }


}

class ClickatellConncetApiException extends Exception {}
