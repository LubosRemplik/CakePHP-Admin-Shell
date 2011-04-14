<?php

class AdminShell extends Shell
{
	public $tasks = array('Backup');
	
	public function main()
	{
		$this->help();		
	}
	


/**
 * Displays help contents
 *
 * @access public
 */
	function help() {
		$help = <<<TEXT
The Admin Shell manage backups (SQL files)

Please create TMP/dumps folder, remote FTP folder and make them
writable.

Use and modify config.php.default

---------------------------------------------------------------
Usage: cake admin <command> <arg1> -<param1>...
---------------------------------------------------------------
Params:
	-ftp
		manage backups on remote FTP server

Commands:
	admin help
		shows this help message.

	admin backup <name>
		writes gziped SQL file to TMP/dumps/<name> file.
		argument <name> is optional.

Example:
	cake admin backup -ftp
TEXT;
		$this->out($help);
		$this->_stop();
	}
}