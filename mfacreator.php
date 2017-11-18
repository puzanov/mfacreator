<?php

define('MYSQL_HOST', '');
define('MYSQL_USER', '');
define('MYSQL_PASS', '');

$domain = getDomainFromCommandLine();

if ($domain === false) {
  showHelpAndExit();
}

$db_name = getDatabaseNameFromDomain($domain);

createDatabase($db_name);
createDirsAndCloneWP($domain);
createWPConfig($db_name, $domain);
createHtaccess($domain);
createVirtualHostAndRestartApache($domain);

function getDomainFromCommandLine() {
  global $argv;
  $domain = $argv[1];
  if (isDomainValid($domain)) {
    return $domain;
  }
  return false;
}

function isDomainValid($domain) {
  return filter_var(gethostbyname($domain), FILTER_VALIDATE_IP);
}

function showHelpAndExit() {
  echo "\nPlease enter valid domain in command line args\n\n";
  exit();
}

function getDatabaseNameFromDomain($domain) {
  return str_replace(array('.', '-'), '', $domain);
}

function createDatabase($db_name) {
  mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS);
  $result = mysql_query("create database {$db_name}");
  if (!$result) {
    echo mysql_error()."\n";
    exit();
  }
}

function createDirsAndCloneWP($domain) {
  system("
mkdir /var/www/mfa/{$domain} ;
cd /var/www/mfa/{$domain} ;
git clone https://github.com/WordPress/WordPress.git . ;
chmod -R 777 wp-content/
  ");
}

function createWPConfig($db_name, $domain) {
  $config_file = "/var/www/mfa/{$domain}/wp-config.php";
  $config_data = file_get_contents('wp-config.php');
  $salt = file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/');
  $config_data = str_replace(array('{DBNAME}', '{SALT}'), array($db_name, $salt), $config_data);
  file_put_contents($config_file, $config_data);
  system("chmod 777 $config_file");
}

function createHtaccess($domain) {
  system("sudo cp .htaccess /var/www/mfa/{$domain}/.htaccess");
  system("sudo chmod 777 /var/www/mfa/{$domain}/.htaccess");
}

function createVirtualHostAndRestartApache($domain) {
  $vh_file = "/etc/apache2/sites-available/$domain";
  $vh_data = file_get_contents('/var/www/mfa/mfacreator/virtualhost.conf');
  $vh_data = str_replace('{DOMAIN}', $domain, $vh_data);
  file_put_contents("/tmp/{$domain}", $vh_data);
  system("sudo mv /tmp/{$domain} $vh_file");
  system("sudo a2ensite $domain");
  system("sudo service apache2 restart");
}
