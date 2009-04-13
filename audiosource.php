<?php // $Id$
/*
 * Copyright (c) sk89q <http://sk89q.therisenrealm.com>
 * Licensed under the GNU General Public License v3
*/

class AudioSource
{
    public $bitrate = 64;
    public $sample_rate = 44100;
    public $encoder = '';
    public $playlist = array();
    
    public $current_index = 0;
    
    private $media_process;
    private $media_pipes;
    
    private $initialized = false;
    private $sent_bytes = 0;
    private $audio_bytes_sent = 0;
    private $current_time = 0;
    
    public $new_track = false;
    public $track_header;
    public $track_frame_size;
    public $track_header_data = array();
    public $track_sample_rate;
    public $track_bitrate;
    
    public function populate_playlist($dir) 
    {
        global $CONFIG;
        
        if ($handle = @opendir($dir)) {
            while(false !== ($file = readdir($handle))) {
                $path = "{$dir}/{$file}";
        
                if (DIRECTORY_SEPARATOR == '\\') {
                    $path = str_replace('/', '\\', $path);
                } else {
                    $path = str_replace('\\', '/', $path);
                }
                
                if (!is_file($path)) continue;
                
                if (!preg_match('`\.mp3$`i', $path)) continue;
                
                $this->playlist[] = array(basename($path), $path);
            }
            closedir($handle); 
        }
    }
    
    public function open_next_file() 
    {
        if ($this->initialized) {
            fclose($this->media_pipes[0]);
            fclose($this->media_pipes[1]);
            $return_value = proc_close($this->media_process);
        }
        
        if (!$this->initialized) {
            $this->initialized = true;
            $this->current_index = rand(0, count($this->playlist));
        } elseif(count($this->playlist) - 1 == $this->current_index) {
            $this->current_index = 0;
        } else {
            $this->current_index++;
        }
        
        $path = $this->playlist[$this->current_index][1];
        
        echo "\nPlaying: {$this->playlist[$this->current_index][0]}";
        
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("file", "/temp.txt", "a")
        );
        
        $exec = $this->encoder . ' --silent -q 7 --cbr --resample ' . 
            $this->sample_rate . ' -B ' . $this->bitrate . ' -V 9 ' . 
            escapeshellarg($path) . ' -';
        
        $this->media_process = proc_open($exec, $descriptorspec, $this->media_pipes);
        
        $data = fread($this->media_pipes[1], 4);
        
        $this->new_track = true;
        $this->track_header = $data;
        
        $track_padding = (ord($data{2}) & 0x02) >> 1;
        $this->track_bitrate = $this->bitrate;
        //$this->track_bitrate = (ord($data{2}) & 0xF0) >> 4;
        $this->track_sample_rate = $this->sample_rate;
        //$this->track_sample_rate = (ord($data{2}) & 0x0C) >> 2;
        $this->track_frame_size = 144 * ($this->track_bitrate * 1000) / 
            ($this->track_sample_rate + $track_padding);
        
        return $this->data_chunk;
    }
    
    public function data_chunk() 
    {
        global $CONFIG;
            
        usleep(1000000 / 38);
        
        if (time() != $this->current_time) {
            $extra = $this->track_frame_size - $this->sent_bytes;
            $this->current_time = time();
            $this->sent_bytes = $extra;
        }
        
        if ($this->sent_bytes <= $this->track_frame_size * 38) {
            if (!feof($this->media_pipes[1])) {
                $bytes_to_send = $this->track_frame_size;
                $data = fread($this->media_pipes[1], $bytes_to_send);
                $this->sent_bytes += strlen($data);
                if ($this->new_track) {
                    $data = $this->track_header . $data;
                    $this->new_track = false;
                }
                return $data;
            } else {
                return $this->open_next_file();
            }
        }
    }
}

