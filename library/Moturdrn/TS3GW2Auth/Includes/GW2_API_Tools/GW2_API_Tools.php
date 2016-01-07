<?php
function gw2_api_request($request, $APIKey = "")
{
    $url = parse_url('https://api.guildwars2.com' . $request);
    // open the socket
    if (!$fp = @fsockopen('ssl://' . $url['host'], 443, $errno, $errstr, 5)) {
        return 'connection error: ' . $errno . ', ' . $errstr;
    }
    // prepare the request header...
    $nl = "\r\n";
    $header = 'GET ' . $url['path'] . (isset($url['query']) ? '?' . $url['query'] : '') . ' HTTP/1.1' . $nl . 'Host: ' . $url['host'] . $nl;

    if ($APIKey != "")
        $header .= 'Authorization: Bearer ' . $APIKey . $nl;

    $header .= 'Connection: Close' . $nl . $nl;

    // ...and send it.
    fwrite($fp, $header);
    stream_set_timeout($fp, 5);

    // receive the response
    $response = '';
    do {
        if (strlen($in = fread($fp, 1024)) == 0) {
            break;
        }
        $response .= $in;
    } while (true);

    // now the nasty stuff... explode the response at the newlines
    $response = explode($nl, $response);

    return $response;
}

?>