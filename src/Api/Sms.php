<?php
namespace Gabia\Api;

use Gabia\Helper\XmlRpcCommon;

class Sms extends XmlRpcCommon
{
    public function __construct($id, $api_key, $pw="")
	{
        parent::__construct($id, $api_key, $pw);
	}

	public function Send($phone, $callback, $msg, $title = "", $refkey="", $sendType = "sms", $reserve = "0") {
		$msg = $this->escape_xml_str($msg);

		if(is_array($phone) == true) {
			$phone = $this->filter_phone_number($phone);
			$method = "SMS.multi_send";
		} else {
			$method = "SMS.send";
		}

		$request_xml = <<<DOC_XML
	<request>
	<sms-id>{$this->smsId}</sms-id>
	<access-token>{$this->md5_access_token}</access-token>
	<response-format>xml</response-format>
	<method>$method</method>
	<params>
		<send_type>$sendType</send_type>
		<ref_key>{$refkey}</ref_key>
		<subject>{$title}</subject>
		<message>{$msg}</message>
		<callback>{$callback}</callback>
		<phone>{$phone}</phone>
		<reserve>{$reserve}</reserve>
	</params>
	</request>
DOC_XML;
		
		return $this->Request($request_xml);
	}

	public function Send_MMS($phone, $callback, $file_path, $msg, $title="", $refkey="", $reserve = "0")
	{
		$msg = $this->escape_xml_str($msg);
		
		$params = "";
		
		$file_cnt = count($file_path);

		if(is_array($phone) == true) {
			$phone = $this->filter_phone_number($phone);
			$method = "SMS.multi_send";
		} else {
			$method = "SMS.send";
		}

		foreach($file_path as $i => $file) {
			if(filesize($file) > 312600 || filesize($file) == 0){
				$this->RESULT_OK = "";
				$this->m_szResultCode = "E015";
				$this->m_szResult = "FILE SIZE OVER";
				return false;
			}

			$fp = fopen($file, "r");
			$fr = fread($fp, filesize($file));

			fclose($fp);

			$params .= "<file_bin_". $i ." xmlns:dt='urn:schemas-microsoft-com:datatypes' dt:dt='bin.base64'>". base64_encode($fr) ."</file_bin_". $i .">";
		}

		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->smsId}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>$method</method>
<params>
		<send_type>mms</send_type>
		<ref_key>{$refkey}</ref_key>
		<subject>{$title}</subject>
		<message>{$msg}</message>
		<callback>{$callback}</callback>
		<phone>{$phone}</phone>
		<reserve>{$reserve}</reserve>
		<file_cnt>{$file_cnt}</file_cnt>
		{$params}
</params>
</request>
DOC_XML;

		return $this->Request($request_xml);
	}

	public function getSendStatusByRefKey($refkey)
	{
		if(is_array($refkey))
		{
			$ref_keys = implode(",", $refkey);
		}
		else
		{
			$ref_keys = $refkey;
		}

		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->smsId}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>SMS.getStatusByRef</method>
<params>
	<ref_key>{$ref_keys}</ref_key>
</params>
</request>
DOC_XML;

		if ($this->Request($request_xml) == self::$RESULT_OK)
		{
			$r = array();
			$resultXML = simplexml_load_string($this->m_szResult);

			foreach($resultXML->children()->smsResult->entries->children() as $n)
			{
				if((string)$n[0] =="NODATA") {
					return array("CODE" => "NODATA", "MESG" =>"NODATA");
				}
				$szKey = (string)$n->children()->SMS_REFKEY;
				$szCode = (string)$n->children()->CODE;
				$szMesg = (string)$n->children()->MESG;

				$r = array("CODE" => $szCode, "MESG" => $szMesg);
			}

			return $r;
		}
		else 
		{
			return false;
		}
	}

	public function getCallbackNum() {
		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->smsId}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>SMS.getCallbackNum</method>
</request>
DOC_XML;

		if ($this->Request($request_xml) == self::$RESULT_OK)
		{
			$r = array();

			$resultXML = simplexml_load_string($this->m_szResult);
			
			foreach($resultXML->children()->smsResult->entries->children() as $n)
			{
				$callbackNum = (string)$n->callback;
				
				if(is_null($callbackNum) || empty($callbackNum)) {
					continue;
				}
				
				$r[] = $callbackNum;
			}

			return $r;
		} else {
			return false;
		}		
	}

	/*public function getSmsCount()
	{
		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->smsId}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>SMS.getUserInfo</method>
<params>
</params>
</request>
DOC_XML;

		$nCount = 0;
		if ($this->xml_do($request_xml) == self::$RESULT_OK)
		{
			if (stripos($this->m_szResult, "<?xml") == 0)
			{
				$oCountXML = simplexml_load_string($this->m_szResult);

				if (isset($oCountXML->children()->sms_quantity))
					$nCount = $oCountXML->children()->sms_quantity;
			}
		}

		return $nCount;
	}

	public function get_status_by_ref_all($ref_key){
		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->smsId}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>SMS.getStatusByRef_all</method>
<params>
	<ref_key>{$ref_key}</ref_key>
</params>
</request>
DOC_XML;

		if ($this->xml_do($request_xml) == self::$RESULT_OK)
		{
			$r = array();
			$resultXML = simplexml_load_string($this->m_szResult);
			$i = 0;
			foreach($resultXML->children()->smsResult->entries->children() as $n)
			{
				$szKey = (string)$n->children()->SMS_REFKEY;
				$szPhone = (string)$n->children()->PHONENUM;
				$szMesg = (string)$n->children()->MESG;

				$r[$i]["PHONE"] = $szPhone;
				$r[$i]["MESG"] = $szMesg;

				$i++;
			}
			if($r[0]["PHONE"] != null){
				return $r;
			}else{
				return false;
			}
			
		}
		else false;
	}

	public function reservationCancel($refkey, $send_type, $phonenum='')
	{
		$multi_phonenum = '';
		if(is_array($phonenum)){
			$multi_phonenum = $this->make_multi_num($phonenum);	
		}

		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->smsId}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>SMS.reservationCancel</method>
<params>
		<send_type>{$send_type}</send_type>
		<ref_key>{$refkey}</ref_key>
		<phonenum>{$multi_phonenum}</phonenum>
</params>
</request>
DOC_XML;

		if ($this->xml_do($request_xml) == self::$RESULT_OK)
		{
			if (stripos($this->m_szResult, "<?xml") == 0)
			{
				$oCountXML = simplexml_load_string($this->m_szResult);
				if (isset($oCountXML->children()->smsResult->entries->entry))
					$result = (string)$oCountXML->children()->smsResult->entries->entry;

				if($result == "true"){
					return true;
				}else{
					$this->m_szResultCode = 'E999';
					$this->m_szResultMessage = '알수없는 에러';
					return false;
				}
			}
		}else{
			return false;
		}
	}*/
}
?>