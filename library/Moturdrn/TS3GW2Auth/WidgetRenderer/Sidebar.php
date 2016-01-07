<?php

class Moturdrn_TS3GW2Auth_WidgetRenderer_Sidebar extends WidgetFramework_WidgetRenderer
{
    protected function _getConfiguration()
    {
        return array(
            'name' => 'Teamspeak Viewer (Sidebar)',
            'useCache' => false,
            'useWrapper' => true
        );
    }

    protected function _getOptionsTemplate()
    {
        return false;
    }

    protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
    {

        $visitor = XenForo_Visitor::getInstance();

        if (XenForo_Permission::hasPermission($visitor['permissions'], 'moturdrn_gw2api', 'verified')) {
            require_once "library/Moturdrn/TS3GW2Auth/Includes/TeamSpeak3/ts3admin.class.php";
            $tsAdmin = new ts3admin(XenForo_Application::getOptions()->ts3gw2auth_ts3server, XenForo_Application::getOptions()->ts3gw2auth_ts3sqport);
            if ($tsAdmin->getElement('success', $tsAdmin->connect())) {
                $tsAdmin->login(XenForo_Application::getOptions()->ts3gw2auth_ts3username, XenForo_Application::getOptions()->ts3gw2auth_ts3password);
                $tsAdmin->selectServer(XenForo_Application::getOptions()->ts3gw2auth_ts3port);
            }

            $ts3server = array();
            $serverinfo = $tsAdmin->serverInfo();
            $ts3server['server_name'] = $serverinfo['data']['virtualserver_name'];
            $ts3server['connected_clients'] = $serverinfo['data']['virtualserver_clientsonline'];

            $channellist = $tsAdmin->channelList("-topic -flags -voice -limits -icon");
            $clientlist = $tsAdmin->clientList("-uid -away -voice -times -groups -info -country -icon -ip -badges");

            foreach ($channellist['data'] as $channelInfo) {
                $ts3server['channellist'][$channelInfo['cid']] = $channelInfo;
            }

            foreach ($clientlist['data'] as $clientInfo) {
                if ($clientInfo['client_type'] == 1) {
                    $this->subtractClient($ts3server['channellist'], $clientInfo['cid']);
                } else {
                    $ts3server['clientlist'][] = $clientInfo;
                }
            }

            $ts3server['channellist'] = $this->buildTree($ts3server['channellist']);

            $ts3server['clientlist'] = $this->array_orderby($ts3server['clientlist'], 'client_talk_power', SORT_DESC, 'client_nickname', SORT_ASC);

            $output = "";

            foreach ($ts3server['channellist'] as $channel) {
                $output .= $this->outputChannel($channel, $ts3server['clientlist']);
            }

            $tsOutput = <<<HTML
<b><a href="https://www.gunnars-hold.eu/resources/gunnars-hold-ts-information.10/" class='tschannellink'>* Connection & Server Details</a></b><br />
<b><a href='ts3server://ts3.gunnars-hold.eu/?port=9987' class='tschannellink'>* Or Click Here To Connect Now!</a></b><br /><br />
{$output}
HTML;
        } else {
            $tsOutput = 'You cannot view this item';
        }
        $renderTemplateObject->setParam('tsOutput', $tsOutput);
        return $renderTemplateObject->render();
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'Moturdrn_TS3GW2Auth_Widget';
    }

    public function outputChannel($channel, $clientlist)
    {
        $output = "";
        if ($channel['total_clients_family'] > 0) {
            $className = $channel['pid'] == 0 ? 'tsitemRoot' : 'tsitem';
            $output .= "<div class='{$className}'>";

            $passworded = $channel['channel_flag_password'] ? 'yellow' : 'green';

            $channFlags = "";

            $channFlags .= $channel['channel_flag_default'] ? "<img src='../styles/moturdrn/ts3gw2auth/16x16_default.png' />" : "";
            $channFlags .= $channel['channel_needed_talk_power'] > 0 ? "<img src='../styles/moturdrn/ts3gw2auth/16x16_moderated.png' />" : "";

            $channelName = htmlspecialchars($channel['channel_name']);
            $output .= "<a href='ts3server://ts3.gunnars-hold.eu/?port=9987&cid={$channel['cid']}' class='tschannellink'><img src='../styles/moturdrn/ts3gw2auth/16x16_channel_{$passworded}.png' /> {$channelName}<div class='tsFlags'>{$channFlags}</div></a>";

            if ($channel['total_clients'] > 0) {
                $clients = $this->search($clientlist, 'cid', $channel['cid']);
                foreach ($clients as $client) {
                    if ($client['client_type'] != 1) {
                        $clientNickname = htmlspecialchars($client['client_nickname']);
                        $talker = $client['client_flag_talking'] ? 'on' : 'off';
                        $output .= "<div class='tsitem'><img src='../styles/moturdrn/ts3gw2auth/16x16_player_{$talker}.png' /> {$clientNickname}</div>";
                    }
                }
            }

            if (isset($channel['children']) && $channel['total_clients'] < $channel['total_clients_family']) {
                foreach ($channel['children'] as $child) {
                    $output .= $this->outputChannel($child, $clientlist);
                }
            }
            $output .= "</div>";
        }
        return $output;
    }

    public function pprint($array)
    {
        echo "<pre>";
        print_r($array);
        echo "<pre>";
    }

    public function buildTree($ar, $pid = 0)
    {
        $op = array();
        foreach ($ar as $item) {
            if ($item['pid'] == $pid) {
                $op[] = $item;
                // using recursion
                $children = $this->buildTree($ar, $item['cid']);
                if ($children) {
                    $keyId = max(array_keys($op));
                    $op[$keyId]['children'] = $children;
                }
            }
        }
        return $op;
    }

    public function subtractClient(&$a, $cid)
    {
        $a[$cid]['total_clients']--;
        $a[$cid]['total_clients_family']--;
        if ($a[$cid]['pid'] != 0) {
            $this->subtractClient($a, $a[$cid]['pid']);
        }
    }

    public function search($array, $key, $value)
    {
        $results = array();

        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }

            foreach ($array as $subarray) {
                $results = array_merge($results, $this->search($subarray, $key, $value));
            }
        }

        return $results;
    }

    public function array_orderby()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();
                foreach ($data as $key => $row)
                    $tmp[$key] = $row[$field];
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }

}