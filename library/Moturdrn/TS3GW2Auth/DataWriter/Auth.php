<?php

class Moturdrn_TS3GW2Auth_DataWriter_Auth extends XenForo_DataWriter
{
    protected function _getFields()
    {
        return array(
            'xf_moturdrn_ts3gw2auth_auth' => array(
                'ts3_uniqueid' => array('type' => self::TYPE_STRING, 'maxLength' => 250),

                'ts3_dbid' => array('type' => self::TYPE_UINT),

                'gw2_name' => array('type' => self::TYPE_STRING, 'maxLength' => 250, 'default' => '',),

                'gw2_account_guid' => array('type' => self::TYPE_STRING, 'maxLength' => 250, 'default' => '',),

                'gw2_apikey' => array('type' => self::TYPE_STRING, 'maxLength' => 250, 'default' => ''),

                'gw2_world' => array('type' => self::TYPE_UINT, 'default' => 0),

                'last_check' => array('type' => self::TYPE_STRING, 'maxLength' => 250, 'default' => 0),

                'sessionid' => array('type' => self::TYPE_STRING, 'maxLength' => 250, 'default' => ''),

                'keyname' => array('type' => self::TYPE_STRING, 'maxLength' => 250, 'default' => '')
            )
        );
    }

    protected function _getExistingData($data)
    {
        if (!$ts3Uniqueid = $this->_getExistingPrimaryKey($data, 'ts3_uniqueid')) {
            return false;
        }

        if (!$authInfo = $this->_getAuthModel()->getAuthByTS3UId($ts3Uniqueid)) {
            return false;
        }

        return $this->getTablesDataFromArray($authInfo);
    }

    /**
     * Gets SQL condition to update the existing record.
     *
     * @return string
     */
    protected function _getUpdateCondition($tableName)
    {
        return 'ts3_uniqueid = ' . $this->_db->quote($this->getExisting('ts3_uniqueid'));
    }

    protected function _getAuthModel()
    {
        return $this->getModelFromCache('Moturdrn_TS3GW2Auth_Model_Auth');
    }
}