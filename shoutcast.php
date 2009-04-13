<?php // $Id$
/*
 * Copyright (c) sk89q <http://sk89q.therisenrealm.com>
 * Licensed under the GNU General Public License v3
*/

class Shoutcast
{	
    public $server_hook_id = 'SHOUTCAST';

    public $address = '0.0.0.0';
    public $port = 45400;
    
    public $server;
    public $source;
    
    public $metadata_interval = 8192;
    
    public $server_name;
    public $server_genre;
    public $server_url;
    public $server_public;
    
    public $note_string = 'This stream requires <a href="http://www.winamp.com/">Winamp</a>';
    private $server_string = 'PHPShoutcast/1.0';
    
    public function hook_to_server() 
    {
        $this->server->hook_processor($this->server_hook_id, $this);
        
        $err = array();
        $master = stream_socket_server("tcp://{$this->address}:{$this->port}", 
            $err['errno'], $err['errstr']);

        $this->server->hook_master($this->server_hook_id, $master);
    }
    
    public function server_interweave() 
    {            
        $media_data = $this->source->data_chunk();
        foreach($this->server->listeners as $listener) {
            if ($this->server->clients[$listener]['processor'] == $this->server_hook_id && 
                $this->server->clients[$listener]['type'] == 'child' &&
                $this->server->clients[$listener]['_i'] == '1') {                    
                /*if ($this->server->clients[$listener]['_audiodatasent'] + strlen($media_data) > $this->metadata_interval) {
                    $new_data = substr($media_data, 0, $this->metadata_interval - $this->server->clients[$listener]['_audiodatasent']);
                    @stream_socket_sendto($listener, $new_data);
                    
                    $metadata = $this->source->playlist[$this->source->current_index][0];
                    
                    if (strlen($metadata) % 16 == 0) {
                        $pad = 16;
                    } else {
                        $pad = 16 - (strlen($metadata) - (strlen($metadata) % 16));
                    }
                    
                    $metadata .= pack(str_repeat('x', $pad));
                    
                    $metadata = pack('c', strlen($metadata) / 16) . $metadata;
                    echo "\n" . strlen($metadata) . '-' . strlen($metadata) / 16 . '-' . $pad;
                    @stream_socket_sendto($listener, $pad);
                    
                    $new_data = substr($media_data, $this->metadata_interval - $this->server->clients[$listener]['_audiodatasent'] + 1);
                    $this->server->clients[$listener]['_audiodatasent'] = strlen($new_data);
                    @stream_socket_sendto($listener, $new_data);
                    unset($new_data);
                } else {*/
                    $this->server->clients[$listener]['_audiodatasent'] += strlen($media_data);
                    @fwrite($listener, $media_data);
                //}
            }
        }
    }
    
    public function server_new_connection(&$new_client) 
    {
        $this->server->listeners[] = $new_client;
        $this->server->clients[$new_client] = array(
            'processor' => $this->server_hook_id,
            'type' => 'child',
            '_i' => 0,
          );
        stream_set_blocking($new_client, 0);
    }
    
    public function server_data_in(&$client, $data) 
    {
        $data = str_replace("\r", "", $data);
        $lines = explode("\n", $data);
        
        $request = '';
        $headers = array();
        $body = '';
        
        $seen_request = false;
        $seen_headers = false;
        
        foreach($lines as $line) {
            $trimmed = trim($line);
            if (!$seen_request) {
                $request = $trimmed;
                $seen_request = true;
            } elseif(!$seen_headers && $trimmed == '') {
                $seen_headers = true;
            } elseif(!$seen_headers) {
                $header = explode(':', $trimmed, 2);
                $headers[strtolower($header[0])] = ltrim($header[1]);
            } else {
                $body .= $line;
            }
        }
        
        if (preg_match('`GET / HTTP/1\.(0|1)`', $request)) {
            $this->server->clients[$client]['_i'] = 1;
            $this->server->clients[$client]['_useragent'] = $headers['user-agent'];
            $this->server->clients[$client]['_metadata'] = intval($headers['icy-metadata']);
            $this->server->clients[$client]['_remote'] = stream_socket_get_name($client, true);
            $this->server->clients[$client]['_joined'] = time();
        
            $welcome = "ICY 200 OK\r\n";
            $welcome .= "Content-Type:audio/mpeg\r\n";
            $welcome .= "icy-notice1:" . $this->notice_string . "\r\n";
            $welcome .= "icy-notice2:" . $this->server_string . "\r\n";
            if ($this->server_name) $welcome .= "icy-name:{$this->server_name}\r\n";
            if ($this->server_genre) $welcome .= "icy-genre:{$this->server_genre}\r\n";
            if ($this->server_url) $welcome .= "icy-url:{$this->server_url}\r\n";
            $welcome .= "icy-pub:" . ($this->server_public ? 1 : 0) . "\r\n";
            $welcome .= "icy-br:" . $this->source->bitrate . "\r\n";
            //$welcome .= "icy-prebuffer:" . $this->source->bitrate . "\r\n";
            //$welcome .= "icy-metaint:8192\r\n";
            $welcome .= "\r\n";
            
            if (!$this->source->new_track) {
                $welcome .= $this->source->track_header;
            }
            
            @stream_socket_sendto($client, $welcome);
            
            $this->list_users();
        }
    }
    
    public function server_lost_connection(&$client) 
    {                
        foreach($this->server->listeners as $k => $l) if ($l == $client) unset($this->server->listeners[$k]);
        foreach($this->server->clients as $k => $l) if ($k == $client) unset($this->server->clients[$k]);
        @fclose($client);
        
        $this->list_users();
    }
    
    public function list_users() 
    {
        $count = 0;
        $list = '';
        
        foreach($this->server->listeners as $listener) {
            if ($this->server->clients[$listener]['processor'] == $this->server_hook_id && 
                $this->server->clients[$listener]['type'] == 'child' &&
                $this->server->clients[$listener]['_i'] == '1') {
                $client = $this->server->clients[$listener];
                
                $count++;
                $list .= "\n" . sprintf('* %-22s %s', $client['_remote'], $client['_useragent']);
            }
        }
    
        echo <<<EOB
\n\n\n
<Users Listening: {$count}>$list
EOB;
    }
}

