<?php

/**
 * Cron entry of the tutorial How to create a Cron Entry to move threds (with options!).
 *
 * MoveThreadCron = Name of our folder (and add-on too!)
 * CronEntry = name of this file!
 *
 */
class Moturdrn_TS3GW2Auth_CronEntry
{
    const CHECK_EVERY_HOURS = 12;

    public static function refreshAPIData()
    {
        /*
         * We need the Teamspeak 3 and GW2 API libraries
         */
        require_once "library/Moturdrn/TS3GW2Auth/Includes/GW2_API_Tools/GW2_API_Tools.php";
        require_once "library/Moturdrn/TS3GW2Auth/Includes/TeamSpeak3/ts3admin.class.php";
        $tsAdmin = new ts3admin(XenForo_Application::getOptions()->ts3gw2auth_ts3server, XenForo_Application::getOptions()->ts3gw2auth_ts3serverquery);
        if ($tsAdmin->getElement('success', $tsAdmin->connect())) {
            $tsAdmin->login(XenForo_Application::getOptions()->ts3gw2auth_ts3username, XenForo_Application::getOptions()->ts3gw2auth_ts3password);
            $tsAdmin->selectServer(XenForo_Application::getOptions()->ts3gw2auth_ts3port);
        }

        $logArray = array();

        /*
         * Create the Auth model for getting keys etc
         */
        $authModel = XenForo_Model::create('Moturdrn_TS3GW2Auth_Model_Auth');

        $checkSchedule = self::CHECK_EVERY_HOURS;
        $lastChecked_date = date('Y-m-d H:i:s', strtotime("-{$checkSchedule} hours"));
        $lastChecked = strtotime($lastChecked_date);
        $auths = $authModel->getAuthOlderThan($lastChecked);

        $checkDate = strtotime("now");
        foreach ($auths as $auth) {
            $APIData = gw2_api_request('/v2/account', $auth['gw2_apikey']);
            $response = json_decode($APIData[count($APIData) - 1], true);

            /* API Returned OK - Key Valid */
            if ($APIData[0] == 'HTTP/1.1 200 OK') {
                /**     @var $authWriter Moturdrn_TS3GW2Auth_DataWriter_Auth   * */
                $authWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Auth');
                $authWriter->setExistingData($auth['ts3_uniqueid']);
                $authWriter->set('gw2_name', $response['name']);
                $authWriter->set('gw2_account_guid', $response['id']);
                $authWriter->set('gw2_world', $response['world']);
                $authWriter->set('last_check', $checkDate);
                $authWriter->save();

                /* API Returned Bad Request - Key Revoked */
            } elseif ($APIData[0] == 'HTTP/1.1 400 Bad Request' && $response['text'] == 'invalid key') {
                /**     @var $authWriter Moturdrn_TS3GW2Auth_DataWriter_Auth   * */
                $authWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Auth');
                $authWriter->setExistingData($auth['ts3_uniqueid']);
                $authWriter->set('gw2_world', 0);
                $authWriter->save();

                $logWriter = XenForo_DataWriter::create('Moturdrn_TS3GW2Auth_DataWriter_Log');
                $logWriter->set('date', $checkDate);
                $logWriter->set('ts3_uniqueid', $auth['ts3_uniqueid']);
                $logWriter->set('ts3_dbid', $auth['ts3_dbid']);
                $logWriter->set('message', 'API Key Not Valid');
                $logWriter->set('apikey', $auth['gw2_apikey']);
                $logWriter->set('apidata', serialize($APIData));
                $logWriter->set('extradata', '');
                $logWriter->save();
            }
        }

        /*
         * Get Verified TS3 IDs and Unverified TS3 IDs
         */
        $verified = $authModel->getAuthVerified(XenForo_Application::getOptions()->ts3gw2auth_worldid);

        $verifiedMembers = $currentMembers = $newMembers = $oldMembers = array();

        foreach ($verified as $key => $addToGroup) {
            $verifiedMembers[] = $addToGroup['ts3_uniqueid'];
        }

        $group_members = $tsAdmin->serverGroupClientList(XenForo_Application::getOptions()->ts3gw2auth_verified_group, true);

        foreach ($group_members['data'] as $member) {
            $currentMembers[] = $member['client_unique_identifier'];
        }

        $newMembers = array_diff($verifiedMembers, $currentMembers);
        $oldMembers = array_diff($currentMembers, $verifiedMembers);

        foreach ($newMembers as $newMember) {
            $ts3_dbid = $tsAdmin->clientGetDbIdFromUid($newMember);

            if ($ts3_dbid['success'] == 1) {
                $tsAdmin->serverGroupAddClient(XenForo_Application::getOptions()->ts3gw2auth_verified_group, $ts3_dbid['data']['cldbid']);
            }
        }

        foreach ($oldMembers as $oldMember) {
            $ts3_dbid = $tsAdmin->clientGetDbIdFromUid($oldMember);

            if ($ts3_dbid['success'] == 1) {
                $tsAdmin->serverGroupDeleteClient(XenForo_Application::getOptions()->ts3gw2auth_verified_group, $ts3_dbid['data']['cldbid']);
            }
        }
    }
}