PHP Shoutcast Server
Copyright (c) 2005 and onward sk89q <http://sk89q.therisenrealm.com>
Licensed under the GNU General Public License v3

Requirements
------------

* LAME
* PHP 5.0 or later
* Sockets

Introduction
------------

PHP Shoutcast Server is a proof-of-concept Shoutcast server written
in PHP. The project was started 18 June, 2005. It encodes tracks on
the fly using LAME and has a very basic in-built HTTP server.

Usage
-----

Open up config.php and change the path to point to a directory
containing MP3 files.

Download the LAME executable encoder and put it into the same directory.
xcLame.exe 3.92 was used during development.

Execute run.php through the command line.

To listen, connect to http://127.0.0.1:45400 with a compatible player.

Sample output:
Playing: The Verve - Bitter Sweet Symphony (Instrumental version).mp3

<Users Listening: 1>
* 127.0.0.1:59885        WinampMPEG/5.54

The HTTP server can be found at:
http://127.0.0.1:45401