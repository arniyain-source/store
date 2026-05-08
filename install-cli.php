<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['run_install'] = '1';
$_POST['db_host'] = 'localhost;unix_socket=/run/mysqld/mysqld.sock';
$_POST['db_name'] = 'u602484543_desivastra';
$_POST['db_user'] = 'user';
$_POST['db_pass'] = '';
$_POST['admin_name'] = 'Super Admin';
$_POST['admin_email'] = 'admin@desivastra.in';
$_POST['admin_password'] = 'password';
$_POST['site_url'] = 'https://desivastra.in';

include 'install.php';
?>