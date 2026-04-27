<?php
// ini_set("display_errors", 1);
// error_reporting(E_ALL);
// ini_set("display_startup_errors", 1);

require_once("includes/includes.php");
require_once("includes/functions.php");


//session_start();
global $mysqli;
$idEpreuve = $_GET['idEpreuve'];
$idLecteur = $_GET['idLecteur'];
$infos_lieux = getInfosParcoursLieu($idEpreuve, $idLecteur);
$lieu = $infos_lieux['lieu'];
$admin = 0;
//if ($_SESSION["typeInternaute"] == 'admin' || $_SESSION["typeInternaute"] == 'super_organisateur') {
//    $admin = 1;
//}

function isrunner($dossard, $idEpreuve)
{
    global $mysqli;
    $qrunner = "SELECT idInscriptionEpreuveInternaute FROM r_inscriptionepreuveinternaute iei ";
    $qrunner .= "WHERE iei.dossard = ? AND idEpreuve = ? ";
    $stmt = $mysqli->prepare($qrunner);
    $stmt->bind_param("ii", $dossard, $idEpreuve);
    $stmt->execute();
    $result = $stmt->get_result();
    $runner = mysqli_fetch_assoc($result);
    if (!$runner) return false;
    else return $runner;
}

function getNumeroPassage($dossard, $config, $idEpreuve)
{
    global $mysqli;

    //Passage (récupère le nombre de passages dans live_Horaire)
    $qpassage = "SELECT passage, rebip ";
    $qpassage .= "FROM live_Horaire liv ";
    $qpassage .= " WHERE liv.dossard = ? ";
    $qpassage .= " AND lieu = ? ";
    $qpassage .= " AND liv.idEpreuve = ? ";
    $qpassage .= " ORDER BY passage DESC;";
    $stmt = $mysqli->prepare($qpassage);
    $stmt->bind_param("isi", $dossard, $config, $idEpreuve);
    $stmt->execute();
    $result = $stmt->get_result();
    $fpassage = mysqli_fetch_assoc($result);
    $passage = $fpassage['passage'];
    $passage = empty($passage) ? 1 : $passage + 1;
    $rebip = $fpassage['rebip'];
    $rebip = isset($rebip) ? 1 : 0;

    return [$passage, $rebip];
}

if (isset($_POST['ajout_heure']) and $_POST['ajout_heure'] != '' && $_POST['ajout_dossard'] != '') {
    $passage = getNumeroPassage($_POST['ajout_dossard'], $_POST['ajout_lieu'], $idEpreuve);

    $pass = $passage[0];
    $rebip = $passage[1];
    $nb = 1;

    $runner = isrunner($_POST['ajout_dossard'], $idEpreuve);
    $iei = $runner['idInscriptionEpreuveInternaute'];
    $tabheure = explode(" ", $_POST['ajout_heure']);
    $tabdate = explode("/", $tabheure[0]);
    $horaire = $tabdate[2] . "-" . $tabdate[1] . "-" . $tabdate[0] . " " . $tabheure[1];

    //Insertion du résultat, champs fixe
    $qresultats = "INSERT INTO live_Horaire ";
    $qresultats .= "( dossard, lieu, idEpreuve, idLecteur, idInscription,  passage, horaire, status, type, indice, rebip ) VALUES ";
    $qresultats .= "(";
    $qresultats .= " ? ,";
    $qresultats .= " ? ,"; //lieu
    $qresultats .= " ? ,";
    $qresultats .= " ? ,"; //idLecteur
    $qresultats .= " ? ,";
    $qresultats .= " ? ,"; //passage
    $qresultats .= " ? ,"; //horaire
    $qresultats .= "'OK',"; //status
    $qresultats .= "'MAN',"; //type
    $qresultats .= " ? ,"; //indice
    $qresultats .= " ? ) ON DUPLICATE KEY UPDATE horaire = ? , rebip = ? ";

    $stmt = $mysqli->prepare($qresultats);
    $stmt->bind_param("isiiiisiisi", $_POST['ajout_dossard'], $_POST['ajout_lieu'], $idEpreuve, $idLecteur, $iei, $pass, $horaire, $nb, $rebip, $horaire, $rebip);
    $stmt->execute();
    $result = $stmt->get_result();
}
function getClassementLieu($idEpreuve, $idLecteur)
{
    global $mysqli;
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
    // echo $query;
    // exit();

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("iii", $idLecteur, $idEpreuve, $idEpreuve);
    $stmt->execute();
    $result = $stmt->get_result();
    $classement = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $classement;
}

$classement = getClassementLieu($idEpreuve, $idLecteur);

function getflag($pays)
{
    global $mysqli;

    $qflag = "SELECT PAYSCODE, PAYSNAME FROM tpays WHERE PAYSNAME LIKE LOWER(TRIM('%" . $pays . "%'))";
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

// fonction qui récupère les informations du lieu pour générer l'affichage de la page classementLieu.php

function getInfosParcoursLieu($idEpreuve, $idLecteur)
{
    global $mysqli;

    $query = 'SELECT DISTINCT nomEpreuve, nomParcours, dateEpreuve, horaireDepart, lieu
    FROM r_epreuveparcours ep
    NATURAL JOIN r_epreuve
    JOIN live_Lecteur cl ON ep.idEpreuve = cl.idEpreuve
    WHERE cl.idEpreuve = ?
    AND idLecteur = ? ';

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $idEpreuve, $idLecteur);
    $stmt->execute();
    $result = $stmt->get_result();
    $infos_lieux = mysqli_fetch_assoc($result);

    return $infos_lieux;
}

function getParcours($idEpreuve, $idLecteur)
{
    global $mysqli;

    $query = 'SELECT DISTINCT ep.nomParcours, ep.idEpreuveParcours
    FROM r_epreuveparcours ep
    JOIN live_Lecteur cl ON ep.idEpreuve = cl.idEpreuve
    JOIN live_Horaire liv ON ep.idEpreuveParcours = (
        SELECT idEpreuveParcours 
        FROM r_inscriptionepreuveinternaute 
        WHERE idInscriptionEpreuveInternaute = liv.idInscription
    )
    WHERE cl.idEpreuve = ?
    AND cl.idLecteur = ?
    AND liv.idLecteur = ?
    AND ep.nomParcours <> "Repas"
    AND liv.passage <= cl.nb_passage
    AND cl.date_min < liv.horaire
    AND cl.date_max > liv.horaire';

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("iii", $idEpreuve, $idLecteur, $idLecteur);
    $stmt->execute();
    $result = $stmt->get_result();
    $parcours = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $parcours;
}

function getLecteurs($idEpreuve)
{
    global $mysqli;
    $queryLecteurs = "SELECT idLecteur, lieu, date_min, date_max, ordre
                  FROM live_Lecteur
                  WHERE idEpreuve = ?
                  ORDER BY ordre ASC, idLecteur ASC";
    $stmt = $mysqli->prepare($queryLecteurs);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $lecteurs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return $lecteurs;
}

function getTempsPassageParLecteur($idInscription, $idEpreuve)
{
    global $mysqli;

    $query = "SELECT cl.idLecteur, cl.lieu, cl.ordre, liv.horaire, ep.horaireDepart
              FROM live_Horaire liv
              INNER JOIN live_Lecteur cl ON liv.idLecteur = cl.idLecteur
              INNER JOIN r_inscriptionepreuveinternaute iei ON liv.idInscription = iei.idInscriptionEpreuveInternaute
              INNER JOIN r_epreuveparcours ep ON iei.idEpreuveParcours = ep.idEpreuveParcours
              WHERE liv.idInscription = ?
              AND cl.idEpreuve = ?
              AND cl.date_min < liv.horaire
              AND cl.date_max > liv.horaire
              ORDER BY cl.ordre ASC";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $idInscription, $idEpreuve);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8"/>
    <title>ATS-SPORT | Résultats live <?php echo $infos_lieux['lieu'] ?></title>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <meta content="chronométrage, chronométreur, inscriptions en ligne, dossards, course à pied, trail, cyclisme, cyclosportive, vtt, triathlon, duathlon"
          name="description"/>
    <meta content="" name="author"/>

    <!-- ================== BEGIN BASE CSS STYLE ================== -->
    <!-- <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" /> -->

    <link href="../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="../assets/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet"/>
    <link href="../assets/css/animate.min.css" rel="stylesheet"/>
    <link href="../assets/css/style_c.css" rel="stylesheet"/>
    <link href="../assets/css/theme/blue.css" id="theme" rel="stylesheet"/>
    <link href="../admin/assets/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css" rel="stylesheet"/>
    <link href="../assets/css/classementLive.css" rel="stylesheet"/>

    <script src="../assets/plugins/jquery/jquery-1.9.1.min.js"></script>
    <script src="../assets/plugins/jquery/jquery-migrate-1.1.0.min.js"></script>
    <script src="../assets/plugins/jquery-ui/ui/minified/jquery-ui.min.js"></script>
    <script>
        // fonctions jQuery ajax qui appelle le script majClassementLieu pour mettre à jour le classement du lieu toutes les 5 secondes
        $(document).ready(function () {
            setInterval(function () {
                majTableau();
                majNbCoureurs();
            }, 60000);
            // 1000= 1 seconde, ne pas descendre sous les 5000
        });

        function majTableau() {
            $.ajax({
                type: "GET",
                url: "majClassementLieu.php",
                data: {
                    idLecteur: "<?php echo $idLecteur ?>",
                    idEpreuve: "<?php echo $idEpreuve ?>",
                    lieu: "<?php echo $lieu ?>"
                },
                success: function (data) {
                    $("table").html(data);
                }
            });
        }

        //  fonctions jQuery ajax qui appelle le script majNbCoureurs pour mettre à jour le nombre de coureurs passés sur le lieu toutes les 5 secondes
        function majNbCoureurs() {
            $.ajax({
                type: "GET",
                url: "majNbCoureurs.php",
                data: {
                    idLecteur: "<?php echo $idLecteur ?>",
                    idEpreuve: "<?php echo $idEpreuve ?>"
                },
                success: function (data) {
                    $("#nb_coureurs").html(data);
                }
            });
        }
    </script>
</head>

<body>
<!--<script>require('../public/app.js')</script>-->
<input type="hidden" id="<?php echo $idEpreuve ?>">
<div id="page-container" style="background:rgb(225, 225, 225)">
    <?php include('header.php'); ?>
    <div style="margin-top:10px;"></div>
    <div id="resultats_complets" class="content" data-scrollview="true" style="margin-top:50px;">
        <div class="container-fluid" data-animation="true" data-animation-type="fadeInDown" style="max-width: 90%;">

            <div class="content-title" style="display : flex; flex-direction: column; justify-content : center;">
                <div>
                    <p class="btn btn-inverse"
                       style="background:#444444;font-weight: 500;"><?php echo $infos_lieux['nomEpreuve'] . " - " . date('d/m/Y', strtotime($infos_lieux['dateEpreuve'])) ?></br>
                    </p>
                </div>
            </div>
            <div>
                <hr>
            </div>

            <div class="content-title" style="display : flex; flex-direction: column; justify-content : center;">
                <div>
                    <p class="btn btn-inverse" style="background:#444444;font-weight: 500;">Chronométrage Live
                        - <?php echo "Point de passage : " . $infos_lieux['lieu'] ?></p>
                    </p>
                </div>
                <div id=nb_coureurs></div>
                <div><span style='color:#348fe2;font-size:23px;font-weight:normal;text-align:center;'><a
                                href="liveinsport.php?idEpreuve=<?php echo $idEpreuve ?>"> Suivre + de lieux</a></span>
                </div>

                <div class="input-group input-group-lg">
                    <input class="zoneSaisie" style="width: 70%; height:40px;text-align: center;" type="text"
                           placeholder="Rechercher un nom, prénom ou n° de dossard..." id="maRecherche"
                           onKeyUp="filtrer()">
                </div>
            </div>

            <!-- Logué en admin on peut ajouter des heures ici -->
            <?php if ($admin) {
                ?>
                <div class="panel-body">
                    <div class="col-md-4">
                        <div class="form-group" style="text-align: right">
                        </div>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="col-md-1">
                            <div class="form-group">
                                <input type="text" style="text-align: center" class="form-control" name="ajout_dossard"
                                       id="ajout_dossard" value="" placeholder="Dossard" required?>
                            </div>
                        </div>
                        <?php
                        date_default_timezone_set('UTC');
                        $date = new DateTimeImmutable();
                        ?>
                        <div class="col-md-2">
                            <div class="form-group">
                                <input type="text" style="text-align: center" class="form-control ajout_heure"
                                       name="ajout_heure" id="ajout_heure" value=""
                                       placeholder="<?PHP echo date_format($date, 'd/m/Y H:i:s') ?>"
                                       requiredplaceholder="jj/mm/aaaa hh:mm:ss"
                                       pattern="[0-9][0-9]/[0-9][0-9]/[0-9][0-9][0-9][0-9]\s[0-9][0-9]:[0-9][0-9]:[0-9][0-9]"
                                       required?>

                            </div>
                        </div>
                        <input type="hidden" style="text-align: center" class="form-control ajout_lieu"
                               name="ajout_lieu" id="ajout_lieu" value='<?php echo $infos_lieux['lieu'] ?>'>
                        <div class="row">

                            <div class="col-md-5">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary" name="">Ajouter une heure sur ce
                                        lieu
                                    </button>
                                </div>
                            </div>
                    </form>
                </div>

                <?php
            }
            ?>

            <?php
            echo "<button type='button' class='btn btn-default' style='background:grey;color:white;font-weight: 500;margin-right:5px;margin-bottom:5px;' onclick='filtrerParParcours(0)'>Tous les parcours</button>";
            foreach (getParcours($idEpreuve, $idLecteur) as $p) {
                $color = "background:grey;color:white;font-weight: 500;margin-right:5px;margin-bottom:5px;";
                echo "<button type='button' class='btn btn-default' style='" . $color . "' onclick=\"filtrerParParcours('" . $p['idEpreuveParcours'] . "')\">" . $p['nomParcours'] . "</button>";
            }
            ?>

            <button type="button" onclick="filtrerParSexe('')">Tous</button>
            <button type="button" onclick="filtrerParSexe('M')">Hommes</button>
            <button type="button" onclick="filtrerParSexe('F')">Femmes</button>
            <button type="button" onclick="filtrerParSexe('x')">Non binaire</button>

            <div class='row' stymle>
                <div id='load_classement' class='col'>
                    <div class="table-responsive-lg" style=" width: 100%; text-align:center;">
                        <table class="table table-striped" id="tableau">
                            <thead class="bg-info">
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

                                <?php
                                foreach (getLecteurs($idEpreuve) as $lecteur) {
                                    echo "<th scope='col' style='text-align: center'>" . $lecteur['lieu'] . "<br/><small>(km)</small></th>";
                                }
                                ?>

                                <th scope="col" style="text-align: center">
                                    Horaire / Time
                                </th>
                                <?php
                                if ($admin)
                                    echo "<th text-align:'center'>Action</th>"
                                ?>
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
                                    for ($i = 0;
                                         $i < count($listeEpreuve);
                                         $i++) {
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
                                    $qequipe = "SELECT nomInternaute, prenomInternaute, sexeInternaute, paysInternaute FROM r_internaute ri 
                                                            INNER JOIN r_inscriptionepreuveinternaute iei ON ri.idInternaute = iei.idInternaute
                                                              WHERE iei.equipe LIKE '" . addslashes($placeClassement['equipe']) . "' AND iei.idEpreuve = " . $idEpreuve . "";
                                    $result2 = $mysqli->query($qequipe) or die("Sql error : " . mysqli_error($mysqli));
                                    $i = 0;
                                    $club = "";
                                    $cat = "";
                                    $nb_femme = 0;
                                    $nb_homme = 0;
                                    while ($row = mysqli_fetch_assoc($result2)) {
                                        $club .= (($i > 0) ? "</br>" : "");
                                        $club .= " <span ' style=" . (($row['sexeInternaute'] == "F") ? 'font-style:italic;' : '') . ">" . $row['prenomInternaute'] . " " . $row['nomInternaute'] . " - " . (($placeClassement['sexeInternaute'] == "M") ? "<i class='fa fa-male' ;></i>" : "<i class='fa fa-female' style='color:#f50666;'></i>") . " | " . $placeClassement['categorie'] . "</span>";
                                        if ($row['sexeInternaute'] == "F") $nb_femme = $nb_femme + 1;
                                        if ($row['sexeInternaute'] == "M") $nb_homme = $nb_homme + 1;
                                        $i++;
                                    }

                                    $nom = "<b>Equipe " . $placeClassement['equipe'] . "</b></br>" . $nb_femme . " <i class='fa fa-female' style='color:#f50666;'></i> | " . $nb_homme . " <i class='fa fa-male'></i>  (" . $placeClassement['dossard'] . ")";
                                    $club .= "";
                                    $cat .= "";
                                } //on crée les variables ici pour l'affichage des solos
                                else {
                                    $club = "" . $placeClassement['clubInternaute'] . "</br>" . $placeClassement['villeInternaute'] . "";
                                    $cat = "<span id='cat'>" . "&nbsp;&nbsp;<b>" . (($placeClassement['sexeInternaute'] == "M") ? "<i class='fa fa-male' ;></i>" : "<i class='fa fa-female' style='color:#f50666;'></i>") . " - " . $placeClassement['categorie'] . "</b>&nbsp;&nbsp;(" . $placeClassement['dossard'] . ")</span>";
                                    $nom = "<b>" . $placeClassement['prenomInternaute'] . "</span>&nbsp;<span id='prenom'>" . $placeClassement['nomInternaute'] . "</b></br>" . $cat . "</span>";
                                }

                                $tempsParLecteur = getTempsPassageParLecteur($placeClassement['idInscriptionEpreuveInternaute'], $idEpreuve);
                                $tempsParLecteurArray = array();

                                foreach ($tempsParLecteur as $temps) {
                                    $tempsParLecteurArray[$temps['idLecteur']] = $temps;
                                }
                                //Affichage normal
                                $affiche_temps = "<b>" . $horaire . "</b><i> (heure)</i></br><i>" . $temps . " (temps)</i>";
                                echo "<tr id='" . $placeClassement['idInscription'] . "' data-parcours='" . $placeClassement['idEpreuveParcours'] . "' data-sexe='" . $placeClassement['sexeInternaute'] . "'>
    <td style='vertical-align:middle; '><span id='nom' title='" . $placeClassement['passage'] . "° passage' style='color:#348fe2;font-size:38px;font-weight:normal;'>" . $nbre . ". </span></br><span id='nom' title='" . $placeClassement['passage'] . "° passage' style='color:#348fe2;font-size:12px;font-weight:normal;'>" . $placeClassement['passage'] . "° tour </span></td>" .
                                        "<td style='vertical-align:middle;color:black;font-size:17px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'>" . $nom . "</th>
                                                          <td id='club'  style='vertical-align:middle;color:black;font-size:15px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'>" . $club . "</th>
                                                          <td id='nomParcours'" . "  title='" . $placeClassement['nomParcours'] . "' style='vertical-align:middle;color:#f50666;font-size:15px;font-weight:normal;'>" . $parcours . "</th>";
                                foreach (getLecteurs($idEpreuve) as $lecteur) {
                                    if (isset($tempsParLecteurArray[$lecteur['idLecteur']])) {
                                        $temps2 = $tempsParLecteurArray[$lecteur['idLecteur']];
                                        $horaire = date('H:i:s', strtotime($temps2['horaire']));
                                        $tempsEcoule = calculTemps($temps['horaire'], $temps2['horaireDepart']);
                                         echo "<td style='text-align:center;'><b>" . $horaire . "</b><br/><i>" . $tempsEcoule . "</i></td>";
                                    } else {
                                        echo "<td style='text-align:center;'>-</td>";
                                    }
                                }

                                echo "<td id='horaire' title='Le temps provisoire = heure de passage - heure départ théorique...' style='vertical-align:middle;color:black;font-size:18px;font-weight:normal;'>" . $affiche_temps . "</th>
                                                         ";


                                if ($admin) {
                                    $query3 = "SELECT re.nomEpreuve, rep.nomParcours, rep.idEpreuveParcours, ept.idEpreuveParcoursTarif  ";
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
                                <!--<button type='button' class='btn btn-primary' data-toggle='modal' data-target='#exampleModal" . $placeClassement['id'] . "'>
                                    <i class='fa fa-pencil-square-o' aria-hidden='true'></i>
                                </button>-->
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
                            }

                            echo "</tr>";
                            echo "<tr><td text-align:'center' colspan='4'>
                                                Ces classements sont provisoires...
                                                </th></tr>";
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class='col'>
                </div>
                <?php if ($admin) {
                    echo
                            '<div class="content col-md-12 col-sm-12 col-xs-12">                                    
                                            <input id="bouton-suppr" type="submit" class="btn btn-danger center-block"
                                            value="Supprimer tous les résultats" onclick="deleteAllCoureurs(' . $idLecteur . ',' . $idEpreuve . ')">                                      
                                </div>';
                } ?>
            </div>

        </div>
        <?php include('../footer.php') ?>
    </div>
</div>


<!-- ================== BEGIN BASE JS ================== -->
<!-- <script src="../admin/assets/plugins/datetimepicker-master/jquery.datetimepicker.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-cookie/jquery.cookie.js"></script>
<script src="../assets/plugins/scrollMonitor/scrollMonitor.js"></script> -->
<!-- <script src="../assets/js/apps.js"></script> -->
<!-- <script src="../assets/js/vendor/popper.min.js"></script>
<script src="../assets/js/vendor/holder.min.js"></script> -->
<!-- <script src="../assets/js/bootstrap.min.js"></script> -->
<!-- <script src="../assets/js/live.js"></script> -->
<script src="../admin/assets/plugins/jquery/jquery-1.9.1.min.js"></script>
<script src="../admin/assets/plugins/jquery/jquery-migrate-1.1.0.min.js"></script>
<script src="../admin/assets/plugins/jquery-ui/ui/minified/jquery-ui.min.js"></script>
<script src="../admin/assets/plugins/bootstrap/js/bootstrap.min.js"></script>

<!-- ================== END BASE JS ================== -->

<script type="text/javascript">
    const searchBar = document.querySelector("#searchInput");
    searchInput.addEventListener('keyup', function () {
        const input = searchInput.nodeValue;
        console.log(input);
    })

    // fonctions jQuery ajax qui permettent à l'admin de supprimer un/des coureurs du classement si besoin. Elles appellent les scripts de suppression PHP
    function deleteTempsCoureur(id, idLecteur, idEpreuve) {
        $(document).ready(function () {
            $.ajax({
                url: 'suppressionCoureurLieu.php',
                method: 'POST',
                data: {
                    id: id,
                    idLecteur: "<?php echo $idLecteur ?>",
                    idEpreuve: "<?php echo $idEpreuve ?>",
                    action: "deleteTemps"
                },
                success: function (response) {
                    alert("Temps supprimé");
                    document.getElementById(id).style.display = "none";
                }
            });
        });
    }

    // fonctions jQuery ajax qui permettent à l'admin de supprimer un/des coureurs du classement si besoin. Elles appellent les scripts de suppression PHP
    function deleteCoureur(id, lieu, idEpreuve) {
        $(document).ready(function () {
            $.ajax({
                url: 'suppressionCoureurLieu.php',
                method: 'POST',
                data: {
                    id: id,
                    lieu: "<?php echo htmlspecialchars($lieu) ?>",
                    idEpreuve: "<?php echo $idEpreuve ?>",
                    action: "delete"
                },
                success: function (response) {
                    alert("Coureur supprimé");
                    document.getElementById(id).style.display = "none";
                }
            });
        });
    }

    function deleteAllCoureurs(idLecteur, idEpreuve) {
        $(document).ready(function () {
            $.ajax({
                url: 'suppressionCoureurLieu.php',
                method: 'POST',
                data: {
                    idLecteur: "<?php echo $idLecteur ?>",
                    idEpreuve: "<?php echo $idEpreuve ?>",
                    action: "deleteAll"
                },
                success: function (response) {
                    alert("Tout le classement a été supprimé");
                    document.getElementById("<?php echo $lieu ?>").style.display = "none";
                }
            });
        });
    }
</script>
<script>
    function filtrer() {


        var filtre, tableau, ligne, cellule, i, texte

        filtre = document.getElementById("maRecherche").value.toUpperCase();
        tableau = document.getElementById("tableau");
        ligne = tableau.getElementsByTagName("tr");


        for (i = 0; i < ligne.length; i++) {

            cellule = ligne[i].getElementsByTagName("td")[1];
            if (cellule) {
                texte = cellule.innerText;
                if (texte.toUpperCase().indexOf(filtre) > -1) {
                    ligne[i].style.display = "";
                } else {
                    ligne[i].style.display = "none";
                }
            }
        }
    }

    let currentSexe = '';
    let currentParcours = '';
    let currentRecherche = '';

    function filtrer() {
        currentRecherche = document.getElementById("maRecherche").value.toUpperCase();
        appliquerFiltres();
    }

    function filtrerParSexe(sexe) {
        currentSexe = sexe;
        appliquerFiltres();
    }

    function filtrerParParcours(idParcours) {
        currentParcours = idParcours === 0 ? '' : String(idParcours);
        appliquerFiltres();
    }


    function appliquerFiltres() {
        var lignes = document.querySelectorAll("#tableau tbody tr");

        for (var i = 0; i < lignes.length; i++) {
            var sexeLigne = lignes[i].getAttribute("data-sexe");
            var parcoursLigne = lignes[i].getAttribute("data-parcours");
            var cellule = lignes[i].getElementsByTagName("td")[1];
            var texte = cellule ? cellule.innerText.toUpperCase() : "";

            var passeSexe = !currentSexe || sexeLigne === currentSexe;
            var passeParcours = !currentParcours || parcoursLigne === currentParcours;
            var passeRecherche = !currentRecherche || texte.indexOf(currentRecherche) > -1;

            lignes[i].style.display = (passeSexe && passeParcours && passeRecherche) ? "" : "none";
        }
    }
</script>
</body>

</html>