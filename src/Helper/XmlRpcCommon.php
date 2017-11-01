<?php
namespace Gabia\Helper;

use PhpXmlRpc;

abstract class XmlRpcCommon 
{
    /** @var RPC Server */
    private $server = "sms.gabia.com";

    /** @var RPC Endpoint */
    private $endpoint = "api";
    
    /** @var Xml RPC Method */
    private $method = "gabiasms";

    /** @var Xml RPC Error */
    private $error = null;

    private $smsId;

    private $apiKey;

    private $smsPw;
    
	private $api_curl_url = "http://sms.gabia.com/assets/api_upload.php";
	private $user_id = "";
	private $user_pw = "";
    
    private $m_szResultXML = "";
	private $m_oResultDom = null;
	private $m_szResultCode = "";
	private $m_szResultMessage = "";
	private $m_szResult = "";

	private $m_nBefore = 0;
	private $m_nAfter = 0;
	private $success_cnt = 0;
	private $fail_list;

	public $md5_access_token = "";

	public static $RESULT_OK = "0000";
    public static $CALL_ERROR = -1;

    public function __construct($smsId, $apiKey, $smsPw)
    {
        $this->smsId = $smsId;
        $this->apiKey = $apiKey;
        $this->smsPw = $smsPw;

        $nonce = $this->GenerateNonce();
		$this->md5_access_token = $nonce . md5($nonce.$this->apiKey);
    }

	public function __destruct()
	{
		unset($this->m_szResultXML);
		unset($this->m_oResultDom);
    }

    public function Request($xmlString)
	{
        $rpcMessage = $this->GenerateXmlRpcMessage($xmlString);
        
        $rpcClient = new PhpXmlRpc\Client($this->endpoint, $this->server, 80);

        $rpcClient->request_charset_encoding = "UTF-8";
		//$c->setDebug(TRUE);

		$resultRpc = $rpcClient->send($rpcMessage);

		if (!$resultRpc->faultCode())
		{
			$resultRpcData = $resultRpc->value();

			//print_r($v);

            $decodeData = $this->Decoding($resultRpcData);

			//print_r($decode_v);

            $this->m_szResultXML = $decodeData;
		}
		else
		{
			$this->error = $rpcClient->errstr;
			$this->m_szResultXML = $rpcClient->errno;
        }
        
        if(is_null($this->error))
        {
            $this->getDomData();
        } 
        else 
        {
            $this->m_szResultCode = $this->m_szResultXML;
			$this->m_szResult = $this->getRpcError();
        }

        return $this->m_szResultCode;
    }

    private function GenerateNonce() {
        $nonce = '';

		for($i=0; $i<8; $i++)
		{
			$nonce .= dechex(rand(0, 15));
		}

		return $nonce;
    }

    private function get_result_xml($result)
	{
		$sp = new Gabia\Helper\SimpleParser();
		$sp->parse_xml($result);

		$result_xml = $sp->getValue("RESPONSE|RESULT");

		return base64_decode($result_xml);
	}

    private function getDomData() 
    {
        $this->m_oResultDom = simplexml_load_string($this->m_szResultXML);
        
        if (isset($this->m_oResultDom->children()->code))
            $this->m_szResultCode = $this->m_oResultDom->children()->code;

        if (isset($this->m_oResultDom->children()->code))
            $this->m_szResultMessage = $this->m_oResultDom->children()->mesg;

        if (isset($this->m_oResultDom->children()->result))
            $this->m_szResult = base64_decode($this->m_oResultDom->children()->result);

        $r = stripos($this->m_szResult, "<?xml");

        if ($r == 0 && $r !== FALSE)
        {
            $oCountXML = simplexml_load_string($this->m_szResult);

            if(isset($oCountXML->children()->BEFORE_SMS_QTY))
                $this->m_nBefore = $oCountXML->children()->BEFORE_SMS_QTY;

            if(isset($oCountXML->children()->AFTER_SMS_QTY))
                $this->m_nAfter = $oCountXML->children()->AFTER_SMS_QTY;

            if(isset($oCountXML->children()->SUCCESS_CNT))
                $this->success_cnt = $oCountXML->children()->SUCCESS_CNT;

            if(isset($oCountXML->children()->FAIL_LIST))
                $this->fail_list = $oCountXML->children()->FAIL_LIST;

            unset($oCountXML);
        }

        unset($this->m_oResultDom);
    }

    private function Decoding($data) {
        $Encoder = new PhpXmlRpc\Encoder();
        
        return $Encoder->decode($data);
    }
    
    private function GenerateXmlRpcMessage($xmlString) {
        $values = array(
            new PhpXmlRpc\Value($xmlString, "string")
        );

        return new PhpXmlRpc\Request($this->method, $values);
    }

	public function getRpcError()
	{
		return $this->error;
    }
    
	public function getResultCode()
	{
		return $this->m_szResultCode;
	}

	public function getResultMessage()
	{
		return $this->m_szResultMessage;
	}

	public function getBefore()
	{
		return $this->m_nBefore;
	}

	public function getAfter()
	{
		return $this->m_nAfter;
	}

	public function get_success_cnt()
	{
		return $this->success_cnt;
	}

	public function get_fail_list()
	{
		return $this->fail_list;
	}

	private function escape_xml_str($message){
		$message = str_replace("&", "&amp;",$message);
		$message = str_replace("<", "&lt;",$message);
		$message = str_replace(">", "&gt;",$message);

		return $message;
    }
    
    private function filter_phone_number($phone) {
        $arr_phone = array_filter($phone, function($value) {
            if(is_null($value) || empty($value)) {
                return false;
            }

            if(preg_match("/^0([1|7]?)([0|1|6|7|8|9]?)([0-9]{3,4})([0-9]{4})$/", $value) === false && preg_match("/^0([1|7]?)([0|1|6|7|8|9]?)-([0-9]{3,4})-([0-9]{4})$/", $value) === false) {
                return false;
            }

            return true;
        });

        return implode(",", $arr_phone);
    }
}
?>