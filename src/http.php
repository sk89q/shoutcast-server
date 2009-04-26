<?php // $Id$
/*
 * Copyright (c) sk89q <http://sk89q.therisenrealm.com>
 * Licensed under the GNU General Public License v3
*/

class HTTP
{	
    public $server_hook_id = 'HTTP';

    public $address = '0.0.0.0';
    public $port = 45401;
    
    public $shoutcast;
    public $server;
    
    public function hook_to_server() 
    {
        $this->server->hook_processor($this->server_hook_id, $this);
        
        $master = stream_socket_server("tcp://{$this->address}:{$this->port}", $err['errno'], $err['errstr']);
        
        $this->server->hook_master($this->server_hook_id, $master);
    }
    
    public function server_new_connection(&$new_client) 
    {            
        $this->server->listeners[] = $new_client;
        $this->server->clients[$new_client] = array(
            'processor' => $this->server_hook_id,
            'type' => 'child',
          );
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
        
        do {
            if (preg_match('`^([A-Za-z]+) (.*?) HTTP/1\.(0|1)$`', $request, $req)) {
                list(, $method, $path, $http_version) = $req;
                
                if (!in_array($method, array('POST', 'GET'))) {
                    fwrite($client, $this->build_response(405, array('allowed' => array('GET', 'POST'))));
                    break;
                }
                
                if ($path == '/') {
                    $songs_list = '';
                    
                    foreach($this->shoutcast->source->playlist as $track) {
                        $songs_list .= "<li>" . htmlspecialchars($track[0]) . "</li>";
                    }
                    
                    fwrite($client, $this->build_response(
                            200,
                            array(),
                            array(),
                            "<ul>$songs_list</ul>"
                          ));
                } elseif($path == '/dump') {
                    ob_start();
                    print_r($this->shoutcast);
                    $dump = ob_get_contents();
                    ob_end_clean();
                    
                    fwrite($client, $this->build_response(
                            200,
                            array(),
                            array(),
                            "<pre>$dump</pre>"
                          ));
                } elseif($path == '/nextsong') {
                    $this->shoutcast->source->open_next_file();
                    
                    fwrite($client, $this->build_response(
                            200,
                            array(),
                            array(),
                            "Done!"
                          ));
                } else {
                    fwrite($client, $this->build_response(404));
                }
            } else {
                fwrite($client, $this->build_response(400));
            }
        }
        while(false);
        
        fclose($client);
        $this->server->drop_client($client);
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
    
    private function build_response($code, $data = array(), $headers = array(), $content = '') 
    {
        $response = array('code' => $code);
        $wrap = false;
    
        switch($code) {
            case 200: {
                $response['name'] = 'OK';
                if (!$headers['Content-Type']) $headers['Content-Type'] = 'text/html';
                break;
            }
            case 400: {
                $response['name'] = 'Bad Request';
                $headers['Content-Type'] = 'text/html';
                $content = "<p>Your browser (or proxy) sent a request that this server could not understand.</p>";
                $wrap = true;
                break;
            }
            case 404: {
                $response['name'] = 'Bad Request';
                $headers['Content-Type'] = 'text/html';
                $content = "<p>The requested URL was not found on this server.</p>";
                
                if ($data['referer']) {
                    $data['referer'] = htmlspecialchars($data['referer']);
                    $content .= "<p>The link on the <a href=\"{$data['referer']}\">referring page</a> seems to be wrong or outdated. " .
                                "Please inform the author of<a href=\"{$data['referer']}\">that page</a> about the error.";
                }
                
                $content .= "<p>If you entered the URL manually please check your spelling and try again.</p>";
                $wrap = true;
                break;
            }
            case 405: {
                $response['name'] = 'Method Not Allowed';
                $headers['Content-Type'] = 'text/html';
                $headers['Allow'] = implode(', ', $data['allowed']);
                $content = "<p>The " . htmlspecialchars($data['method']) . " method is not allowed for the requested URL.</p>";
                $wrap = true;
                break;
            }
            default:
                return;
        }
        
        foreach($headers as $n => $v) {
            $headers_list .= "$n: $v\r\n";
        }
        
        return  "HTTP/1.0 {$response['code']} {$response['name']}\r\n" .
                $headers_list .
                "\r\n" .
                $content;
    }
}

