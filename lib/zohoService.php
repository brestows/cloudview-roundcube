<?php
/**
 * @version $Id$
 * upload a document to Zoho's web service 
 * @author Rene Kanzler <rk (at) cosmomill (dot) de>
 */
 
class zohoService {
	var $sDocument;
	var $sFileName;
	var $sMimeType;
	var $sOpenMode;

	/**
	 * send the document to Zoho's web service
	 * @param string $sDocument the document
	 * @param string $sFileName the filename
	 * @param string $sMimeType the document MIME type
	 * @param string $sOpenMode view or edit the document
	 * @return string a JSON string which contains the URL to view or edit the document in Zoho Office
	 */
 
	function sendDocument($sDocument, $sFileName, $sMimeType, $sOpenMode) {
		
		$sFileSuffix = pathinfo($sFileName, PATHINFO_EXTENSION);
		
		$sTmpFile = INSTALL_PATH . 'temp' . "/" . uniqid('cloudviewTmp_') . "." . $sFileSuffix;
		file_put_contents($sTmpFile, $sDocument);
		
		// check open mode parameter ##
		if (!$sOpenMode == 'view' || !$sOpenMode == 'edit') {
			appendLogEntry::addLogEntry( "no valid open mode given - set to view", "zohoService" );
			$sOpenMode = 'view';
		}
		
		// Zoho Writer, Sheet, Show API url ##
		if (mimeHelper::isMimeTypeText($sMimeType)) {
			$sZohoUrl = "https://export.writer.zoho.com/remotedoc.im";
		} elseif (mimeHelper::isMimeTypeSpreadsheet($sMimeType)) {
			$sZohoUrl = "https://sheet.zoho.com/remotedoc.im";
		} elseif (mimeHelper::isMimeTypePresentation($sMimeType)) {
			$sZohoUrl = "https://show.zoho.com/remotedoc.im";
		} else {
			appendLogEntry::addLogEntry( "Document type not supported", "zohoService" );
			return '{"response":{"errorCode":"unsupporteddoctype"}}';
		}
		
		// parameters for Zoho Writer, Sheet, Show ##
		$oRCmail = rcmail::get_instance(); // initialize the rcmail class ##
		$this->load_config(); // load configuration ##
		
		$sUniqueId = $oRCmail->config->get('cloudview_access_key');
		$sSaveUrl = $oRCmail->config->get('cloudview_zoho_save_url');
		$sEditMode = 'normaledit';
		
		// check if unique id ist set ##
		if (!$sUniqueId) {
			$sUniqueId = md5(uniqid(rand(), true)); // generate a random unique id ##
		}
		
		// check if save url is set ## 
		if (!$sSaveUrl) {
			$aPostdata = array('apikey' => zohoAPIkey, 'content' => "@" . $sTmpFile, 'filename' => $sFileName, 'format' => $sFileSuffix, 'output' => 'url', 'mode' => $sEditMode, 'id' => $sUniqueId);
		} else {
			$aPostdata = array('apikey' => zohoAPIkey, 'content' => "@" . $sTmpFile, 'filename' => $sFileName, 'format' => $sFileSuffix, 'output' => 'url', 'mode' => $sEditMode, 'id' => $sUniqueId, 'saveurl' => $sSaveUrl);
		}
			
		
		
		// POST request with curl ##
		appendLogEntry::addLogEntry( "Start POST request with curl", "zohoService" );
		
		$ch = curl_init($sZohoUrl);
		$timeout = 15;
		#curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $aPostdata);
		$result = curl_exec($ch);
		curl_close ($ch);
		
		appendLogEntry::addLogEntry( "Finished POST request: " . $result, "zohoService" );
		
		// delete temporary file ##
		@unlink($sTmpFile);
		
		// handle the result ##
		// check if we got a json response ##
		/**
		 * Use the pear json library to validate the Zoho API response
		 * because the core PHP json_decode function return the given 
		 * string if a non JSON input string was given. Strange behavior!
		 */
		$pearJson = new Services_JSON();
		if ($pearJson->decode($result) != NULL) {
			appendLogEntry::addLogEntry( "We got a json response", "zohoService" );

			// construct a Zoho Viewer API compatible json string ##
			$aResult = json_decode($result, true);
			$aJsonResponse[response] = $aResult;

			// return a json string ## 
			return json_encode($aJsonResponse);
		}
		
		// construct a Zoho Viewer API compatible json string ##
		$result = str_replace(str_split(" \t\n\r\0\x0B"), ' ', $result); // remove line breakes ##
		$result = strtolower(trim($result));
		$aResult = explode(' ', $result);
		$aJsonArray = array();
		$aTempArray = array();
		foreach ($aResult as $key => $value) {
			parse_str($value, $aTempArray);
			$newKey = array_keys($aTempArray);
			$newValue = array_values($aTempArray);
			$aJsonArray[$newKey[0]] = $newValue[0];
		}
		
		if ($aJsonArray[result] == 'true') {
			$aJsonArray[result] = 'Success';
		}
		
		$aJsonResponse[response] = $aJsonArray;
		// return a json string ##
		appendLogEntry::addLogEntry( json_encode($aJsonResponse), "zohoService" );
		return json_encode($aJsonResponse);
	}
}
?>