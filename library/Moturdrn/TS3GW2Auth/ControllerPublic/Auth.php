<?php

class Moturdrn_TS3GW2Auth_ControllerPublic_Auth extends XenForo_ControllerPublic_Abstract
{
    protected $_ts3group;
    protected $_ts3server;
    protected $_ts3port;
    protected $_ts3sqport;
    protected $_ts3user;
    protected $_ts3pass;
    protected $_ts3prefix;
    protected $_gw2world;

    /**
     * Session activity details.
     * @see XenForo_Controller::getSessionActivityDetailsForList()
     */
    public static function getSessionActivityDetailsForList(array $activities)
    {
        return new XenForo_Phrase('Verifying Teamspeak Identity');
    }

    public function actionAuth()
    {
        $this->_ts3group = XenForo_Application::getOptions()->ts3gw2auth_verified_group;
        $this->_ts3server = XenForo_Application::getOptions()->ts3gw2auth_ts3server;
        $this->_ts3port = XenForo_Application::getOptions()->ts3gw2auth_ts3port;
        $this->_ts3sqport = XenForo_Application::getOptions()->ts3gw2auth_ts3serverquery;
        $this->_ts3user = XenForo_Application::getOptions()->ts3gw2auth_ts3username;
        $this->_ts3pass = XenForo_Application::getOptions()->ts3gw2auth_ts3password;
        $this->_gw2world = XenForo_Application::getOptions()->ts3gw2auth_worldid;
        $this->_ts3prefix = XenForo_Application::getOptions()->ts3gw2auth_keyprefix;

        require_once "library/Moturdrn/TS3GW2Auth/Includes/GW2_API_Tools/GW2_API_Tools.php";
        require_once "library/Moturdrn/TS3GW2Auth/Includes/TeamSpeak3/ts3admin.class.php";
        $tsAdmin = new ts3admin($this->_ts3server, $this->_ts3sqport);
        if ($tsAdmin->getElement('success', $tsAdmin->connect())) {
            $tsAdmin->login($this->_ts3user, $this->_ts3pass);
            $tsAdmin->selectServer($this->_ts3port);
        } else {
            throw $this->responseException($this->responseError('There was an error connecting to the TeamSpeak Server.', 400));
        }

        $visitor = XenForo_Visitor::getInstance();
        $session = XenForo_Session::startPublicSession();
        $sessionId = $session->getSessionId();
        $ts3_id = $session->get('last_ts3_id');
        $ts3_dbid = $session->get('last_ts3_dbid');
        $remove_old_auths = $session->get('remove_old_auths');
        $session->set('last_ts3_id', '');
        $session->set('last_ts3_dbid', 0);
        $session->set('remove_old_auths', '');

        $logArray = array();

        if ($ts3_id == '') {
            return $this->actionIndex();
        }

        $apiKey = $this->_input->filterSingle('APIKey', XenForo_Input::STRING);

        if ($apiKey == "") {
            throw $this->responseException($this->responseError('You must enter an API Key.', 400));
        }

        $APIData = gw2_api_request('/v2/tokeninfo', $apiKey);
        $response = json_decode($APIData[count($APIData) - 1], true);

        if (!$authRecord = $this->_getAuthModel()->getAuthByTS3UId($ts3_id)) {
            throw $this->responseException($this->responseError('You have tried to authenticate an ID which has not started verification.', 400));
        }

        $ts3_user = $tsAdmin->clientDBInfo($ts3_dbid);

        if ($ts3_user['success'] != 1) {
            throw $this->responseException($this->responseError('You must supply a valid Teamspeak ID', 400));
        }

        $ts3_user = $ts3_user['data'];

        $logArray = array(
            'date' => strtotime("now"),
            'ts3_uniqueid' => $ts3_user['client_unique_identifier'],
            'ts3_dbid' => $ts3_user['client_database_id'],
            'message' => '',
            'apikey' => $apiKey,
            'apidata' => '',
            'extradata' => '',
        );

        if ($authRecord['sessionid'] != $sessionId) {
            $logArray['message'] = 'Session Mismatch';
            $logArray['apidata'] = serialize($APIData);
            $this->writeLog($logArray);
            throw $this->responseException($this->responseError('Mismatching sessions - potential CSRF detected. Please inform an admin if you received this in error.', 400));
        }

        if ($APIData[0] == 'HTTP/1.1 200 OK') {
            if (!array_key_exists('name', $response)) {
                $response['name'] = '';
            }

            if ($response['name'] == $authRecord['keyname']) {
                $logArray['message'] = 'Inserting TokenInfo';
                $logArray['apidata'] = serialize($APIData);
                $this->writeLog($logArray);

                $APIData = gw2_api_request('/v2/account', $apiKey);
                $response = json_decode($APIData[count($APIData) - 1], true);

                if ($APIData[0] == 'HTTP/1.1 200 OK') {
                    if ($existingAuths = $this->_getAuthModel()->getOtherAuthsByGW2GUID($response['id'], $ts3_id)) {
                        foreach ($existingAuths as $existingAuth) {
                            $this->doAuthUnAuth('UnAuth', $existingAuth['ts3_dbid']);
                            /**     @var $authDeleter Moturdrn_TS3GW2Auth_DataWriter_Auth   * */
                            $authDeleter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Auth');
                            $authDeleter->setExistingData($existingAuth['ts3_uniqueid']);
                            $authDeleter->set('gw2_name', '');
                            $authDeleter->set('gw2_account_guid', '');
                            $authDeleter->set('gw2_apikey', '');
                            $authDeleter->set('gw2_world', 0);
                            $authDeleter->set('last_check', strtotime("now"));
                            $authDeleter->save();
                        }
                    }

                    if ($response['world'] == $this->_gw2world) {
                        $this->doAuthUnAuth('Auth', $ts3_user['client_database_id']);

                        $logArray['message'] = 'Account Verified';
                        $logArray['apidata'] = serialize($APIData);
                        $this->writeLog($logArray);

                    } else {
                        $this->doAuthUnAuth('UnAuth', $ts3_user['client_database_id']);

                        $logArray['message'] = 'Non-Verified World';
                        $logArray['apidata'] = serialize($APIData);
                        $this->writeLog($logArray);
                    }

                    $session->set('last_ts3_id', '');
                    $session->set('last_ts3_dbid', 0);
                    $session->set('remove_old_auths', false);

                    /**     @var $authWriter Moturdrn_TS3GW2Auth_DataWriter_Auth   * */
                    $authWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Auth');
                    $authWriter->setExistingData($ts3_user['client_unique_identifier']);
                    $authWriter->set('gw2_name', $response['name']);
                    $authWriter->set('gw2_account_guid', $response['id']);
                    $authWriter->set('gw2_apikey', $apiKey);
                    $authWriter->set('gw2_world', $response['world']);
                    $authWriter->set('last_check', strtotime("now"));
                    $authWriter->save();

                    $session->set('gw2_world', $response['world']);

                    return $this->responseRedirect(
                        XenForo_ControllerResponse_Redirect::SUCCESS,
                        XenForo_Link::buildPublicLink('TS3Auth/Complete'),
                        'Verification Complete.'
                    );
                } elseif ($APIData[0] == 'HTTP/1.1 400 Bad Request') {
                    if ($response['text'] == 'invalid key') {
                        $logArray['message'] = 'Invalid API Key';
                        $logArray['apidata'] = serialize($APIData);
                        $this->writeLog($logArray);
                        $this->doAuthUnAuth('UnAuth', $ts3_user['client_database_id']);
                        throw $this->responseException($this->responseError("You have entered an invalid API Key, please try again.", 400));
                    } else {
                        $logArray['message'] = 'Unknown Error';
                        $logArray['apidata'] = serialize($APIData);
                        $this->writeLog($logArray);

                        $logArray['date'] = strtotime("now");
                        $logArray['message'] = 'Adding Temporary Access';
                        $logArray['apidata'] = serialize($APIData);
                        $this->writeLog($logArray);

                        /**     @var $authWriter Moturdrn_TS3GW2Auth_DataWriter_Auth   * */
                        $authWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Auth');
                        $authWriter->setExistingData($ts3_user['client_unique_identifier']);
                        $authWriter->set('gw2_name', '');
                        $authWriter->set('gw2_account_guid', '');
                        $authWriter->set('gw2_apikey', $apiKey);
                        $authWriter->set('gw2_world', 0);
                        $authWriter->set('last_check', 0);
                        $authWriter->save();

                        $this->doAuthUnAuth('Auth', $ts3_user['client_database_id']);

                        return $this->responseRedirect(
                            XenForo_ControllerResponse_Redirect::SUCCESS,
                            XenForo_Link::buildPublicLink('TS3Auth/Error'),
                            'Error whilst Verifying.'
                        );
                    }
                } else {
                    $logArray['message'] = 'Unknown Error';
                    $logArray['apidata'] = serialize($APIData);
                    $this->writeLog($logArray);

                    $logArray['date'] = strtotime("now");
                    $logArray['message'] = 'Adding Temporary Access';
                    $logArray['apidata'] = serialize($APIData);
                    $this->writeLog($logArray);

                    /**     @var $authWriter Moturdrn_TS3GW2Auth_DataWriter_Auth   * */
                    $authWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Auth');
                    $authWriter->setExistingData($ts3_user['client_unique_identifier']);
                    $authWriter->set('gw2_name', '');
                    $authWriter->set('gw2_account_guid', '');
                    $authWriter->set('gw2_apikey', $apiKey);
                    $authWriter->set('gw2_world', 0);
                    $authWriter->set('last_check', 0);
                    $authWriter->save();

                    $this->doAuthUnAuth('Auth', $ts3_user['client_database_id']);

                    return $this->responseRedirect(
                        XenForo_ControllerResponse_Redirect::SUCCESS,
                        XenForo_Link::buildPublicLink('TS3Auth/Error'),
                        'Error whilst Verifying.'
                    );
                }
            } else {
                $session->set('last_ts3_id', $ts3_user['client_unique_identifier']);
                $session->set('last_ts3_dbid', $ts3_user['client_database_id']);
                $logArray['message'] = 'Unexpected Key Name';
                $logArray['apidata'] = serialize($APIData);
                $logArray['extradata'] = "Expected Key Name: {$authRecord['keyname']}";
                $this->doAuthUnAuth('UnAuth', $ts3_user['client_database_id']);
                $this->writeLog($logArray);
                throw $this->responseException($this->responseError("The Key used was named {$response['name']}. Expected {$authRecord['keyname']}. Please close this message and make sure you create a NEW key with the correct name.", 400));
            }
        } elseif ($response['text'] == 'endpoint requires authentication') {
            $logArray['message'] = 'Endpoint Requires Authentication';
            $logArray['apidata'] = serialize($APIData);
            $this->writeLog($logArray);
            $this->doAuthUnAuth('UnAuth', $ts3_user['client_database_id']);
            throw $this->responseException($this->responseError("You have entered an invalid API Key, please try again.", 400));
        } else {
            $logArray['message'] = 'Unknown Error';
            $logArray['apidata'] = serialize($APIData);
            $this->writeLog($logArray);

            $logArray['date'] = strtotime("now");
            $logArray['message'] = 'Adding Temporary Access';
            $logArray['apidata'] = serialize($APIData);
            $this->writeLog($logArray);

            /**     @var $authWriter Moturdrn_TS3GW2Auth_DataWriter_Auth   * */
            $authWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Auth');
            $authWriter->setExistingData($ts3_user['client_unique_identifier']);
            $authWriter->set('gw2_name', '');
            $authWriter->set('gw2_account_guid', '');
            $authWriter->set('gw2_apikey', $apiKey);
            $authWriter->set('gw2_world', 0);
            $authWriter->set('last_check', 0);
            $authWriter->save();

            $this->doAuthUnAuth('Auth', $ts3_user['client_database_id']);

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('TS3Auth/Error'),
                'Error whilst Verifying.'
            );
        }
    }

    public function actionIndex()
    {
        $this->_ts3group = XenForo_Application::getOptions()->ts3gw2auth_verified_group;
        $this->_ts3server = XenForo_Application::getOptions()->ts3gw2auth_ts3server;
        $this->_ts3port = XenForo_Application::getOptions()->ts3gw2auth_ts3port;
        $this->_ts3sqport = XenForo_Application::getOptions()->ts3gw2auth_ts3serverquery;
        $this->_ts3user = XenForo_Application::getOptions()->ts3gw2auth_ts3username;
        $this->_ts3pass = XenForo_Application::getOptions()->ts3gw2auth_ts3password;
        $this->_gw2world = XenForo_Application::getOptions()->ts3gw2auth_worldid;
        $this->_ts3prefix = XenForo_Application::getOptions()->ts3gw2auth_keyprefix;

        require_once "library/Moturdrn/TS3GW2Auth/Includes/TeamSpeak3/ts3admin.class.php";
        $tsAdmin = new ts3admin($this->_ts3server, $this->_ts3sqport);
        if ($tsAdmin->getElement('success', $tsAdmin->connect())) {
            $tsAdmin->login($this->_ts3user, $this->_ts3pass);
            $tsAdmin->selectServer($this->_ts3port);
        } else {
            throw $this->responseException($this->responseError('There was an error connecting to the TeamSpeak Server.', 400));
        }

        $visitor = XenForo_Visitor::getInstance();
        $session = XenForo_Session::startPublicSession();
        $sessionId = $session->getSessionId();
        $session->set('last_ts3_id', '');
        $session->set('last_ts3_dbid', 0);
        $session->set('remove_old_auths', false);

        if ($_SERVER['REMOTE_ADDR']) $ipaddress = $_SERVER['REMOTE_ADDR'];

        $tsId = $this->_input->filterSingle('tsid', XenForo_Input::UINT);

        if (!$tsId) {
            throw $this->responseException($this->responseError('You must supply a valid Teamspeak ID', 400));
        }

        $ts3_user = $tsAdmin->clientDBInfo($tsId);

        if ($ts3_user['success'] != 1) {
            throw $this->responseException($this->responseError('You must supply a valid Teamspeak ID', 400));
        }

        $ts3_user = $ts3_user['data'];

        $ts3_user_ip = $ts3_user['client_lastip'];

        $ts3_nickname = $ts3_user['client_nickname'];

        if ($ts3_user_ip != $ipaddress) {
            throw $this->responseException($this->responseError('Last TS IP and current IP are different, please verify whilst TS is connected.', 400));
        }

        $keyname = $this->_ts3prefix . uniqid();

        /**     @var $authWriter Moturdrn_TS3GW2Auth_DataWriter_Auth   * */
        $authWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Auth');

        if ($this->_getAuthModel()->getAuthByTS3UId($ts3_user['client_unique_identifier'])) {
            $authWriter->setExistingData($ts3_user['client_unique_identifier']);
        } else {
            $authWriter->set('ts3_uniqueid', $ts3_user['client_unique_identifier']);
            $authWriter->set('ts3_dbid', $ts3_user['client_database_id']);
            $authWriter->set('gw2_name', '');
            $authWriter->set('gw2_account_guid', '');
            $authWriter->set('gw2_apikey', '');
            $authWriter->set('gw2_world', '');
            $authWriter->set('last_check', strtotime("now"));
        }

        $authWriter->set('sessionid', $sessionId);
        $authWriter->set('keyname', $keyname);

        $authWriter->preSave();
        $authWriter->save();

        /**     @var $logWriter Moturdrn_TS3GW2Auth_DataWriter_Log   * */
        $logWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Log');
        $logWriter->set('date', strtotime("now"));
        $logWriter->set('ts3_uniqueid', $ts3_user['client_unique_identifier']);
        $logWriter->set('ts3_dbid', $ts3_user['client_database_id']);
        $logWriter->set('message', 'Verification Start');
        $logWriter->set('apikey', '');
        $logWriter->set('apidata', '');
        $logWriter->set('extradata', "Expected Key Name: {$keyname}");

        $logWriter->preSave();
        $logWriter->save();

        $viewParams = array(
            'keyname' => $keyname,
            'ts3_nickname' => $ts3_nickname,
            'sessionId' => $sessionId,
        );

        $session->set('last_ts3_id', $ts3_user['client_unique_identifier']);
        $session->set('last_ts3_dbid', $ts3_user['client_database_id']);

        return $this->responseView('Moturdrn_TS3GW2Auth_ViewPublic_Index', 'Moturdrn_TS3GW2Auth_Index', $viewParams);
    }

    protected function _getAuthModel()
    {
        return $this->getModelFromCache('Moturdrn_TS3GW2Auth_Model_Auth');
    }

    public function writeLog(array $data)
    {
        /**     @var $logWriter Moturdrn_TS3GW2Auth_DataWriter_Log   * */
        $logWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Log');
        $logWriter->set('date', $data['date']);
        $logWriter->set('ts3_uniqueid', $data['ts3_uniqueid']);
        $logWriter->set('ts3_dbid', $data['ts3_dbid']);
        $logWriter->set('message', $data['message']);
        $logWriter->set('apikey', $data['apikey']);
        $logWriter->set('apidata', $data['apidata']);
        $logWriter->set('extradata', $data['extradata']);
        $logWriter->save();
    }

    public function doAuthUnAuth($action, $ts3_dbid)
    {
        $this->_ts3group = XenForo_Application::getOptions()->ts3gw2auth_verified_group;
        $this->_ts3server = XenForo_Application::getOptions()->ts3gw2auth_ts3server;
        $this->_ts3port = XenForo_Application::getOptions()->ts3gw2auth_ts3port;
        $this->_ts3sqport = XenForo_Application::getOptions()->ts3gw2auth_ts3serverquery;
        $this->_ts3user = XenForo_Application::getOptions()->ts3gw2auth_ts3username;
        $this->_ts3pass = XenForo_Application::getOptions()->ts3gw2auth_ts3password;
        $this->_gw2world = XenForo_Application::getOptions()->ts3gw2auth_worldid;
        $this->_ts3prefix = XenForo_Application::getOptions()->ts3gw2auth_keyprefix;

        require_once "library/Moturdrn/TS3GW2Auth/Includes/TeamSpeak3/ts3admin.class.php";
        $tsAdmin = new ts3admin($this->_ts3server, $this->_ts3sqport);
        if ($tsAdmin->getElement('success', $tsAdmin->connect())) {
            $tsAdmin->login($this->_ts3user, $this->_ts3pass);
            $tsAdmin->selectServer($this->_ts3port);
        }

        if ($action == 'Auth') {
            $tsAdmin->serverGroupAddClient($this->_ts3group, $ts3_dbid);
        } elseif ($action == 'UnAuth') {
            $tsAdmin->serverGroupDeleteClient($this->_ts3group, $ts3_dbid);
        }
    }

    public function actionComplete()
    {
        $session = XenForo_Session::startPublicSession();
        $worldId = $session->get('gw2_world');
        $session->set('gw2_world', '');

        if (!$worldId) {
            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink(''), '');
        }

        $viewParams = array(
            'world' => $worldId,
        );

        return $this->responseView('Moturdrn_TS3GW2Auth_ViewPublic_Complete', 'Moturdrn_TS3GW2Auth_Complete', $viewParams);
    }

    public function actionError()
    {
        $viewParams = array();
        return $this->responseView('Moturdrn_TS3GW2Auth_ViewPublic_Error', 'Moturdrn_TS3GW2Auth_Error', $viewParams);
    }
}