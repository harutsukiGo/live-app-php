<?php

// ini_set("display_errors", 1);
// error_reporting(E_ALL);
// ini_set("display_startup_errors", 1);
require_once("includes/includes.php");
require_once("includes/functions.php");

$idEpreuve = $_GET['idEpreuve'];
$idLecteur = $_GET['idLecteur'];
$lieu = $_GET['lieu'];
$admin = 0;

if ($_SESSION["typeInternaute"] == 'admin' || $_SESSION["typeInternaute"] == 'super_organisateur') {
    $admin = 1;
}
global $mysqli;

function getflag($pays)
{
    global $mysqli;

    $qflag  = "SELECT PAYSCODE, PAYSNAME FROM tpays WHERE PAYSNAME LIKE LOWER(TRIM('%" . $pays . "%'))";
    $result = $mysqli->query($qflag);


    if ($row = $result->fetch_assoc()) {
        $pays = substr(strtoupper($row["PAYSNAME"]), 0, 3);
        $flag = "<img src='../images/flag/" . $row["PAYSCODE"] . ".png'>";
        // $flag = $pays . "-<img src='../images/flag/" . $row["PAYSCODE"] . ".png'>";
    } else {

        $flag = "<img src='../images/flag/fr.png'>";
    }

    return $flag;
}

//requete pour récupérer les coureurs dans la BDD a chaque appel de la fonction Jquery AJAX majTableau
$query = 'SELECT iei.idInscriptionEpreuveInternaute, iei.idInternaute, iei.idEpreuveParcours, iei.dossard, iei.categorie, iei.equipe, horaire AS horaire,
    ri.nomInternaute, ri.prenomInternaute, ri.sexeInternaute, ri.clubInternaute, ri.villeInternaute, ri.paysInternaute,
    ep.nomParcours, ep.idEpreuveParcours, ep.horaireDepart, liv.id, liv.idInscription, liv.passage 
    FROM r_inscriptionepreuveinternaute iei
    INNER JOIN r_internaute ri ON iei.idInternaute = ri.idInternaute
    INNER JOIN live_Horaire liv ON liv.idInscription= iei.idInscriptionEpreuveInternaute
    INNER JOIN live_Lecteur cl ON liv.idLecteur = cl.idLecteur
    INNER JOIN r_epreuveparcours ep ON iei.idEpreuveParcours = ep.idEpreuveParcours
    WHERE liv.idLecteur = ?
    AND ep.idEpreuve = ? 
    AND cl.idEpreuve = ? 
    AND liv.passage <= cl.nb_passage
    -- AND ep.horaireDepart < liv.horaire
    AND cl.date_min < liv.horaire
    AND cl.date_max > liv.horaire
    ORDER BY liv.passage DESC, liv.horaire ASC';

$stmt = $mysqli->prepare($query);
$stmt->bind_param("iii", $idLecteur, $idEpreuve, $idEpreuve);
$stmt->execute();
$result = $stmt->get_result();
$classement  = mysqli_fetch_all($result, MYSQLI_ASSOC);
//  echo "<br><br><br>".$query;

?>
<thead class="bg-info">
    <tr>
    <tr>
        <th scope="col" style="text-align: center">
            Pl.
        </th>
        <th scope="col" style="text-align: center">
            Nom / Name
        </th>
        <th scope="col" style="text-align: center">
            Club / Team
        </th>
        <th scope="col" style="text-align: center">
            Parc / Race
        </th>
        <th scope="col" style="text-align: center">
            Horaire / Time
        </th>
        <?php
        if ($admin)
            echo "<th text-align:'center'>Action</th>"
        ?>
    </tr>
    </tr>
</thead>
<tbody id="<?php echo $lieu; ?>">
    <?php
    //Retourne le nombre de parcours
    $listeEpreuve = listeDeCoupleParcoursIndex($idEpreuve);
    $tabdossard = array();
    //On met à jour le nombre de passage par rapport au critères de la course
    $query2 = "SELECT id, dossard, passage FROM live_Horaire liv
                            INNER JOIN live_Lecteur cl ON liv.idLecteur=cl.idLecteur
                            WHERE liv.idLecteur = " . $idLecteur . " AND liv.idEpreuve=" . $idEpreuve . " AND liv.passage < cl.nb_passage AND cl.date_min < liv.horaire AND cl.date_max > liv.horaire
                            ORDER BY dossard, horaire ";
    $result2 = $mysqli->query($query2) or die("Sql error : " . mysqli_error($mysqli));
    $passage = 1;
    $dossard = 0;
    while ($row = mysqli_fetch_assoc($result2)) {
        //   echo $row['dossard']."=". $dossard."</br>";                  
        if ($row['dossard'] == $dossard) {
            $passage = $passage + 1;
        } else {
            $passage = 1;
            $dossard = $row['dossard'];
        }
        $query2 = "UPDATE live_Horaire SET passage = " . $passage . " WHERE id = " . $row['id'];
        $result2 = $mysqli->query($query2) or die("Sql error : " . mysqli_error($mysqli));
    }


    foreach ($classement as $placeClassement) {
        //On scinde le nom du parcours
        $pparcours = explode("-", $placeClassement['nomParcours']);
        $parcours = $pparcours['0'] . "<br>" . $pparcours['1'];
        $nbre = 1;
        $idParcours_test = $placeClassement['idEpreuveParcours'];
        if (!in_array($placeClassement['dossard'], $tabdossard)) {
            for ($i = 0; $i < count($listeEpreuve); $i++) {
                if ($idParcours_test == $listeEpreuve[$i][0]) {
                    $listeEpreuve[$i][1] = $listeEpreuve[$i][1] + 1;
                    $nbre = $listeEpreuve[$i][1];
                }
            }
            array_push($tabdossard, $placeClassement['dossard']);
        } else $nbre = "-";
        $horaire = date('H:i:s', strtotime($placeClassement['horaire']));

        $temps = calculTemps($placeClassement['horaire'], $placeClassement['horaireDepart']);

        //on cré les variables ici pour l'affichage des équipes
        if ($placeClassement['equipe'] != "Aucune") {
            $qequipe  = "SELECT nomInternaute, prenomInternaute, sexeInternaute, paysInternaute FROM r_internaute ri 
                                                            INNER JOIN r_inscriptionepreuveinternaute iei ON ri.idInternaute = iei.idInternaute
                                                              WHERE iei.equipe LIKE '" . addslashes($placeClassement['equipe']) . "' AND iei.idEpreuve = " . $idEpreuve . "";
            $result2 = $mysqli->query($qequipe) or die("Sql error : " . mysqli_error());

            $i = 0;
            $club = "";
            $nb_femme = 0;
            $nb_homme = 0;
            while ($row = mysqli_fetch_assoc($result2)) {
                $club .= (($i > 0) ? "</br>" : "");
                $club .= getflag($row['paysInternaute']) . " <span style=" . (($row['sexeInternaute'] == "F") ? 'font-style:italic;' : '') . ">" . $row['prenomInternaute'] . " " . $row['nomInternaute'] . " - " . (($placeClassement['sexeInternaute'] == "M") ? "<i class='fa fa-male' ;></i>" : "<i class='fa fa-female' style='color:#f50666;'></i>") . " | " . $placeClassement['categorie'] . "</span>";
                if ($row['sexeInternaute'] == "F") $nb_femme = $nb_femme + 1;
                if ($row['sexeInternaute'] == "M") $nb_homme = $nb_homme + 1;
                $i++;
            }
            $nom = "<b>Equipe " . $placeClassement['equipe'] . "</b></br>" . $nb_femme . " <i class='fa fa-female' style='color:#f50666;'></i> | " . $nb_homme . " <i class='fa fa-male'></i>  (" . $placeClassement['dossard'] . ")";
            $club .= "";
            $cat .= "";
        }
        //on cré les variables ici pour l'affichage des solos
        else {
            $club = "" . $placeClassement['clubInternaute'] . "</br>" . $placeClassement['villeInternaute'] . "";
            $cat = "<span id='cat'>" . getflag($placeClassement['paysInternaute']) . "&nbsp;&nbsp;<b>" . (($placeClassement['sexeInternaute'] == "M") ? "<i class='fa fa-male' aria-hidden='true'></i>" : "<i class='fa fa-female' aria-hidden='true'></i>") . " - " . $placeClassement['categorie'] . "</b>&nbsp;&nbsp;(" . $placeClassement['dossard'] . ")</span>";
            $nom = "<b>" . $placeClassement['prenomInternaute'] . "</span>&nbsp;<span id='prenom'>" . $placeClassement['nomInternaute'] . "</b></br>" . $cat . "</span>";
        }
        //Affichage normal
        $affiche_temps = "<b>" . $horaire . "</b><i> (heure)</i></br><i>" . $temps . " (temps)</i>";
        echo "<tr id=" . $placeClassement['idInscription'] . ">                                            
                                                          <td style='vertical-align:middle; '><span id='nom' title='" . $placeClassement['passage'] . "° passage' style='color:#348fe2;font-size:38px;font-weight:normal;'>" . $nbre . ". </span></br><span id='nom' title='" . $placeClassement['passage'] . "° passage' style='color:#348fe2;font-size:12px;font-weight:normal;'>" . $placeClassement['passage'] . "° tour </span></th>
                                                          <td style='vertical-align:middle;color:black;font-size:17px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'>" . $nom . "</th>
                                                          <td id='club'  style='vertical-align:middle;color:black;font-size:15px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'>" . $club . "</th>
                                                          <td id='nomParcours'   title='" . $placeClassement['nomParcours'] . "' style='vertical-align:middle;color:#f50666;font-size:15px;font-weight:normal;'>" . $parcours . "</th>
                                                         <td id='horaire' title='Le temps provisoire = heure de passage - heure départ théorique...' style='vertical-align:middle;color:black;font-size:18px;font-weight:normal;'>" . $affiche_temps . "</th>
                                                          ";
        if ($admin) {
            $query3  = "SELECT re.nomEpreuve, rep.nomParcours, rep.idEpreuveParcours, ept.idEpreuveParcoursTarif  ";
            $query3 .= "FROM r_epreuve re";
            $query3 .= " INNER JOIN r_epreuveparcours as rep ON re.idEpreuve = rep.idEpreuve";
            $query3 .= " INNER JOIN r_epreuveparcourstarif as ept ON rep.idEpreuveParcours = ept.idEpreuveParcours";
            $query3 .= " WHERE re.idEpreuve = " . $idEpreuve . " GROUP BY idEpreuveParcours";
            // echo $query3;
            // exit();
            $result3 = $mysqli->query($query3) or die("Sql error : " . mysqli_error($mysqli));

            echo "<td text-align:'center'>
                                                            <button type='button' name='button' style='color:red;' onclick='deleteTempsCoureur(" . $placeClassement['id'] . ");'>X</button><i> -  ce temps uniquement</i>
                                                            </br>
                                                            <button type='button' name='button' style='color:black;' onclick='deleteCoureur(" . $placeClassement['idInscription'] . ");'>X</button><i> -  tous les temps</i>
                                                            </br>                                
                                                            <button type='button' class='btn btn-primary' data-toggle='modal' data-target='#exampleModal" . $placeClassement['id'] . "'>
                                                                <i class='fa fa-pencil-square-o' aria-hidden='true'></i>
                                                            </button>
                                                            </th>                                
                                                            <!-- Modal -->
                                                            <div class='modal modal-xl'  id='exampleModal" . $placeClassement['id'] . "' tabindex='-1' aria-labelledby='exampleModalLabel' aria-hidden='true'>
                                                                <div class='modal-dialog' style='width:950px;'>
                                                                    <div class='modal-content'>
                                                                        <div class='modal-header'>
                                                                        <form method='POST' action='modif_liste_live.php' enctype='multipart/form-data'>
                                                                            <h5 class='modal-title' id='exampleModalLabel'>Edition du dossard " . $placeClassement['dossard'] . "</h5>
                                                                            <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                                                                <span aria-hidden='true'>&times;</span>
                                                                            </button>
                                                                        </div>
                                                                        <div class='modal-body'>
                                                                            
                                                                                <div class='form-row'>
                                                                                    <div class='form-group col-md-2'>
                                                                                    <label >DOSSARD</label>
                                                                                    <input type='text' class='form-control' id='dossard' name='dossard' placeholder='dossard' value=" . $placeClassement['dossard'] . ">
                                                                                    </div>
                                                                                    <div class='form-group col-md-5'>
                                                                                    <label >NOM</label>
                                                                                    <input type='text' class='form-control' id='nom' name='nom' placeholder='NOM' value=" . $placeClassement['nomInternaute'] . ">
                                                                                    </div>
                                                                                    <div class='form-group col-md-5'>
                                                                                    <label >PRENOM</label>
                                                                                    <input type='text' class='form-control' id='prenom' name='prenom' placeholder='PRENOM' value=" . $placeClassement['prenomInternaute'] . ">
                                                                                    </div>
                                                                                </div>
                                                                                <div class='form-row'>
                                                                                    <div class='form-group col-md-1'>
                                                                                    <label >SEXE</label>
                                                                                    <input type='text' class='form-control' id='sexe' name='sexe' placeholder='SEXE' value=" . $placeClassement['sexeInternaute'] . ">
                                                                                    </div>
                                                                                    <div class='form-group col-md-1'>
                                                                                    <label >CAT</label>
                                                                                    <input type='text' class='form-control' id='categorie' name='categorie' placeholder='CAT' value=" . $placeClassement['categorie'] . ">
                                                                                    </div>
                                                                                    <div class='form-group col-md-5'>
                                                                                    <label >CLUB</label>
                                                                                    <input type='text' class='form-control' id='club' name='club' placeholder='CLUB' value=" . $placeClassement['clubInternaute'] . ">
                                                                                    </div>
                                                                                    <input type='hidden' class='form-control' id='id_iei' name='id_iei' value=" . $placeClassement['idInscription'] . ">
                                                                                    <input type='hidden' class='form-control' id='idEpreuve' name='idEpreuve' value=" . $idEpreuve . ">
                                                                                    <input type='hidden' class='form-control' id='idLecteur' name='idLecteur' value=" . $idLecteur . ">
                                                                                    
                                                                                    <div class='form-group col-md-5'>
                                                                                    <label for='inputState'>PARCOURS</label>
                                                                                    <select id='idEpreuveParcours' name='idEpreuveParcours' class='form-control'>";
            while ($row = mysqli_fetch_assoc($result3)) {
                echo '<option value="' . $row['idEpreuveParcours'] . '|' . $row['idEpreuveParcoursTarif'] . '" ' . (($placeClassement['nomParcours'] == $row['nomParcours']) ? "selected" : "") . '>' . $row['nomParcours'] . '</option>';
            }
            echo "
                                                                                    </select>
                                                                                    </div>
                                                                                </div>  
                                                                            </div>
                                                                        <div class='modal-footer'>
                                                                        <div class='form-check'>
                                                                                <input class='form-check-input' type='radio' name='action' id='action1' value='enregistrer' checked>
                                                                                <label class='form-check-label' for='gridRadios1'>
                                                                                Modifier
                                                                                </label>
                                                                            </div>
                                                                            <div class='form-check'>
                                                                                <input class='form-check-input' type='radio' name='action' id='action2' value='remplacer'>
                                                                                <label class='form-check-label' for='gridRadios2'>
                                                                                Remplacer
                                                                                </label>
                                                                            </div>
                                                                            <button type='button' class='btn btn-secondary' data-dismiss='modal'>Fermer</button>
                                                                            <button type='submit' class='btn btn-primary'>Enregistrer</button>
                                                                        </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>";
        }
        echo "</tr>";
    }
    echo "<tr><td text-align:'center' colspan='4'>
                                                    Ces classements sont provisoires...
                                                    </th></tr>";
    ?>


</tbody>