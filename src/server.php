<?php // $Id$
/*
 * Copyright (c) sk89q <http://sk89q.therisenrealm.com>
 * Licensed under the GNU General Public License v3
*/

class Server
{
	public $clients;
	
	private $master_hooks = array();
	private $processor_hooks = array();
	
	public $listeners;
	
	public function hook_master($id, &$socket) 
	{
		$this->listeners[] = $socket;
		$this->master_hooks[] = $socket;

		$this->clients[$socket] = array(
			'processor' => $id,
			'type' => 'listener',
		  );
	}
	
	public function hook_processor($id, &$class) 
	{
		$this->processor_hooks[$id] = &$class;
	}
	
	public function listen() 
	{
		while(true) {
			foreach($this->processor_hooks as $proc) {
				if (method_exists($proc, 'server_interweave')) {
					$proc->server_interweave();
				}
			}
			
			$read = $this->listeners;
			
			$selected = stream_select($read, $_w = NULL, $_e = NULL, 0);
			
			if ($selected === false) break; // Could not select!
			
			for ($cid = 0; $cid < $selected; $cid++) {
				$client = &$read[$cid];
				$metadata = &$this->clients[$client];
				
				/*
				* New connection
				*/
				foreach($this->master_hooks as $master) {
					if ($client === $master) {                            
						$new_client = stream_socket_accept($master);
						
						$proc = $this->processor_hooks[$this->clients[$master]['processor']];
						
						if (method_exists($proc, 'server_new_connection')) {
							$proc->server_new_connection($new_client);
						} else {
							fclose($new_client);
						}
						
						continue 2;
					}
				}
				
				$data = fread($client, 1024);		               
				/*
				* Lost Connection
				*/
				if (strlen($data) === 0 || $data === false) { // connection closed
					$proc = $this->processor_hooks[$this->clients[$client]['processor']];
					
					if (method_exists($proc, 'server_lost_connection')) {
						$proc->server_lost_connection($client);
					} else {
						foreach($this->listeners as $k => $l) if ($l == $client) unset($this->listeners[$k]);
						unset($metadata);
						@fclose($client);
					}
					
					continue;
				}
				
				/*
				* Data in
				*/
				$proc = $this->processor_hooks[$this->clients[$client]['processor']];
				
				if (method_exists($proc, 'server_data_in')) {
					$proc->server_data_in($client, $data);
				}
			}
		}
	}

	public function drop_client(&$socket) 
	{			
		foreach($this->listeners as $k => $l) if ($l == $socket) unset($this->listeners[$k]);
		unset($this->clients[$socket]);
		@fclose($socket);
	}
}

