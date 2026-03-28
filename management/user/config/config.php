<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['index_page'] = '';
$config['site_license_key'] = '';
// ExpressionEngine Config Items
// Find more configs and overrides at
// https://docs.expressionengine.com/latest/general/system-configuration-overrides.html

$env = parse_ini_file(file_exists('/var/local/.env') ? '/var/local/.env' : '.env');

$config['app_version'] = '7.5.12';
$config['encryption_key'] = 'b25e985dbeaf589e2db1f065fe78b2a3661cbb83';
$config['session_crypt_key'] = 'bad891b8da5678152d1cd44b439a92b6c749da5a';
$config['database'] = array(
	'expressionengine' => array(
		'hostname' => $env["DB_HOSTNAME"],
		'database' => $env["DB_DATABASE"],
		'username' => $env["DB_USERNAME"],
		'password' => $env["DB_PASSWORD"],
		'dbprefix' => 'exp_',
		'char_set' => 'utf8mb4',
		'dbcollat' => 'utf8mb4_unicode_ci',
		'port'     => ''
	),
);
$config['show_ee_news'] = 'y';

// EOF