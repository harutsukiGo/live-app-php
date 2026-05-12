<?php
// ini_set("display_errors", 1);
// error_reporting(E_ALL);
// ini_set("display_startup_errors", 1);

require_once("includes/includes.php");
require_once("includes/functions.php");


//session_start();
global $mysqli;

function normaliserLieu($str)
{
    $str = mb_strtolower($str, 'UTF-8');
    $accents = ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ô' => 'o', 'ö' => 'o', 'î' => 'i', 'ï' => 'i', 'ç' => 'c'];
    return trim(strtr($str, $accents));
}

$idEpreuve = $_GET['idEpreuve'];
$infos_epreuve = getInfosEpreuve($idEpreuve);
$lieu = $infos_epreuve['ville'];
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
function getClassementLieu($idEpreuve)
{
    global $mysqli;
    $query = 'SELECT MAX(cl.distance_depart) as distance, iei.idInscriptionEpreuveInternaute, iei.idInternaute, iei.idEpreuveParcours, iei.dossard, iei.categorie, iei.equipe,
  MAX(liv.horaire) AS horaire,
  ri.nomInternaute, ri.prenomInternaute, ri.sexeInternaute, ri.clubInternaute, ri.villeInternaute, ri.paysInternaute,
  ep.nomParcours, ep.idEpreuveParcours, ep.horaireDepart,  liv.id , liv.idInscription,liv.status, liv.passage AS passage
  FROM live_Horaire liv
  INNER JOIN r_inscriptionepreuveinternaute iei ON liv.idInscription = iei.idInscriptionEpreuveInternaute
  INNER JOIN r_internaute ri ON iei.idInternaute = ri.idInternaute
  INNER JOIN live_reader cl ON cl.lieu = liv.lieu AND cl.idParcours = iei.idEpreuveParcours
  INNER JOIN r_epreuveparcours ep ON iei.idEpreuveParcours = ep.idEpreuveParcours
  WHERE liv.idEpreuve = ?
  AND cl.date_min < liv.horaire
  AND cl.date_max > liv.horaire
  AND liv.passage <= cl.nb_passage
  AND NOT EXISTS (
      SELECT * FROM live_Horaire abd
      WHERE abd.idInscription = liv.idInscription
      AND abd.idEpreuve = liv.idEpreuve
      AND abd.status = "ABD"
  )
  GROUP BY liv.idInscription
  ORDER BY distance DESC, horaire ASC';
//    echo $query;
//    exit();

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $result = $stmt->get_result();
    $classement = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $classement;
}

$classement = getClassementLieu($idEpreuve);

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
    echo $query;
    exit();

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $idEpreuve, $idLecteur);
    $stmt->execute();
    $result = $stmt->get_result();
    $infos_lieux = mysqli_fetch_assoc($result);

    return $infos_lieux;
}

function getInfosEpreuve($idEpreuve)
{
    global $mysqli;

    $query = 'SELECT  nomEpreuve, dateEpreuve, ville
    FROM r_epreuve ep
    WHERE ep.idEpreuve = ?';
    // echo $query ;
    // exit();

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $result = $stmt->get_result();
    $infos_epreuve = mysqli_fetch_assoc($result);

    return $infos_epreuve;
}


function getParcours($idEpreuve)
{
    global $mysqli;
    $query = 'SELECT ep.nomParcours, ep.idEpreuveParcours, MAX(lr.distance_depart) AS distance_max
    FROM r_epreuveparcours ep
    JOIN live_reader lr ON lr.idParcours = ep.idEpreuveParcours AND lr.idEpreuve = ep.idEpreuve
    WHERE ep.idEpreuve = ?
    GROUP BY ep.idEpreuveParcours
    ORDER BY distance_max ASC';

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $result = $stmt->get_result();
    $parcours = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $parcours;
}


function getLieu($idEpreuve)
{
    global $mysqli;
    $queryLecteurs = "SELECT id, LOWER(lieu) AS lieu, lieu AS lieu_original, distance_depart, idParcours, date_max
                  FROM live_reader
                  WHERE idEpreuve = ?
                  ORDER BY distance_depart";

    $stmt = $mysqli->prepare($queryLecteurs);
//    echo $queryLecteurs;
//    exit();
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $lecteurs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return $lecteurs;
}

function getTempsPassageParLecteur($idInscription, $idEpreuve)
{
    global $mysqli;

    $query = "SELECT cl.idLecteur, liv.lieu, cl.ordre, liv.horaire, ep.horaireDepart
              FROM live_Horaire liv
              INNER JOIN live_Lecteur cl ON liv.idLecteur = cl.idLecteur
              INNER JOIN r_inscriptionepreuveinternaute iei ON liv.idInscription = iei.idInscriptionEpreuveInternaute
              INNER JOIN r_epreuveparcours ep ON iei.idEpreuveParcours = ep.idEpreuveParcours
              WHERE liv.idInscription = ?
              AND cl.idEpreuve = ?
              AND cl.date_min < liv.horaire
              AND cl.date_max > liv.horaire";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $idInscription, $idEpreuve);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getTempsPassageLieu($idEpreuve)
{
    global $mysqli;

    $query = "SELECT lh.horaire, lh.idInscription, LOWER(lh.lieu) AS lieu, lr.distance_depart, lr.idParcours,lr.coefficient
     FROM live_Horaire lh
     JOIN r_inscriptionepreuveinternaute iei ON iei.idInscriptionEpreuveInternaute = lh.idInscription
     INNER JOIN live_reader lr ON (LOWER(lr.lieu) = LOWER(lh.lieu) AND iei.idEpreuveParcours = lr.idParcours)
     WHERE lr.idEpreuve = ? AND lr.date_min < lh.horaire AND lr.date_max > lh.horaire
     ORDER BY lh.horaire DESC;";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $results;
}


function getCategorie($idEpreuve)
{
    global $mysqli;

    $query = "SELECT ri.categorie
  FROM r_inscriptionepreuveinternaute ri
  INNER JOIN r_internaute rie ON rie.idInternaute = ri.idInternaute
  WHERE ri.idEpreuve = ?
  GROUP BY ri.categorie;";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $results;
}

$lieux = getLieu($idEpreuve);

$TabTempsPassageLieu = getTempsPassageLieu($idEpreuve);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8"/>
    <title>ATS-SPORT | Résultats live <?php echo $getInfosEpreuve['nomEpreuve'] ?></title>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <meta content="chronométrage, chronométreur, inscriptions en ligne, dossards, course à pied, trail, cyclisme, cyclosportive, vtt, triathlon, duathlon"
          name="description"/>
    <meta content="" name="author"/>

    <!-- ================== BEGIN BASE CSS STYLE ================== -->
    <!-- <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" /> -->

    <link href="assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="assets/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet"/>
    <link href="assets/css/animate.min.css" rel="stylesheet"/>
    <link href="assets/css/style_c.css" rel="stylesheet"/>
    <link href="assets/css/theme/blue.css" id="theme" rel="stylesheet"/>
    <link href="assets/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css" rel="stylesheet"/>
    <link href="assets/css/classementLive.css" rel="stylesheet"/>

    <script src="../assets/plugins/jquery/jquery-1.9.1.min.js"></script>
    <script src="../assets/plugins/jquery/jquery-migrate-1.1.0.min.js"></script>
    <script src="../assets/plugins/jquery-ui/ui/minified/jquery-ui.min.js"></script>
    <script>
        // fonctions jQuery ajax qui appelle le script majClassementLieu pour mettre à jour le classement du lieu toutes les 5 secondes
        // $(document).ready(function () {
        //     setInterval(function () {
        //         majTableau();
        //         majNbCoureurs();
        //     }, 60000);
        //     // 1000= 1 seconde, ne pas descendre sous les 5000
        // });

        // function majTableau() {
        //     $.ajax({
        //         type: "GET",
        //         url: "majClassementLieu.php",
        //         data: {
        //             idLecteur: "<?php echo $idLecteur ?>",
        //             idEpreuve: "<?php echo $idEpreuve ?>",
        //             lieu: "<?php echo $lieu ?>"
        //         },
        //         success: function (data) {
        //             $("table").html(data);
        //         }
        //     });
        // }

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
<input type="hidden" id="<?php echo $idEpreuve ?>">
<div id="page-container" style="background:rgb(225, 225, 225)">
    <?php include('header.php'); ?>

    <!--         <ul class="nav nav-tabs" style="margin-top:69px;background: #f3f3f3; font-size: 20px; font-weight: 400; ">-->
    <!--            <li role="presentation" class="active"><a href="classementLieu.php?idEpreuve=-->
    <?php //echo $idEpreuve ?><!--" style="background: #e1e1e1; color: black">RÉSULTAT</a></li>-->
    <!--            <li role="presentation" class="active"><a href="classementListeEngage.php?idEpreuve=-->
    <?php //echo $idEpreuve ?><!--" style="background: #e1e1e1; color: black">ENGAGÉS</a></li>-->
    <!--            <li role="presentation" class="active"><a href="classementOrganisateur.php?idEpreuve=-->
    <?php //echo $idEpreuve ?><!--" style="background: #e1e1e1; color: black">TABLEAU DE BOARD</a></li>-->
    <!--        </ul>-->


    <div style="margin-top:10px;"></div>
    <div id="resultats_complets" class="content" data-scrollview="true" style="margin-top:50px;">
        <div class="container-fluid" data-animation="true" data-animation-type="fadeInDown" style="max-width: 90%;">

            <div class="content-title" style="display : flex; flex-direction: column; justify-content : center;">
                <div>
                    <p class="btn btn-inverse"
                       style="background:#444444;font-weight: 500;"><?php echo $infos_epreuve['nomEpreuve'] . " - " . date('d/m/Y', strtotime($infos_epreuve['dateEpreuve'])) ?></br>
                    </p>
                </div>
            </div>
            <div>
                <hr>
            </div>

            <div class="content-title" style="display : flex; flex-direction: column; justify-content : center;">
                <!-- <div>
                    <p class="btn btn-inverse" style="background:#444444;font-weight: 500;">Chronométrage Live
                        - <?php echo "Point de passage : " . $infos_epreuve['ville'] ?></p>
                    </p>
                </div> -->
                <div id=nb_coureurs></div>
                <!--                <div>-->
                <!--                    <span style='color:#348fe2;font-size:23px;font-weight:normal;text-align:center;'><a-->
                <!--                                href="liveinsport.php?idEpreuve=--><?php //echo $idEpreuve ?><!--"> Revenir à l'accueil</a></span>-->
                <!--                </div>-->

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
                               name="ajout_lieu" id="ajout_lieu" value='<?php echo $infos_epreuve['ville'] ?>'>
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

            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a href="classementLieu.php?idEpreuve=<?php echo $idEpreuve ?>"
                       style="background: #e1e1e1; color: black">RÉSULTAT</a>
                </li>
                <li class="nav-item">
                    <a href="https://ats-sport.com/liste_des_inscrits.php?id_epreuve=<?php echo $idEpreuve ?>&course=<?php echo strtolower($infos_epreuve['nomEpreuve']) ?>"
                       style="background: #e1e1e1; color: black">ENGAGÉS</a>
                </li>
            </ul>
            <style>
                .dashed {
                    border-bottom: 3px dashed;
                }
            </style>

            <?php
            $listeParcours = getParcours($idEpreuve);
            $premierParcoursId = !empty($listeParcours) ? $listeParcours[0]['idEpreuveParcours'] : null;
            foreach ($listeParcours as $p) {
                echo "<button type='button' class='btn btn-outline-secondary m-b-10 m-r-10' data-idEpreuveParcours='" . $p['idEpreuveParcours'] . "' onclick=\"filtrerParParcours('" . $p['idEpreuveParcours'] . "')\">" . $p['nomParcours'] . "</button>";
            }
            ?>

            <div class="w-100" role="group" aria-label="Filtres categorie">

                <!--                --><?php
                //
                //                $listeCategorie = getCategorie($idEpreuve);
                //                echo "<button type='button' class='btn btn-outline-secondary m-b-10 m-r-10'  data-categorieFiltre='0' onclick=\"filtrerParCategorie(0)\">Tous</button>";
                //                foreach ($listeCategorie as $c) {
                //                    echo "<button type='button' class='btn btn-outline-secondary m-b-10 m-r-10'  data-categorieFiltre='" . $c['categorie'] . "' onclick=\"filtrerParCategorie('" . $c['categorie'] . "')\">" . $c['categorie'] . "</button>";
                //                }
                //                ?>
            </div>

            <div class="w-100 m-b-10" role="group" aria-label="Filtres sexe">
                <button type="button" class="btn btn-secondary btn-sexe active" onclick="filtrerParSexe('', this)">
                    Tous
                </button>
                <button type="button" class="btn btn-secondary btn-sexe" onclick="filtrerParSexe('M', this)"><i
                            class="fa fa-male"></i></button>
                <button type="button" class="btn btn-secondary btn-sexe" onclick="filtrerParSexe('F', this)"><i
                            class="fa fa-female"></i></button>
                <?php
                if (in_array('', array_column($classement, 'sexeInternaute'))) {
                    echo "<button type='button' class='btn btn-secondary btn-sexe' onclick=\"filtrerParSexe('x', this)\">Autres</button>";
                }
                ?>

                <button type="button" class="btn btn-secondary btn-estimation" onclick="togglePredictions(this)">
                    Estimation
                </button>
            </div>
            <div class='row' style>
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


                                <?php
                                foreach ($lieux as $lieu) {
                                    echo "<th scope='col' style='text-align: center' data-parcoursLieu='" . $lieu['idParcours'] . "' data-lieuIndex='" . $lieu['idParcours'] . "'>" . $lieu['lieu_original'] . "<br/><small> " . $lieu['distance_depart'] . "km</small> </th>";
                                }
                                ?>

                                <th scope="col" style="text-align: center">
                                    Vitesse
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
                            // $query2 = "SELECT id, dossard, passage FROM live_Horaire liv
                            // INNER JOIN live_Lecteur cl ON liv.idLecteur=cl.idLecteur
                            // WHERE liv.idLecteur = " . $idLecteur . " AND liv.idEpreuve=" . $idEpreuve . " AND liv.passage < cl.nb_passage AND cl.date_min < liv.horaire AND cl.date_max > liv.horaire
                            // ORDER BY dossard, horaire ";
                            // $result2 = $mysqli->query($query2) or die("Sql error : " . mysqli_error($mysqli));
                            $passage = 1;
                            $dossard = 0;
                            // while ($row = mysqli_fetch_assoc($result2)) {
                            //     //   echo $row['dossard']."=". $dossard."</br>";
                            //     if ($row['dossard'] == $dossard) {
                            //         $passage = $passage + 1;
                            //     } else {
                            //         $passage = 1;
                            //         $dossard = $row['dossard'];
                            //     }
                            //     $query2 = "UPDATE live_Horaire SET passage = " . $passage . " WHERE id = " . $row['id'];
                            //     $result2 = $mysqli->query($query2) or die("Sql error : " . mysqli_error($mysqli));
                            // }


                            foreach ($classement as $placeClassement) {
                                //On scinde le nom du parcours

                                $pparcours = explode("-", $placeClassement['nomParcours']);
                                $parcours = $pparcours[0] . (isset($pparcours[1]) ? "<br>" . $pparcours[1] : "");

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

                                foreach ($tempsParLecteur as $tempsData) {
                                    $tempsParLecteurArray[$tempsData['idLecteur']] = $tempsData;
                                }
                                //Affichage normal
                                $affiche_temps = "<b>" . $horaire . "</b><i> (heure)</i></br><i>" . $temps . " (temps)</i>";


                                if ($placeClassement['passage'] > 1) {
                                    echo "<tr id='" . $placeClassement['idInscription'] . "' data-parcours='" . $placeClassement['idEpreuveParcours'] . "' data-sexe='" . $placeClassement['sexeInternaute'] . "' data-status='" . $placeClassement['status'] . "' data-categorie='" . $placeClassement['categorie'] . "'>";
                                    echo "<td style='vertical-align:middle;'><span title='" . $placeClassement['passage'] . "° passage' style='color:#348fe2;font-size:38px;font-weight:normal;'>" . $nbre . ". </span></br><span title='" . $placeClassement['passage'] . "° passage' style='color:#348fe2;font-size:12px;font-weight:normal;'>" . $placeClassement['passage'] . "° tour </span></td>";
                                    echo "<td style='vertical-align:middle;color:black;font-size:17px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'><b>" . $nom . "</b></td>";
                                    echo "<td style='vertical-align:middle;color:black;font-size:15px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'><b>" . $club . "</b></td>";
                                } else {
                                    echo "<tr id='" . $placeClassement['idInscription'] . "' data-parcours='" . $placeClassement['idEpreuveParcours'] . "' data-sexe='" . $placeClassement['sexeInternaute'] . "' data-status='" . $placeClassement['status'] . "' data-categorie='" . $placeClassement['categorie'] . "'>";
                                    echo "<td style='vertical-align:middle;'><span title='" . $placeClassement['passage'] . "° passage' style='color:#348fe2;font-size:38px;font-weight:normal;'>" . $nbre . ". </span></br><span title='" . $placeClassement['passage'] . "° passage' style='color:#348fe2;font-size:12px;font-weight:normal;'>" . " </span></td>";
                                    echo "<td style='vertical-align:middle;color:black;font-size:17px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'>" . $nom . "</td>";
                                    echo "<td style='vertical-align:middle;color:black;font-size:15px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'>" . $club . "</td>";
                                }

                                $horaireDepart = $placeClassement['horaireDepart'];
                                $idInscription = $placeClassement['idInscription'];
                                $idParcoursCoureur = $placeClassement['idEpreuveParcours'];


                                $tempsPassagesCoureur = array_filter($TabTempsPassageLieu, function ($row) use ($idInscription, $idParcoursCoureur) {
                                    return (int)$row['idInscription'] == (int)$idInscription
                                            && (int)$row['idParcours'] == (int)$idParcoursCoureur;
                                });

                                $dernierPassage = null;
                                $distanceMax = 0;
                                foreach ($tempsPassagesCoureur as $passage) {
                                    if ($passage['distance_depart'] > $distanceMax) {
                                        $distanceMax = $passage['distance_depart'];
                                        $dernierPassage = $passage;
                                    }
                                }

                                $vitesseMoyenne = null;
                                if ($dernierPassage && $distanceMax > 0) {
                                    $tempsEcoule = strtotime($dernierPassage['horaire']) - strtotime($horaireDepart);
                                    if ($tempsEcoule > 0) {
                                        $coefficient = isset($dernierPassage['coefficient']) ? $dernierPassage['coefficient'] : 1;
                                        $vitesseMoyenne = $distanceMax * $coefficient / ($tempsEcoule / 3600);
                                    }
                                }

                                foreach ($lieux as $lieuIndex => $lieuInfo) {
                                    $nomLieu = $lieuInfo['lieu'];
                                    $lieuParcours = $lieuInfo['idParcours'];
                                    $distanceLieu = (float)$lieuInfo['distance_depart'];

                                    $tempsFiltre = [];
                                    if ($lieuParcours == $idParcoursCoureur) {
                                        $tempsFiltre = array_filter($TabTempsPassageLieu, function ($row) use ($idInscription, $nomLieu, $idParcoursCoureur) {
                                            return (int)$row['idInscription'] == (int)$idInscription
                                                    && normaliserLieu($row['lieu']) == normaliserLieu($nomLieu)
                                                    && (int)$row['idParcours'] == (int)$idParcoursCoureur;
                                        });
                                    }

                                    if (!empty($tempsFiltre)) {
                                        $tempsTrouvee = array_values($tempsFiltre)[0];
                                        $horairePassage = date('H:i:s', strtotime($tempsTrouvee['horaire']));
                                        $tempsPassageAffiche = calculTemps($tempsTrouvee['horaire'], $horaireDepart);
                                        echo "<td id='horaire' data-lieuIndex='" . $lieuParcours . "' style='vertical-align:middle;'>" .
                                                "<b>" . $horairePassage . "</b><i> (heure)</i></br><i>" . $tempsPassageAffiche . " (temps)</i></td>";

                                    } else if ($vitesseMoyenne && $distanceLieu >= $distanceMax) {
                                        $tempsPreditSecondes = ($distanceLieu / $vitesseMoyenne) * 3600;
                                        $horairePreditTimestamp = strtotime($horaireDepart) + (int)$tempsPreditSecondes;
                                        $horairePreditAffiche = date('H:i:s', $horairePreditTimestamp);
                                        $tempsPreditAffiche = gmdate('G:i:s', (int)$tempsPreditSecondes);

                                        echo "<td id='horaire' data-lieuIndex='" . $lieuParcours . "' data-lieu='" . $nomLieu . "' data-predict='true' style='vertical-align:middle; color:#007FFF; font-style:italic;'>" .
                                                "<b>~" . $horairePreditAffiche . "</b><i> (prédit)</i></br><i>~" . $tempsPreditAffiche . "</i></td>";

                                    } else {
                                        echo "<td id='horaire' data-lieuIndex='" . $lieuParcours . "' data-lieu='" . $nomLieu . "' style='vertical-align:middle; text-align:center;'>-</td>";
                                    }
                                }

                                if ($vitesseMoyenne) {
                                    echo "<td style='vertical-align:middle; text-align:center;'><b>" . round($vitesseMoyenne, 2) . "</b> km/h</td>";
                                } else {
                                    echo "<td style='vertical-align:middle; text-align:center;'> Vitesse inconnu</td>";
                                }


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
                                <button type='button' name='button' style='color:orange; font-weight:bold;' onclick='marquerAbandon(" . $placeClassement['idInscription'] . ", " . $placeClassement['dossard'] . ");'>ABD</button><i> </i>
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

                                echo "</tr>";
                            }
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
                    lieu: "<?php echo htmlspecialchars($infos_epreuve['ville']) ?>",
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
    let currentCategorie = '';

    function filtrer() {
        currentRecherche = document.getElementById("maRecherche").value.toUpperCase();
        appliquerFiltres();
    }

    function filtrerParSexe(sexe, btn) {
        currentSexe = sexe;
        appliquerFiltres();

        if (btn) {
            document.querySelectorAll('.btn-sexe').forEach(function (b) {
                b.classList.remove('active', 'btn-primary');
                b.classList.add('btn-secondary');
            });
            btn.classList.remove('btn-secondary');
            btn.classList.add('active', 'btn-primary');
        }
    }

    function filtrerParParcours(idParcours) {
        currentParcours = idParcours === 0 ? '' : String(idParcours);
        appliquerFiltres();

        var colonnesLieux = document.querySelectorAll("th[data-parcoursLieu]");

        colonnesLieux.forEach(function (col) {
            var parcoursLieu = col.getAttribute('data-parcoursLieu');

            if (parcoursLieu === String(idParcours)) {
                col.style.display = "";
                var indexLieu = col.getAttribute('data-lieuIndex');
                document.querySelectorAll("td[data-lieuIndex='" + indexLieu + "']").forEach(td => td.style.display = "");
            } else {
                col.style.display = "none";
                var indexLieu = col.getAttribute('data-lieuIndex');
                document.querySelectorAll("td[data-lieuIndex='" + indexLieu + "']").forEach(td => td.style.display = "none");
            }
        });
    }


    // function filtrerParCategorie(categorie) {
    //     currentCategorie = categorie;
    //     appliquerFiltres();
    //
    //     var colonnesLieux = document.querySelectorAll("th[data-categorie]");
    //     colonnesLieux.forEach(function (col) {
    //         var parcoursCategorie = col.getAttribute('data-categorieFiltre');
    //         var indexCategorie = col.getAttribute('data-categorieFiltre');
    //
    //         if (categorie === 0 || parcoursCategorie === String(categorie)) {
    //             col.style.display = "";
    //             document.querySelectorAll("td[data-categorieFiltre='" + indexCategorie + "']").forEach(td => td.style.display = "");
    //         } else {
    //             col.style.display = "none";
    //             document.querySelectorAll("td[data-categorieFiltre='" + indexCategorie + "']").forEach(td => td.style.display = "none");
    //         }
    //     });
    //
    //
    // }


    function appliquerFiltres() {
        var lignes = document.querySelectorAll("#tableau tbody tr");

        for (var i = 0; i < lignes.length; i++) {
            var sexeLigne = lignes[i].getAttribute("data-sexe");
            var parcoursLigne = lignes[i].getAttribute("data-parcours");
            var categorieLigne = lignes[i].getAttribute("data-categorie");
            var cellule = lignes[i].getElementsByTagName("td")[1];
            var texte = cellule ? cellule.innerText.toUpperCase() : "";

            var passeSexe = !currentSexe || sexeLigne === currentSexe;
            var passeParcours = !currentParcours || parcoursLigne === currentParcours;
            var passeRecherche = !currentRecherche || texte.indexOf(currentRecherche) > -1;
            var passeCategorie = !currentCategorie || categorieLigne === currentCategorie;


            lignes[i].style.display = (passeSexe && passeParcours && passeRecherche && passeCategorie) ? "" : "none";
        }
    }

    var predictionsVisible = true;

    function togglePredictions(btn) {
        var predictions = document.querySelectorAll("td[data-predict='true']");
        predictions.forEach(function (td) {
            if (predictionsVisible) {
                td.setAttribute('data-original-content', td.innerHTML);
                td.innerHTML = '-';
                td.style.color = '';
                td.style.fontStyle = '';
                td.style.textAlign = 'center';
            } else {
                td.innerHTML = td.getAttribute('data-original-content');
                td.style.color = '#007FFF';
                td.style.fontStyle = 'italic';
            }
        });
        predictionsVisible = !predictionsVisible;

        if (btn) {
            if (predictionsVisible) {
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-info');
            } else {
                btn.classList.remove('btn-info');
                btn.classList.add('btn-secondary');
            }
        }
    }


    document.addEventListener('DOMContentLoaded', function () {
        const btnParcours = document.querySelectorAll("[data-idEpreuveParcours]");
        const btnCategories = document.querySelectorAll("[data-categorieFiltre]");

        btnParcours.forEach(function (btn) {
            btn.addEventListener("click", function () {
                btnParcours.forEach(function (b) {
                    b.classList.remove("dashed");
                });
                this.classList.add("dashed");
            });
        });

        // btnCategories.forEach(function (btn) {
        //     btn.addEventListener("click", function () {
        //         btnCategories.forEach(function (b) {
        //             b.classList.remove("dashed");
        //         });
        //         this.classList.add("dashed");
        //     });
        // });

        var btnEstimation = document.querySelector('.btn-estimation');
        togglePredictions(btnEstimation);
        <?php if ($premierParcoursId): ?>
        filtrerParParcours('<?php echo $premierParcoursId; ?>');
        <?php endif; ?>
    });

</script>
</body>

</html>