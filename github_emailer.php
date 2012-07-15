#!/usr/bin/php
<?php
	include('config.php');
	define('CACHE_DIR',__DIR__.DIRECTORY_SEPARATOR.'cache');

	function github_rpc($url) {
		$json = file_get_contents("https://api.github.com/".$url);
		return json_decode($json);
	}

	function read_cache($fname) {
		return unserialize(file_get_contents(CACHE_DIR.DIRECTORY_SEPARATOR.$fname));
	}

	function write_cache($fname,$data) {
		$fh = fopen(CACHE_DIR.DIRECTORY_SEPARATOR.$fname, 'w');
		$wrapped_data = array('timestamp'=>time(),'data'=>$data);
		fwrite($fh,serialize($wrapped_data));
		fclose($fh);
	}

	function compare_users($obj_a, $obj_b) {
		return $obj_a->id - $obj_b->id;
	}

	function user_login($user) {
		return $user->login;
	}

	function calculate_change($old,$repo,$change_type) {
		$old_repo = $old['data'][$repo->name];
		$old_repo_vars = get_object_vars($old_repo);
		$repo_vars = get_object_vars($repo);


		if ($old_repo_vars[$change_type] != $repo_vars[$change_type]) {
			$new_field = github_rpc("repos/".GITHUB_USER."/".$repo->name."/".$change_type);
			$old_field = read_cache(GITHUB_USER.'|'.$repo->name.'|'.$change_type);
			$old_field = $old_field['data'];

			$diff_field = array_udiff($new_field,$old_field,'compare_users');
			$field_logins = array_map('user_login', $diff_field);

			write_cache(GITHUB_USER.'|'.$repo->name.'|'.$change_type,$new_field);

			$change_string = substr($change_type,0,-1);

			return "  - went from ".$old_repo_vars[$change_type]." to ".$repo_vars[$change_type].
				" ".$change_string.($repo_vars[$change_type] == 1 ? '' : 's')." (".join(", ",$field_logins).")\n";
		}
	}

	function calculate_changes($old,$new) {
		$changes = array();

		foreach ($new as $repo) {
			$changes[$repo->name] = '';
			$changes[$repo->name] .= calculate_change($old,$repo,'watchers');
			$changes[$repo->name] .= calculate_change($old,$repo,'forks');

			if ($changes[$repo->name] == '') {
				unset($changes[$repo->name]);
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
			$msg .= "$key\n$change\n\n";
		}

		mail($to,$subject,$msg, $headers);
	}

	function main() {
		$repos = github_rpc("users/".GITHUB_USER."/repos");

		$cache = read_cache(GITHUB_USER);

		$changes = calculate_changes($cache,$repos);

		if (count($changes) > 0) {
			email_changes($changes);
		}

		// index data by repo name
		$indexed_data = array();

		foreach ($repos as $repo) {
			$indexed_data[$repo->name] = $repo;
		}

		write_cache(GITHUB_USER,$indexed_data);

	}

	main();
	exit();

?>
