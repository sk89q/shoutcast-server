<?php // $Id$
/*
 * Copyright (c) sk89q <http://sk89q.therisenrealm.com>
 * Licensed under the GNU General Public License v3
*/

require 'config.php';

require 'server.php';
require 'shoutcast.php';
require 'audiosource.php';
require 'http.php';

set_time_limit(0);

$Server = new Server();

$AudioSource = new AudioSource();
$AudioSource->bitrate = $CONFIG['bitrate'];
$AudioSource->encoder = $CONFIG['encoder_path'];
$AudioSource->metadata_interval = $CONFIG['metadata_interval'];
$AudioSource->populate_playlist($CONFIG['music_dir']);
$AudioSource->open_next_file();

$Shoutcast = new Shoutcast();
$Shoutcast->server = &$Server;
$Shoutcast->source = &$AudioSource;
$Shoutcast->metadata_interval = $CONFIG['metadata_interval'];
$Shoutcast->server_name = $CONFIG['server_name'];
$Shoutcast->server_genre = $CONFIG['server_genre'];
$Shoutcast->server_url = $CONFIG['server_url'];
$Shoutcast->server_public = $CONFIG['server_public'];
$Shoutcast->hook_to_server();

$HTTP = new HTTP();
$HTTP->server = &$Server;
$HTTP->shoutcast = &$Shoutcast;
$HTTP->hook_to_server();

$Server->listen();