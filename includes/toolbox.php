<?php 

// require_once('connect_db.php');
// connect_db();
define("EXTENSION","doc|docx|xls|rtf|ppt|pdf|swf|flv|jpg|jpeg|gif|png|tiff|tif|bmp");
$allowedExtensions = array("doc","docx","xls","rtf","ppt","pdf","swf","flv","jpg","jpeg","gif","png","tiff","tif","bmp");

function multi_certificat_medical($idTypeEpreuve,$idInternaute,$files,$cursor = 0)
{
	$retour = "";
	$nomfichier = "";
	global $allowedExtensions;
	global $mysqli;

	if(is_uploaded_file($files['tmp_name'][$cursor]))
	{
		if(!in_array(end(explode(".", strtolower($files['name'][$cursor]))), $allowedExtensions))
		{
			$retour = "Le fichier que vous avez tenté d'insérer n'est pas valide,<br />merci de nous le transmettre  <a href=\"mailto:contact@ats-sport.com?subject=Mon certificat en pièce jointe&body=Merci de nous indiquer ici, vos noms & prénoms\">par mail</a>.<br />";
		}
		elseif($files['size'][$cursor] < 2000000)
		{	 
			if($idTypeEpreuve == 1)
			{
				$nomfichier = "certif_tri".'_'.$idInternaute.'_'.rand(1000, 9999).".".pathinfo($files['name'][$cursor], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'][$cursor], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_tri='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<img src='images/valide_.gif' width='12'/>Le certificat médical du coureur n°".($cursor+1)." a bien été transféré";
				}
			}			
			else if($idTypeEpreuve == 2)
			{
				$nomfichier = "certif_vel".'_'.$idInternaute.'_'.rand(1000, 9999).".".pathinfo($files['name'][$cursor], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'][$cursor], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_vel='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<img src='images/valide_.gif'>Félicitations!! <br /><br />Le fichier " .$files['name'][$cursor]." a bien été transféré";
				}
			}			
			else if($idTypeEpreuve == 3)
			{
				$nomfichier = "certif_cap".'_'.$idInternaute.'_'.rand(1000, 9999).".".pathinfo($files['name'][$cursor], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'][$cursor], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_cap='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<img src='images/valide_.gif'>Félicitations!! <br /><br />Le fichier " .$files['name'][$cursor]." a bien été transféré";
				}
			}
		}
		else
		{
			$retour = "Le fichier que vous avez tenté d'insérer n'est pas valide,<br />il est trop volumineux, sa taille doit être inférieure à 2Mo";
		}
		echo "oui";
	}else{
		echo "non";
	}
	// die();
	return $retour;
}

function certificat_medical($idTypeEpreuve,$idInternaute,$files)
{
	$retour = "";
	$nomfichier = "";
	global $allowedExtensions;
	global $mysqli;
	
	if(isset($files['tmp_name']))
	{
		if(!in_array(end(explode(".", strtolower($files['name']))), $allowedExtensions))
		{
			$retour = "<p class='txtLibre'>Le fichier que vous avez tenté d'insérer n'est pas valide,<br />merci de nous le transmettre  <a href=\"mailto:contact@ats-sport.com?subject=Mon certificat en pièce jointe&body=Merci de nous indiquer ici, vos noms & prénoms\">par mail</a></p><br />";
		}
		elseif($files['size'] < 2000000)
		{	 
			if($idTypeEpreuve == 1) //Triathlon
			{
				$nomfichier = "certif_tri_".date("Y-m")."_".$idInternaute."_".rand(1000, 9999).".".pathinfo($files['name'], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_tri='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<p class='txtLibre'><img src='images/valide_.gif' width='8'/>Le fichier " .$files['name']." a bien été transféré</p>";
				}
			}			
			else if($idTypeEpreuve == 2) // Vélo
			{
				$nomfichier = "certif_vel_".date("Y-m")."_".$idInternaute.'_'.rand(1000, 9999).".".pathinfo($files['name'], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_vel='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<p class='txtLibre'><img src='images/valide_.gif' width='8'>Félicitations!! <br /><br />Le fichier " .$files['name']." a bien été transféré</p>";
				}
			}			
			else if($idTypeEpreuve == 3) //Course à pied
			{
				$nomfichier = "certif_cap_".date("Y-m")."_".$idInternaute.'_'.rand(1000, 9999).".".pathinfo($files['name'], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_cap='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<p class='txtLibre'><img src='images/valide_.gif' width='8'>Félicitations!! <br /><br />Le fichier " .$files['name']." a bien été transféré</p>";
				}
			}			
			else if($idTypeEpreuve == 4) //Ski de fond
			{
				$nomfichier = "certif_ski_".date("Y-m")."_".$idInternaute.'_'.rand(1000, 9999).".".pathinfo($files['name'], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_ski='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<p class='txtLibre'><img src='images/valide_.gif' width='8'>Félicitations!! <br /><br />Le fichier " .$files['name']." a bien été transféré</p>";
				}
			}
		}
		else
		{
			$retour = "Le fichier que vous avez tenté d'insérer n'est pas valide,<br />il est trop volumineux, sa taille doit être inférieure à 2 Mo (2000 Ko)";
		}
	}
	return $retour;
}

function certificat_medical_coureur($idTypeEpreuve,$idInternaute,$files,$post)
{
	$retour = "";
	$nomfichier = "";
	global $allowedExtensions;
	global $mysqli;
	
	if(isset($files['tmp_name']))
	{
		if(!in_array(end(explode(".", strtolower($files['name']))), $allowedExtensions))
		{
			$retour = "Le fichier que vous avez tenté d'insérer n'est pas valide,<br />merci de nous le transmettre  <a href=\"mailto:contact@ats-sport.com?subject=Mon certificat en pièce jointe&body=Merci de nous indiquer ici, vos noms & prénoms\">par mail</a>.<br />";
		}
		elseif($files['size'] < 2000000)
		{	 
			if($idTypeEpreuve == 1) //Triathlon
			{
				$query="SELECT fichier_tri FROM r_internaute WHERE idInternaute=".$idInternaute." AND fichier_tri IS NOT NULL" ;
				$result = $mysqli->query($query);
				if(mysqli_num_rows($result)==1)  
				{
					$row=mysqli_fetch_array($result);
					if(file_exists('cert/'.$row['fichier_tri'])) unlink('cert/'.$row['fichier_tri']);
					$mysqli->query("UPDATE r_internaute SET fichier_tri=NULL WHERE idInternaute=".$idInternaute);
				}
				$nomfichier = "certif_tri_".date("Y-m")."_".$idInternaute."_".rand(1000, 9999).".".pathinfo($files['name'], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_tri='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<img src='images/valide_.gif' width='12'/>Félicitations!!<br />Le fichier " .$files['name']." a bien été transféré<br /><br />";
					$query  = "UPDATE r_internaute SET ";
					$query .= "peremption_tri='".intval($post['tri_annee'])."-";
					$query .= intval($post['tri_mois'])."-";
					$query .= intval($post['tri_jour'])."'";
					$query .= " WHERE idInternaute=".$idInternaute;
					$mysqli->query($query);
					$mysqli->query("UPDATE r_inscriptionepreuveinternaute, r_epreuve SET new='oui' WHERE r_inscriptionepreuveinternaute.idInternaute=".$idInternaute." 
								 AND r_epreuve.idEpreuve = r_inscriptionepreuveinternaute.idEpreuve AND r_epreuve.idTypeEpreuve = 1 AND r_epreuve.dateEpreuve > NOW()");
				}
			}			
			else if($idTypeEpreuve == 2) // Vélo
			{
				$query="SELECT fichier_vel FROM r_internaute WHERE idInternaute=".$idInternaute." AND fichier_vel IS NOT NULL" ;
				$result = $mysqli->query($query);
				if(mysqli_num_rows($result)==1)
				{
					$row=mysqli_fetch_assoc($result);
					if(file_exists('cert/'.$row['fichier_vel'])) unlink('cert/'.$row['fichier_vel']);
					$mysqli->query("UPDATE r_internaute SET fichier_vel=NULL WHERE idInternaute=".$idInternaute);
				}
				$nomfichier = "certif_vel_".date("Y-m")."_".$idInternaute.'_'.rand(1000, 9999).".".pathinfo($files['name'], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_vel='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<img src='images/valide_.gif' width='12'>Félicitations!!<br />Le fichier " .$files['name']." a bien été transféré<br /><br />";
					$query  = "UPDATE r_internaute SET ";
					$query .= "peremption_vel='".intval($post['vel_annee'])."-";
					$query .= intval($post['vel_mois'])."-";
					$query .= intval($post['vel_jour'])."'";
					$query .= " WHERE idInternaute=".$idInternaute;
					$mysqli->query($query);
					$mysqli->query("UPDATE r_inscriptionepreuveinternaute, r_epreuve SET new='oui' WHERE r_inscriptionepreuveinternaute.idInternaute=".$idInternaute." 
								 AND r_epreuve.idEpreuve = r_inscriptionepreuveinternaute.idEpreuve AND r_epreuve.idTypeEpreuve = 2 AND r_epreuve.dateEpreuve > NOW()");
				}
			}			
			else if($idTypeEpreuve == 3) //Course à pied
			{
				$query="SELECT fichier_cap FROM r_internaute WHERE idInternaute=".$idInternaute." AND fichier_cap IS NOT NULL" ;
				$result = $mysqli->query($query);
				if(mysqli_num_rows($result)==1)
				{
					$row=mysqli_fetch_array($result);
					if(file_exists('cert/'.$row['fichier_cap'])) unlink('cert/'.$row['fichier_cap']);
					$mysqli->query("UPDATE r_internaute SET fichier_cap=NULL WHERE idInternaute=".$idInternaute);
				}
				$nomfichier = "certif_cap_".date("Y-m")."_".$idInternaute.'_'.rand(1000, 9999).".".pathinfo($files['name'], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_cap='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<img src='images/valide_.gif' width='12'>Félicitations!!<br />Le fichier " .$files['name']." a bien été transféré<br /><br />";
					$query  = "UPDATE r_internaute SET ";
					$query .= "peremption_cap='".intval($post['cap_annee'])."-";
					$query .= intval($post['cap_mois'])."-";
					$query .= intval($post['cap_jour'])."'";
					$query .= " WHERE idInternaute=".$idInternaute;
					$mysqli->query($query);
					$query=("UPDATE r_inscriptionepreuveinternaute, r_epreuve SET new='oui' WHERE 
					r_inscriptionepreuveinternaute.idEpreuve <> 3284 AND r_inscriptionepreuveinternaute.idInternaute=".$idInternaute." 
					AND r_epreuve.idEpreuve = r_inscriptionepreuveinternaute.idEpreuve AND r_epreuve.idTypeEpreuve = 3 AND r_epreuve.dateEpreuve > NOW()");
					$mysqli->query($query);
				}
			}			
			else if($idTypeEpreuve == 4) //Ski de fond
			{
				$query="SELECT fichier_ski FROM r_internaute WHERE idInternaute=".$idInternaute." AND fichier_ski IS NOT NULL" ;
				$result = $mysqli->query($query);
				if(mysqli_num_rows($result)==1)
				{
					$row=mysqli_fetch_array($result);
					if(file_exists('cert/'.$row['fichier_ski'])) unlink('cert/'.$row['fichier_ski']);
					$mysqli->query("UPDATE r_internaute SET fichier_ski=NULL WHERE idInternaute=".$idInternaute);
				}
				$nomfichier = "certif_ski_".date("Y-m")."_".$idInternaute.'_'.rand(1000, 9999).".".pathinfo($files['name'], PATHINFO_EXTENSION);
				if(move_uploaded_file($files['tmp_name'], 'cert/'.$nomfichier))
				{
					$mysqli->query("UPDATE r_internaute SET fichier_ski='$nomfichier' WHERE idInternaute=$idInternaute");
					$retour = "<img src='images/valide_.gif' width='12'>Félicitations!!<br />Le fichier " .$files['name']." a bien été transféré<br /><br />";
					$query  = "UPDATE r_internaute SET ";
					$query .= "peremption_ski='".intval($post['ski_annee'])."-";
					$query .= intval($post['ski_mois'])."-";
					$query .= intval($post['ski_jour'])."'";
					$query .= " WHERE idInternaute=".$idInternaute;
					$mysqli->query($query);
				}
			}
		}
		else
		{
			$retour = "Le fichier que vous avez tenté d'insérer n'est pas valide,<br />il est trop volumineux, sa taille doit être inférieure à 2 Mo (2000 Ko)";
		}
	}
	return $retour;
}
?>