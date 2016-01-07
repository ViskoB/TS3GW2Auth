<?php

class Moturdrn_TS3GW2Auth_Installer
{
    protected static $table = array(
        'xf_moturdrn_ts3gw2auth_auth' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `moturdrn_ts3gw2auth_auth` (
                  `ts3_uniqueid` varchar(250) NOT NULL,
                  `ts3_dbid` int(11) NOT NULL,
                  `gw2_name` varchar(250) DEFAULT NULL,
                  `gw2_account_guid` varchar(250) DEFAULT NULL,
                  `gw2_apikey` varchar(250) DEFAULT NULL,
                  `gw2_world` int(11) DEFAULT NULL,
                  `last_check` int(11) NOT NULL,
                  `sessionid` varchar(250) NOT NULL,
                  `keyname` varchar(250) NOT NULL,
                  PRIMARY KEY (`ts3_uniqueid`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_moturdrn_ts3gw2auth_auth`'
        ),
        'xf_moturdrn_ts3gw2auth_log' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `moturdrn_ts3gw2auth_log` (
                  `logid` int(11) NOT NULL AUTO_INCREMENT,
                  `date` int(11) NOT NULL,
                  `ts3_uniqueid` varchar(250) NOT NULL,
                  `ts3_dbid` int(11) NOT NULL,
                  `message` varchar(250) NOT NULL,
                  `apikey` varchar(250) NOT NULL,
                  `apidata` varchar(8000) NOT NULL,
                  `extradata` varchar(8000) NOT NULL,
                  PRIMARY KEY (`logid`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_moturdrn_ts3gw2auth_log`'
        ),
    );

    public static function install()
    {
        $db = XenForo_Application::get('db');
        $db->query(self::$table['xf_moturdrn_ts3gw2auth_auth']['createQuery']);
        $db->query(self::$table['xf_moturdrn_ts3gw2auth_log']['createQuery']);
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');
        $db->query(self::$table['xf_moturdrn_ts3gw2auth_auth']['dropQuery']);
        $db->query(self::$table['xf_moturdrn_ts3gw2auth_log']['dropQuery']);
    }
}