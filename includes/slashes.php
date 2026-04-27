<?php
function addslashes_form_to_sql($chaine)
{
	return (get_magic_quotes_gpc() == 1) ?
		$chaine :
		addslashes($chaine);
}

function stripslashes_form_to_scr($chaine)
{
	return (get_magic_quotes_gpc() == 1) ?
		stripslashes($chaine) :
		$chaine;
}

function addslashes_scr_to_bdd($chaine)
{
	return (get_magic_quotes_runtime() == 1) ?
		$chaine :
		addslashes($chaine);
}

function stripslashes_bdd_to_scr($chaine)
{
	return (get_magic_quotes_runtime() == 1) ?
		stripslashes($chaine) :
		$chaine;
}

function form_to_form($chaine)
{
	return (get_magic_quotes_gpc() == 1) ?
		preg_replace("/'/", "&#39;", stripslashes($chaine)) :
		preg_replace("/'/", "&#39;", $chaine);
}

function sql_to_form($txt)
{
	return preg_replace("/'/", "&#39;", stripslashes_bdd_to_scr($txt));
}
