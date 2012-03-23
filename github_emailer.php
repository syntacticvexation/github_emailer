#!/usr/bin/php

<?php
	include('config.php');

	function read_cache($fname) {
		return unserialize(file_get_contents("cache".DIRECTORY_SEPARATOR.$fname));
	}

	function write_cache($fname,$data) {
		$fh = fopen("cache".DIRECTORY_SEPARATOR.$fname, 'w');
		fwrite($fh,serialize($data));
		fclose($fh);
	}

	

	// main
	$json = file_get_contents("https://api.github.com/users/".GITHUB_USER."/repos");

	$repos = json_decode($json,true);

	$cache = read_cache(GITHUB_USER);

	print_r(array_diff($repos, $cache['repodata']));

	write_cache(GITHUB_USER,array('timestamp'=>time(),'repodata'=>$repos));

?>
