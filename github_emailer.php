#!/usr/bin/php

<?php
	include('config.php');

	function read_cache($fname) {
		return unserialize(file_get_contents("cache".DIRECTORY_SEPARATOR.$fname));
	}

	function write_cache($fname,$data) {
		// index data by repo name
		$indexed_data = array();

		foreach ($data as $repo) {
			$indexed_data[$repo->name] = $repo;
		}

		$fh = fopen("cache".DIRECTORY_SEPARATOR.$fname, 'w');
		$wrapped_data = array('timestamp'=>time(),'repodata'=>$indexed_data);
		fwrite($fh,serialize($wrapped_data));
		fclose($fh);
	}

	function calculate_changes($old,$new) {
		print_r(array_keys($old['repodata']));

		$changes = array();

		foreach ($new as $repo) {
			if ($old['repodata'][$repo->name]->watchers != $repo->watchers) {
				$changes[$repo->name] = "went from ".$old['repodata'][$repo->name]->watchers." to ".$repo->watchers." watcher".($repo->watchers == 1 ? '' : 's');
			}
		}

		return $changes;
	}

	function email_changes($changes) {
		$to = EMAIL_ADDR;
		$subject = "[github Status Update for ".GITHUB_USER."] - ".count($changes)." change".(count($changes) == 1 ? '' : 's');
		$headers = '';
		$msg = '';

		foreach ($changes as $key=>$change) {
			$msg .= "$key\n - $change\n\n";
		}

		mail($to,$subject,$msg, $headers);
	}

	function main() {
		$json = file_get_contents("https://api.github.com/users/".GITHUB_USER."/repos");

		$repos = json_decode($json);

		$cache = read_cache(GITHUB_USER);

		$changes = calculate_changes($cache,$repos);

		print_r($changes);

		if (count($changes) > 0) {
			email_changes($changes);
		}

		write_cache(GITHUB_USER,$repos);
	}

	main();

?>
