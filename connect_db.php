<?php 
if ( !function_exists('connect_db') )
{
function connect_db()
{
	global $mysqli;	
	if (( $mysqli = mysqli_connect("db", "root", "root")) == FALSE) return FALSE;
	$mysqli->set_charset("utf8");
	if (mysqli_select_db($mysqli,"live") == FALSE)
		return FALSE;
	return $mysqli;
}
}

?>