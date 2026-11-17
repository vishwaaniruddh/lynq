<?php
/**
 * Root login redirect/alias
 */
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /views/auth/login.php' . $query);
exit;

