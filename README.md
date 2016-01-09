# [Moturdrn] TS3 GW2 Authentication
Verify the home world of players on TeamSpeak using the Guild Wars 2 API, and add to server groups

This repository includes code for
* The XenForo add-on

This add-on requires the use of a TeamSpeak 3 bot to automatically send messages to players on connection, if they're not a member of the verified group specified.

These messages should contain links to the following route: /TS3Auth?tsid=(client database id)

e.g. http://www.example.com/TS3Auth?tsid=4