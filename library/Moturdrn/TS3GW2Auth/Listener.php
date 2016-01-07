<?php

class Moturdrn_TS3GW2Auth_Listener
{

    public static function WidgetFrameworkReady(&$renderers)
    {
        $renderers[] = "Moturdrn_TS3GW2Auth_WidgetRenderer_Sidebar";
    }

}