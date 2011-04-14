<?php
App::import('Core', 'ConnectionManager');
App::import('Model', 'Ftp.Ftp');
class BackupTask extends Shell {
	
	var $nameTemplate = 'sql_shell_backup';
	var $cutoffDate = '-2weeks';
	var $Ftp = null;
	
	function execute() {
		$ftp = null;
		if(isset($this->params['ftp'])) $ftp = true;
		
		if($ftp) {
			// Connecting to FTP
			$this->Ftp = new Ftp();
			$connected = $this->Ftp->connect(Configure::read('Backup.Ftp'));
		}
			
		if($ftp) $this->cleanFtp();
		$this->clean();
		
		$version = date('Y-m-d');
		$folder = new Folder(TMP . 'dumps', true); 
		$c = ConnectionManager::getInstance()->config->default; 
		$this->filename = TMP . 'dumps'.DS.sprintf('%s_%s.sql.gz', $this->nameTemplate, $version);
		if(!empty($this->args[0])) $this->filename = TMP . 'dumps'.DS.$this->args[0];
		$this->out("Writing backup dump file to $this->filename"); 
		
		// If this is a manual request, ask to overwrite file, if it already exists
		if(!empty($_SERVER['USER'])) {
			if (file_exists($this->filename)) { 
			    if ($this->in('File exists, overwrite? [y/n]') !== 'y') { 
			        return; 
			    } 
			}
		}		
		$command = exec($c = "mysqldump -u {$c['login']} --password={$c['password']} -h {$c['host']} {$c['database']} | gzip > $this->filename"); 
		if (!file_exists($this->filename)) { 
		    $this->out("Couldn't create backup, aborting."); 
		    $this->_stop(); 
		}
		
		if($ftp) {
			// Uploading file to FTP		
			$ftp_filename = Configure::read('Backup.Ftp.path').sprintf('%s_%s.sql.gz', $this->nameTemplate, $version);	
			if(!empty($this->args[0])) $ftp_filename = Configure::read('Backup.Ftp.path').$this->args[0];
			try {
				$this->Ftp->create();
			    if ($this->Ftp->save(array(
			        'local' => $this->filename,
			        'remote' => $ftp_filename
			    ))) {
			    	$this->out(sprintf("Uploading backup dump file to ftp://%s%s", Configure::read('Backup.Ftp.host'), $ftp_filename));
			    } else {
			    	$this->out(sprintf("Couldn't upload backup to ftp://%s%s.", Configure::read('Backup.Ftp.host'), $ftp_filename));
			    }		   
			} catch (Exception $e) {
				debug($e->getMessage());
			}	
		}
	}
	
	function clean() {
		$this->out('Local housekeeping, removing outdated backups');
		$cutoffTime = strtotime($this->cutoffDate);
		$files = $this->getDirectoryList(TMP.'dumps'.DS);
		if(count($files)) {
			foreach($files as $file) {
				$modificationTime = $this->getModificationTime($file);
				if($modificationTime) {
					if($cutoffTime > $modificationTime) {
						$this->out(sprintf('Remove old backup: %s', $file));
						unlink(TMP.'dumps'.DS.$file);						
					} else {
						$this->out(sprintf('Keeping backup: %s', $file));
					}
				}
			}
		}
	}
	
	function cleanFtp() {
		$this->out('FTP housekeeping, removing outdated backups');
		$cutoffTime = strtotime($this->cutoffDate);
		$files = $this->getFtpList(Configure::read('Backup.Ftp.path'));
		if(count($files)) {
			foreach($files as $file) {
				$modificationTime = strtotime($file['Ftp']['mtime']);
				if($modificationTime) {
					if($cutoffTime > $modificationTime) {
						$path = $file['Ftp']['path'].$file['Ftp']['filename'];
						try {
						    if ($this->Ftp->delete($path, false)) {
								$this->out(sprintf('Remove old backup: %s', $path));
						    }
						} catch (Exception $e) {
						    debug($e->getMessage());
						}
					} else {
						$this->out(sprintf('Keeping backup: %s', $file['Ftp']['filename']));
					}
				}
			}
		}
	}
	
	function getDirectoryList($directory) {
		$results = array();
		
		if(is_dir($directory)) {
			$handler = opendir($directory);
			
			// open directory and walk through the filenames
			while ($file = readdir($handler)) {
				// if file isn't this directory or its parent, add it to the results
				if ($file != "." && $file != "..") {
					$results[] = $file;
				}
			}
			closedir($handler);
		}
		return $results;
	}
	
	function getFtpList($path = null) {
		if(!$path) return false;
		
		$results = array();
		
		try {
		    $files = $this->Ftp->find('all', array('conditions' => array('path' => $path)));
		    foreach ($files as $file) {
				// if file isn't this directory or its parent, add it to the results
				if ($file['Ftp']['filename'] != "." && $file['Ftp']['filename'] != "..") {
					$results[] = $file;
				}
		    }
		} catch (Exception $e) {
		    debug($e->getMessage());
		}
		
		return $results;
	}
	
	function getModificationTime($file) {
		$file = TMP.'dumps'.DS.$file;
		if(file_exists($file)) {
			return filemtime($file);
		}
		return false;
	}
	
}	
?>