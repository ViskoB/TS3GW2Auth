<?php

class Moturdrn_TS3GW2Auth_DataWriter_Log extends XenForo_DataWriter
{
    protected function _getFields()
    {
        return array(
            'xf_moturdrn_ts3gw2auth_log' => array(
                'logid' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),

                'date' => array('type' => self::TYPE_UINT,),

                'ts3_uniqueid' => array('type' => self::TYPE_STRING, 'maxLength' => 250, 'required' => true),

                'ts3_dbid' => array('type' => self::TYPE_UINT, 'required' => true),

                'message' => array('type' => self::TYPE_STRING, 'maxLength' => 250, 'default' => ''),

                'apikey' => array('type' => self::TYPE_STRING, 'maxLength' => 250, 'default' => ''),

                'apidata' => array('type' => self::TYPE_STRING, 'maxLength' => 8000, 'default' => ''),

                'extradata' => array('type' => self::TYPE_STRING, 'maxLength' => 8000, 'default' => '')
            )
        );
    }

    protected function _getExistingData($data)
    {
        if (!$logId = $this->_getExistingPrimaryKey($data, 'logid')) {
            return false;
        }
    }

    /**
     * Gets SQL condition to update the existing record.
     *
     * @return string
     */
    protected function _getUpdateCondition($tableName)
    {
        return 'logid = ' . $this->_db->quote($this->getExisting('logid'));
    }
}