<?php

class Moturdrn_TS3GW2Auth_Model_Auth extends XenForo_Model
{
    public function getAuthByTS3UId($ts3_uid)
    {
        return $this->_getDb()->fetchRow('SELECT * FROM xf_moturdrn_ts3gw2auth_auth WHERE ts3_uniqueid = ?', $ts3_uid);
    }

    public function getAuthUnverified($worldId)
    {
        return $this->fetchAllKeyed($this->limitQueryResults(
            'SELECT *
				FROM xf_moturdrn_ts3gw2auth_auth
				WHERE gw2_world != ' . $this->_getDb()->quote($worldId), 0), 'ts3_uniqueid');
    }

    public function getAuthVerified($worldId)
    {
        return $this->fetchAllKeyed($this->limitQueryResults(
            'SELECT *
				FROM xf_moturdrn_ts3gw2auth_auth
				WHERE gw2_world = ' . $this->_getDb()->quote($worldId) . ' OR (gw2_apikey != \'\' AND gw2_account_guid = \'\')', 0), 'ts3_uniqueid');
    }

    public function getAuthOlderThan($lastCheck)
    {
        return $this->fetchAllKeyed($this->limitQueryResults(
            'SELECT *
				FROM xf_moturdrn_ts3gw2auth_auth
				WHERE gw2_apikey != \'\' AND last_check <= ' . $this->_getDb()->quote($lastCheck), 0), 'ts3_uniqueid');
    }

    public function getOtherAuthsByGW2GUID($gw2_guid, $ts3_uid)
    {
        return $this->fetchAllKeyed($this->limitQueryResults(
            'SELECT *
				FROM xf_moturdrn_ts3gw2auth_auth
				WHERE gw2_account_guid = ' . $this->_getDb()->quote($gw2_guid) . ' AND ts3_uniqueid != ' . $this->_getDb()->quote($ts3_uid), 0), 'ts3_uniqueid');
    }
}