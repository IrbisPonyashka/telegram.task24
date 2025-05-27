<?php

	require_once ('settings.php');
	// require 'vendor/autoload.php';

	// use Jenssegers\Blade\Blade;
	
	/**
	 *  @version 1.36
	 *  define:
	 *      C_REST_WEB_HOOK_URL = 'https://rest-api.bitrix24.com/rest/1/doutwqkjxgc3mgc1/'  //url on creat Webhook
	 *      or
	 *      C_REST_CLIENT_ID = 'local.5c8bb1b0891cf2.87252039' //Application ID
	 *      C_REST_CLIENT_SECRET = 'SakeVG5mbRdcQet45UUrt6q72AMTo7fkwXSO7Y5LYFYNCRsA6f'//Application key
	 *
	 *		C_REST_CURRENT_ENCODING = 'windows-1251'//set current encoding site if encoding unequal UTF-8 to use iconv()
	 *      C_REST_BLOCK_LOG = true //turn off default logs
	 *      C_REST_LOGS_DIR = __DIR__ .'/logs/' //directory path to save the log
	 *      C_REST_LOG_TYPE_DUMP = true //logs save var_export for viewing convenience
	 *      C_REST_IGNORE_SSL = true //turn off validate ssl by curl
	 */

	class CRest
	{
		const VERSION = '1.36';
		const BATCH_COUNT    = 50;//count batch 1 query
		const TYPE_TRANSPORT = 'json';// json or xml

		private static $dbInstance = null;
	
		public static function getDBInstance(): ?mysqli
		{
			if (self::$dbInstance == null) {
				self::$dbInstance = new mysqli(DB_HOST, DB_LOGIN, DB_PASSWORD, DB_NAME);
			}

			return self::$dbInstance;
		}
	
		public static function installApp(): array
		{
			$result = [
				'rest_only' => true,
				'install' => false
			];
			if ((isset($_REQUEST['event']) && $_REQUEST['event'] == 'ONAPPINSTALL') && !empty($_REQUEST['auth']))
			{
				$result['install'] = static::setAppSettings($_REQUEST['auth'], true);
			}
			elseif ($_REQUEST['PLACEMENT'] == 'DEFAULT')
			{
				$result['rest_only'] = false;
				$result['install'] = static::setAppSettings(
					[
						'access_token' => htmlspecialchars($_REQUEST['AUTH_ID']),
						'expires_in' => htmlspecialchars($_REQUEST['AUTH_EXPIRES']),
						'application_token' => htmlspecialchars($_REQUEST['APP_SID']),
						'refresh_token' => htmlspecialchars($_REQUEST['REFRESH_ID'])    ,
						'domain' => htmlspecialchars($_REQUEST['DOMAIN']),
						'client_endpoint' => 'https://' . htmlspecialchars($_REQUEST['DOMAIN']) . '/rest/',
					],
					true
				);
			}
	
			static::setLog(
				[
					'request' => $_REQUEST,
					'result' => $result
				],
				'installApp'
			);
			return $result;
		}
	
		/**
		 * @return mixed array|string|boolean curl-return or error
		 *
		 * @var $arParams array
		 * $arParams = [
		 *      'method'    => 'some rest method',
		 *      'params'    => []//array params of method
		 * ];
		 */
		protected static function callOauthCurl($arParams, $domain = false)
		{
			if (!function_exists('curl_init')) {
				return [
					'error' => 'error_php_lib_curl',
					'error_information' => 'need install curl lib'
				];
			}
	
			if (!$domain && !empty(DOMAIN)) {
				$domain = DOMAIN;
			}
			if ($domain) {
				$arSettings = static::getAppSettings($domain);
			} else {
				$arSettings = static::getAppSettings();
			}
			if ($arSettings !== false) {
				if (isset($arParams['this_auth']) && $arParams['this_auth'] == 'Y') {
					$url = 'https://oauth.bitrix.info/oauth/token/';
				} else {
					$url = $arSettings["client_endpoint"] . $arParams['method'] . '.' . static::TYPE_TRANSPORT;
					if (empty($arSettings['is_web_hook']) || $arSettings['is_web_hook'] != 'Y') {
						$arParams['params']['auth'] = $arSettings['access_token'];
					}
				}
	
				$sPostFields = http_build_query($arParams['params']);
	
				try {
					$obCurl = curl_init();
					curl_setopt($obCurl, CURLOPT_URL, $url);
					curl_setopt($obCurl, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($obCurl, CURLOPT_POSTREDIR, 10);
					curl_setopt($obCurl, CURLOPT_USERAGENT, 'Bitrix24 CRest PHP ' . static::VERSION);
					if ($sPostFields) {
						curl_setopt($obCurl, CURLOPT_POST, true);
						curl_setopt($obCurl, CURLOPT_POSTFIELDS, $sPostFields);
					}
					curl_setopt(
						$obCurl, CURLOPT_FOLLOWLOCATION, (isset($arParams['followlocation']))
						? $arParams['followlocation'] : 1
					);
					if (defined("C_REST_IGNORE_SSL") && C_REST_IGNORE_SSL === true) {
						curl_setopt($obCurl, CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($obCurl, CURLOPT_SSL_VERIFYHOST, false);
					}
					$out = curl_exec($obCurl);
					$info = curl_getinfo($obCurl);
	
					if (curl_errno($obCurl)) {
						$info['curl_error'] = curl_error($obCurl);
					}
					if (static::TYPE_TRANSPORT == 'xml' && (!isset($arParams['this_auth']) || $arParams['this_auth'] != 'Y'))//auth only json support
					{
						$result = $out;
					} else {
						$result = static::expandData($out);
					}
					curl_close($obCurl);
	
					if (!empty($result['error'])) {
						if ($result['error'] == 'expired_token' && empty($arParams['this_auth'])) {

							$result = static::GetNewAuth($arParams, $domain);
						} else {
							$arErrorInform = [
								'expired_token' => 'expired token, cant get new auth? Check access oauth server.',
								'invalid_token' => 'invalid token, need reinstall application',
								'invalid_grant' => 'invalid grant, check out define C_REST_CLIENT_SECRET or C_REST_CLIENT_ID',
								'invalid_client' => 'invalid client, check out define C_REST_CLIENT_SECRET or C_REST_CLIENT_ID',
								'QUERY_LIMIT_EXCEEDED' => 'Too many requests, maximum 2 query by second',
								'ERROR_METHOD_NOT_FOUND' => 'Method not found! You can see the permissions of the application: CRest::call(\'scope\')',
								'NO_AUTH_FOUND' => 'Some setup error b24, check in table "b_module_to_module" event "OnRestCheckAuth"',
								'INTERNAL_SERVER_ERROR' => 'Server down, try later'
							];
							if (!empty($arErrorInform[$result['error']])) {
								$result['error_information'] = $arErrorInform[$result['error']];
							}
						}
					}
					if (!empty($info['curl_error'])) {
						$result['error'] = 'curl_error';
						$result['error_information'] = $info['curl_error'];
					}
	
					static::setLog(
						[
							'url' => $url,
							'info' => $info,
							'params' => $arParams,
							'result' => $result
						],
						'callOauthCurl'
					);
	
					return $result;
				} catch (Exception $e) {
					static::setLog(
						[
							'message' => $e->getMessage(),
							'code' => $e->getCode(),
							'trace' => $e->getTrace(),
							'params' => $arParams
						],
						'exceptionCurl'
					);
	
					return [
						'error' => 'exception',
						'error_exception_code' => $e->getCode(),
						'error_information' => $e->getMessage(),
					];
				}
			} else {
				static::setLog(
					[
						'params' => $arParams
					],
					'emptySetting'
				);
			}
	
			return [
				'error' => 'no_install_app',
				'error_information' => 'error install app, pls install local application '
			];
		}
	
		/**
		 * Generate a request for callOauthCurl()
		 *
		 * @return mixed array|string|boolean curl-return or error
		 * @var $params array method params
		 * @var $method string
		 */
	
		public static function call($method, $params = [], $domain = false)
		{
			$arPost = [
				'method' => $method,
				'params' => $params
			];
			if (defined('C_REST_CURRENT_ENCODING')) {
				$arPost['params'] = static::changeEncoding($arPost['params']);
			}
	
			if (!$domain && !empty($_REQUEST['DOMAIN'])) {
				$domain = $_REQUEST['DOMAIN'];
			} elseif (!$domain && !empty($_REQUEST['domain'])) {
				$domain = $_REQUEST['domain'];
			}

			if ($domain) {
				$result = static::callOauthCurl($arPost, $domain);
			} else {
				$result = static::callOauthCurl($arPost);
			}
			return $result;
		}
	
		/**
		 * @return array
		 *
		 * @var $arData array
		 * @var $halt   integer 0 or 1 stop batch on error
		 * @example $arData:
		 * $arData = [
		 *      'find_contact' => [
		 *          'method' => 'crm.duplicate.findbycomm',
		 *          'params' => [ "entity_type" => "CONTACT",  "type" => "EMAIL", "values" => array("info@bitrix24.com") ]
		 *      ],
		 *      'get_contact' => [
		 *          'method' => 'crm.contact.get',
		 *          'params' => [ "id" => '$result[find_contact][CONTACT][0]' ]
		 *      ],
		 *      'get_company' => [
		 *          'method' => 'crm.company.get',
		 *          'params' => [ "id" => '$result[get_contact][COMPANY_ID]', "select" => ["*"],]
		 *      ]
		 * ];
		 *
		 */
	
		public static function callBatch($arData, $domain = false, $halt = 0)
		{
			$arResult = [];
			if (is_array($arData)) {
				if (defined('C_REST_CURRENT_ENCODING')) {
					$arData = static::changeEncoding($arData);
				}
				$arDataRest = [];
				$i = 0;
				foreach ($arData as $key => $data) {
					if (!empty($data['method'])) {
						$i++;
						if (static::BATCH_COUNT >= $i) {
							$arDataRest['cmd'][$key] = $data['method'];
							if (!empty($data['params'])) {
								$arDataRest['cmd'][$key] .= '?' . http_build_query($data['params']);
							}
						}
					}
				}
				if (!empty($arDataRest)) {
					$arDataRest['halt'] = $halt;
					$arPost = [
						'method' => 'batch',
						'params' => $arDataRest
					];
	
					if (!$domain && !empty($_REQUEST['DOMAIN'])) {
						$domain = $_REQUEST['DOMAIN'];
					}
	
					if ($domain) {
						$arResult = static::callOauthCurl($arPost, $domain);
					} else {
						$arResult = static::callOauthCurl($arPost);
					}
				}
			}
			return $arResult;
		}
	
		/**
		 * Getting a new authorization and sending a request for the 2nd time
		 *
		 * @return array query result from $arParams
		 *
		 * @var $arParams array request when authorization error returned
		 */
	
		private static function GetNewAuth($arParams, $domain = false)
		{
			$result = [];
	
			if ($domain) {
				$arSettings = static::getAppSettings($domain);
			} else {
				$arSettings = static::getAppSettings();
			}
	
			if ($arSettings !== false) {
				$arParamsAuth = [
					'this_auth' => 'Y',
					'params' =>
						[
							'client_id' => $arSettings['C_REST_CLIENT_ID'],
							'grant_type' => 'refresh_token',
							'client_secret' => $arSettings['C_REST_CLIENT_SECRET'],
							'refresh_token' => $arSettings["refresh_token"],
						]
				];
				$newData = static::callOauthCurl($arParamsAuth, $domain);
	
				if (isset($newData['C_REST_CLIENT_ID'])) {
					unset($newData['C_REST_CLIENT_ID']);
				}
				if (isset($newData['C_REST_CLIENT_SECRET'])) {
					unset($newData['C_REST_CLIENT_SECRET']);
				}
				if (isset($newData['error'])) {
					unset($newData['error']);
				}
				$newData['application_token'] = $arSettings['application_token'];
				$newData['domain'] = $domain;
				if (static::setAppSettings($newData)) {
					$arParams['this_auth'] = 'N';
					$result = static::callOauthCurl($arParams, $domain);
				}
			}
			return $result;
		}

		/**
		 * Getting app ID
		 *
		 * @return integer as app ID
		 *
		 */
	
		public static function getAppId($domain, $auth_id)
		{
			
			$headers = array('Accept: application/json');

			$responseJson = static::callCurl("GET","https://".$domain."/rest/app.info?auth=".$auth_id, [] ,$headers);

			return json_decode($responseJson,1)["result"]["ID"];
		}
	
		/**
		 * @return boolean
		 * @var $isInstall  boolean true if install app by installApp()
		 * @var $arSettings array settings application
		 */
	
		private static function setAppSettings($arSettings, $isInstall = false)
		{
			$return = false;
	
			if (is_array($arSettings)) {
	
				$oldData = static::getAppSettings($arSettings['domain']);
				if ($isInstall != true && !empty($oldData) && is_array($oldData)) {
					$arSettings = array_merge($oldData, $arSettings);
				}
				$return = static::setSettingData($arSettings, $isInstall);
			}
			return $return;
		}
	
		/**
		 * @return mixed setting application for query
		 */
	
		private static function getAppSettings($domain = false)
		{
			if (defined("C_REST_WEB_HOOK_URL") && !empty(C_REST_WEB_HOOK_URL)) {
				$arData = [
					'client_endpoint' => C_REST_WEB_HOOK_URL,
					'is_web_hook' => 'Y'
				];
				$isCurrData = true;
			} else {
				$arData = static::getSettingData($domain);
				$isCurrData = false;
				if (
					!empty($arData['access_token']) &&
					!empty($arData['domain']) &&
					!empty($arData['refresh_token']) &&
					!empty($arData['application_token']) &&
					!empty($arData['client_endpoint'])
				) {
					$isCurrData = true;
				}
			}
	
			return ($isCurrData) ? $arData : false;
		}
	
		/**
		 * Can overridden this method to change the data storage location.
		 *
		 * @return array setting for getAppSettings()
		 */
	
		public static function getSettingData($domain): array
		{
			$return = [];
			$mysqli = self::getDBInstance();
			$tableName = DB_TABLE_NAME;
			
			// echo '<pre>'; print_r([$tableName]); echo '</pre>';
			foreach ($mysqli->query("SELECT * FROM $tableName WHERE domain='{$domain}'") as $row) {
				$return = $row;
			}
	
			//$return = static::expandData(file_get_contents(__DIR__ . '/settings.json'));
	
			if (defined("C_REST_CLIENT_ID") && !empty(C_REST_CLIENT_ID)) {
				$return['C_REST_CLIENT_ID'] = C_REST_CLIENT_ID;
			}
			if (defined("C_REST_CLIENT_SECRET") && !empty(C_REST_CLIENT_SECRET)) {
				$return['C_REST_CLIENT_SECRET'] = C_REST_CLIENT_SECRET;
			}
	
			return $return;
		}
	
		/**
		 * @return string json_encode with encoding
		 * @var $encoding boolean true - encoding to utf8, false - decoding
		 *
		 * @var $data mixed
		 */
		protected static function changeEncoding($data, $encoding = true)
		{
			if (is_array($data)) {
				$result = [];
				foreach ($data as $k => $item) {
					$k = static::changeEncoding($k, $encoding);
					$result[$k] = static::changeEncoding($item, $encoding);
				}
			} else {
				if ($encoding) {
					$result = iconv(C_REST_CURRENT_ENCODING, "UTF-8//TRANSLIT", $data);
				} else {
					$result = iconv("UTF-8", C_REST_CURRENT_ENCODING, $data);
				}
			}
	
			return $result;
		}
	
		/**
		 * @return string json_encode with encoding
		 * @var $debag boolean
		 *
		 * @var $data mixed
		 */
		protected static function wrapData($data, $debag = false)
		{
			if (defined('C_REST_CURRENT_ENCODING')) {
				$data = static::changeEncoding($data, true);
			}
			$return = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	
			if ($debag) {
				$e = json_last_error();
				if ($e != JSON_ERROR_NONE) {
					if ($e == JSON_ERROR_UTF8) {
						return 'Failed encoding! Recommended \'UTF - 8\' or set define C_REST_CURRENT_ENCODING = current site encoding for function iconv()';
					}
				}
			}
	
			return $return;
		}
	
		/**
		 * @return string json_decode with encoding
		 * @var $debag boolean
		 *
		 * @var $data mixed
		 */
		protected static function expandData($data)
		{
			$return = json_decode($data, true);
			if (defined('C_REST_CURRENT_ENCODING')) {
				$return = static::changeEncoding($return, false);
			}
			return $return;
		}
	
		/**
		 * Can override this method to change the data storage location.
		 *
		 * @return boolean is successes save data for setSettingData()
		 * @var $arSettings array settings application
		 */
	
		protected static function setSettingData($arSettings, $isInstall)
		{
			$mysqli = self::getDBInstance();
			$tableName = DB_TABLE_NAME;
			$domain = $arSettings['domain'];
			$access_token = $arSettings['access_token'];
			$expires_in = $arSettings['expires_in'];
			$application_token = $arSettings['application_token'];
			$refresh_token = $arSettings['refresh_token'];
			$client_endpoint = $arSettings['client_endpoint'];

			if ($isInstall) {
				return (boolean)$mysqli->query("INSERT INTO `$tableName` (`domain`, `access_token`, `refresh_token`, `application_token`, `expires_in`, `client_endpoint`) VALUES ('$domain', '$access_token', '$refresh_token', '$application_token','$expires_in','$client_endpoint')");
			} else {
				return (boolean)$mysqli->query("UPDATE `$tableName` SET `domain`='$domain', `access_token`='$access_token', `refresh_token`='$refresh_token', `application_token`='$application_token', `expires_in`='$expires_in', `client_endpoint`='$client_endpoint' WHERE `domain`='$domain'");
			}
		}

        public static function getMyDiskId($userId, $domain)
        {
            $result = CRest::call('disk.storage.getlist', [
                'filter' => [
                    'ENTITY_ID' => $userId, // ID сотрудника
                ]
            ], $domain);

            if (!empty($result['result'][0]['ID'])) {
                return $result['result'][0]['ID'];
            } else {
                return false;
            }
        }

        public static function getUploadedFilesFolderId($diskId, $domain)
        {
            $result = CRest::call('disk.storage.getchildren', [
                'id' => $diskId
            ], $domain);

            if (!empty($result['result'])) {
                foreach ($result['result'] as $folder) {
                    if ($folder['NAME'] === 'Загруженные файлы') {
                        return $folder['ID'];
                    }
                }
            }
            return false;
        }

        public static function uploadFileToDisk($folderId, $fileName, $fileBase64, $domain)
        {
            $result = CRest::call('disk.folder.uploadfile', [
                'id' => $folderId,
                'data' => [
                    'NAME' => $fileName
                ],
                'fileContent' => [ $fileName,  $fileBase64 ]
//                'fileContent' => $fileName . ';base64,' . $fileBase64
            ], $domain);

            if (!empty($result['result']['ID'])) {
                return $result['result']['ID'];
            } else {
                return false;
            }
        }

        public static function getFileBase64($fileUrl)
        {
            $fileData = file_get_contents($fileUrl);
            if ($fileData === false) {
                return false;
            }
            return base64_encode($fileData);
        }



        /**
		 * Can overridden this method to change the log data storage location.
		 *
		 * @return boolean is successes save log data
		 * @var $type   string to more identification log data
		 * @var $arData array of logs data
		 */
	
		public static function setLog($arData, $type = '')
		{
			$return = false;
			if (!defined("C_REST_BLOCK_LOG") || C_REST_BLOCK_LOG !== true) {
				if (defined("C_REST_LOGS_DIR")) {
					$path = C_REST_LOGS_DIR;
				} else {
					$path = __DIR__ . '/logs/';
				}
				$path .= date("Y-m-d/H") . '/';
	
				if (!file_exists($path)) {
					@mkdir($path, 0775, true);
				}
	
				$path .= time() . '_' . $type . '_' . rand(1, 9999999) . 'log';
				if (!defined("C_REST_LOG_TYPE_DUMP") || C_REST_LOG_TYPE_DUMP !== true) {
					$jsonLog = static::wrapData($arData);
					if ($jsonLog === false) {
						$return = file_put_contents($path . '_backup.txt', var_export($arData, true));
					} else {
						$return = file_put_contents($path . '.json', $jsonLog);
					}
				} else {
					$return = file_put_contents($path . '.txt', var_export($arData, true));
				}
			}
			return $return;
		}
	
		/**
		 * check minimal settings server to work CRest
		 * @return array of errors
		 * @var $print boolean
		 */
		public static function checkServer($print = true)
		{
			$return = [];
	
			//check curl lib install
			if (!function_exists('curl_init')) {
				$return['curl_error'] = 'Need install curl lib.';
			}
	
			//creat setting file
			file_put_contents(__DIR__ . '/settings_check.json', static::wrapData(['test' => 'data']));
			if (!file_exists(__DIR__ . '/settings_check.json')) {
				$return['setting_creat_error'] = 'Check permission! Recommended: folders: 775, files: 664';
			}
			unlink(__DIR__ . '/settings_check.json');
			//creat logs folder and files
			$path = __DIR__ . '/logs/' . date("Y-m-d/H") . '/';
			if (!mkdir($path, 0775, true) && !file_exists($path)) {
				$return['logs_folder_creat_error'] = 'Check permission! Recommended: folders: 775, files: 664';
			} else {
				file_put_contents($path . 'test.txt', var_export(['test' => 'data'], true));
				if (!file_exists($path . 'test.txt')) {
					$return['logs_file_creat_error'] = 'check permission! recommended: folders: 775, files: 664';
				}
				unlink($path . 'test.txt');
			}
	
			if ($print === true) {
				if (empty($return)) {
					$return['success'] = 'Success!';
				}
				echo '<pre>';
				print_r($return);
				echo '</pre>';
	
			}
	
			return $return;
		}

		public static function callCurl($methodType,$url,$params,$headers)
		{
			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => $methodType,
				CURLOPT_POSTFIELDS => $params,
				CURLOPT_HTTPHEADER => $headers
			));

			$response = curl_exec($curl);

			curl_close($curl);
			return $response;
		}

		public static function callTemplate($tmpName, $arData )
		{
			$blade = new Blade('../components/', '../components/cache');
			return $blade->make( $tmpName, $arData )->render();
		}

		public static function callMessage($type, $title, $body, $subtitle=null)
		{
			
			return "<!-- bootstrap stylesheets -->
				<link rel=\"stylesheet\" href=\"https://bootstrap-4.ru/docs/5.3/scss/helpers/_color-bg.scss\">
				<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css\">
				<link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\" integrity=\"sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH\" crossorigin=\"anonymous\">
				<!-- bootstrap stylesheets / -->
				<body style=\"background: rgba(76, 78, 100, 0.5) !important;\">
					<div class=\"bitrix-app tab_container container-lg h-100 pt-3 pb-3\">
						<div class=\"row justify-content-center align-items-center h-100\">
							<div class=\"alert alert-$type mt-3 mb-3\" role=\"alert\" style=\"max-width: 720px;\">
								<h4 class=\"alert-heading\">$title</h4>
								<p>$subtitle</p>
								<hr>
								<p class=\"mb-0\">$body</p>
							</div>
						</div>
					</div>
				</body>";

		}
		
		public static function addLogs($result, $comment, $fileName)
		{
			$html = '\-------'.$comment."---------\n";
			$html.= print_r($result,true);
			$html.="\n".date("d.m.Y H:i:s")."\n--------------------\n\n\n";
			$file="/home/bitrix/www/dev/rest/telegram.tasks/logs/$fileName.txt";
			$old_data=file_get_contents($file);
			file_put_contents($file, $html.$old_data);
		}

	}