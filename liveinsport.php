<?php
//ini_set("display_errors", 1);
//error_reporting(E_ALL);
//ini_set("display_startup_errors", 1);

require_once("includes/includes.php");
require_once("includes/functions.php");
global $mysqli;

$idEpreuve = $_GET['idEpreuve'];

if ($_SESSION["typeInternaute"] == 'admin' || $_SESSION["typeInternaute"] == 'super_organisateur' || $_SESSION["typeInternaute"] == 'organisateur') {
    $admin = 1;
}

$nomEpreuve = extract_champ_epreuve("nomEpreuve", $idEpreuve);
$dateEpreuve = extract_champ_epreuve("dateEpreuve", $idEpreuve);

function recuperationHeureDebutFinLieu($idEpreuve, $lieu)
{
    global $mysqli;
    //récupération des informations du lecteur pour ce lieu
    $reqHeurePassageLieu = "SELECT * FROM live_reader
                                WHERE idEpreuve = ? AND lieu = ?";

    $stmt = $mysqli->prepare($reqHeurePassageLieu);
    $stmt->bind_param("is", $idEpreuve, $lieu);
    $stmt->execute();
    $req_prep = $stmt->get_result();
    $heureEpreuveLieu = mysqli_fetch_assoc($req_prep);


    //récupération des données relatives au premier et au dernier passage sur ce lieu
    $reqTempsPassage = "SELECT liv.lieu, MIN(horaire) AS minTemps, MAX(horaire) AS maxTemps
    FROM live_Horaire liv
    INNER JOIN live_reader cl ON liv.idEpreuve = cl.idEpreuve AND liv.lieu = cl.lieu
    WHERE liv.passage <= cl.nb_passage
    AND cl.date_min < liv.horaire
    AND cl.date_max > liv.horaire
    AND liv.idEpreuve = ?
    AND liv.lieu = ?";

    $stmt = $mysqli->prepare($reqTempsPassage);
    $stmt->bind_param("is", $idEpreuve, $lieu);
    $stmt->execute();
    $req_prep = $stmt->get_result();
    $resultatPassage = mysqli_fetch_assoc($req_prep);

    //récupération des heures du premier passage
    if ($resultatPassage['minTemps'] <> "") {
        $hdebut = $resultatPassage['minTemps'];
    } else $hdebut = $heureEpreuveLieu['date_min'];

    if ($resultatPassage['minTemps'] <> "") {
        $hfin = $resultatPassage['maxTemps'];
    } else $hfin = $heureEpreuveLieu['date_max'];

    $premierPassage = explode(' ', $hdebut);
    $premierPassage = explode(':', $premierPassage[1]);
    $infoLecteur['premierPassage'] = $premierPassage[0] . "h" . $premierPassage[1];
    //récupération des heures du dernier passage
    $dernierPasssage = explode(' ', $hfin);
    $dernierPasssage = explode(':', $dernierPasssage[1]);
    $infoLecteur['dernierPassage'] = $dernierPasssage[0] . "h" . $dernierPasssage[1];

    return $infoLecteur;
}

//récupération du nombre de coureur qui ont franchit un point de passage
function recuperationNbCoureurs($idEpreuve, $lieu)
{
    global $mysqli;
    $req_nbCoureurs = "
    SELECT COUNT(DISTINCT liv.idInscription) as nbCoureurs
    FROM live_Horaire liv
    INNER JOIN live_reader cl ON liv.idEpreuve = cl.idEpreuve AND liv.lieu = cl.lieu
    INNER JOIN r_inscriptionepreuveinternaute iei ON liv.idInscription = iei.idInscriptionEpreuveInternaute
    INNER JOIN r_epreuveparcours ep ON iei.idEpreuveParcours = ep.idEpreuveParcours
    WHERE liv.passage <= cl.nb_passage
    AND ep.horaireDepart < liv.horaire
    AND cl.date_min < liv.horaire
    AND cl.date_max > liv.horaire
    AND liv.idEpreuve = ?
    AND liv.lieu = ?";

    $stmt = $mysqli->prepare($req_nbCoureurs);
    $stmt->bind_param("is", $idEpreuve, $lieu);
    $stmt->execute();
    $result = $stmt->get_result();
    $req_result_nbCoureurs = mysqli_fetch_assoc($result);
    $nbCoureurs = $req_result_nbCoureurs['nbCoureurs'];

    return $nbCoureurs;
}

function recuperationNomLieu($idEpreuve)
{
    global $mysqli;
    $req_lieux = "SELECT DISTINCT id,cl.lieu, cl.idLecteur
            FROM live_reader cl
            WHERE cl.idEpreuve = ?
            AND (cl.clone = 0 OR cl.clone IS NULL)
            GROUP BY cl.lieu, cl.idLecteur
            ORDER BY ordre";

    $stmt = $mysqli->prepare($req_lieux);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $result = $stmt->get_result();
    $lieuxEpreuve = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $lieuxEpreuve;
}

$lieuxEpreuve = recuperationNomLieu($idEpreuve);
foreach ($lieuxEpreuve as $lieu) {
    $nom_lieu = $lieu['lieu'];
    $idLecteur = $lieu['idLecteur'];
}

//recuperation de l'affiche de la course
function recuperationAfficheEpreuve($idEpreuve)
{

    global $mysqli;
    $req_affiche = "SELECT nom_fichier FROM r_epreuvefichier
                    WHERE idEpreuve = ? AND type='photo_epreuve' ORDER BY idEpreuveFichier DESC LIMIT 1;";
    $stmt = $mysqli->prepare($req_affiche);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $prep_req = $stmt->get_result();
    $affiche = mysqli_fetch_assoc($prep_req);

    return $affiche['nom_fichier'];
}

function recuperationDepartements($idEpreuve)
{
    global $mysqli;
    $req_departement = "SELECT ville, nom FROM r_epreuve re 
                        JOIN departements d ON re.departement = d.num_departement
                        WHERE idEpreuve = ?";

    $stmt = $mysqli->prepare($req_departement);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    $req_departement_d = $stmt->get_result();
    $departement = mysqli_fetch_array($req_departement_d);

    return $departement;
}

$departement = recuperationDepartements($idEpreuve);

function checkIfResultatExist($id_epreuve)
{
    global $mysqli;
    $query = "SELECT * FROM r_resultats WHERE idEpreuve = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $id_epreuve);
    $stmt->execute();
    $result_epreuve_resultats = $stmt->get_result();
    if (mysqli_num_rows($result_epreuve_resultats) > 0) return true;
    else return false;
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


function getInfosEpreuve($idEpreuve)
{
    global $mysqli;
    $query = 'SELECT nomEpreuve, dateEpreuve, ville FROM r_epreuve WHERE idEpreuve = ?';
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $idEpreuve);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$infos_epreuve = getInfosEpreuve($idEpreuve);
$listeParcours = getParcours($idEpreuve);
$premierParcoursId = isset($_GET['parcours']) ? (int)$_GET['parcours'] : (!empty($listeParcours) ? $listeParcours[0]['idEpreuveParcours'] : null);
?>

<!DOCTYPE html>
<!--[if IE 8]>
<html lang="en" class="ie8"> <![endif]-->
<!--[if !IE]><!-->
<html lang="fr">
<!--<![endif]-->

<head>

    <meta charset="utf-8"/>
    <title>ATS-SPORT | Chronométrage, inscriptions en ligne, dossards</title>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <meta content="chronométrage, chronométreur, inscriptions en ligne, dossards, course à pied, trail, cyclisme, cyclosportive, vtt, triathlon, duathlon"
          name="description"/>
    <meta content="" name="author"/>

    <!-- ================== BEGIN BASE CSS STYLE ================== -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet"/>
    <link href="assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="assets/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet"/>
    <link href="assets/css/animate.min.css" rel="stylesheet"/>
    <link href="assets/css/style_c.css" rel="stylesheet"/>
    <link href="assets/css/style-responsive.css" rel="stylesheet"/>
    <link href="assets/css/theme/blue.css" id="theme" rel="stylesheet"/>

    <link href='assets/css/style_live_luc.css' rel='stylesheet'/>
    <link href='assets/css/style_live_responsive_luc.css' rel='stylesheet'/>
    <link href='assets/css/pageLive.css' rel='stylesheet'/>
    <link href="assets/css/classementLive.css" rel="stylesheet"/>
    <link rel='stylesheet' href='https://unpkg.com/leaflet@1.7.1/dist/leaflet.css'
          integrity='sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=='
          crossorigin=''/>
    <!-- ================== END BASE CSS STYLE ================== -->

    <!-- ================== BEGIN PAGE LEVEL STYLE ================== -->
    <link href="assets/plugins/DataTables/media/css/dataTables.bootstrap.min.css" rel="stylesheet"/>
    <link href="assets/plugins/DataTables/extensions/Responsive/css/responsive.bootstrap.min.css" rel="stylesheet"/>
    <!-- ================== END PAGE LEVEL STYLE ================== -->
    <!-- ================== BEGIN BASE JS ================== -->
    <script src='assets/plugins/jquery/jquery-1.9.1.min.js'></script>
    <script src='assets/plugins/jquery/jquery-migrate-1.1.0.min.js'></script>
    <script src='assets/plugins/jquery-ui/ui/minified/jquery-ui.min.js'></script>
    <!--<script defer type='text/javascript' src='../assets/js/scriptCreation.js'></script>-->
    <!-- ================== END BASE JS ================== -->

    <style>
        #tableau_lieux td {
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>

<body data-spy='scroll' data-target='#header-navbar' data-offsuivreet='51' id='body_accueil'>
<div id="page-container">
    <?php include('header.php'); ?>
    <div style="margin-top:90px;"></div>
    <div id='tableau_acceuil' class='container-fluid'
         style="display : flex; flex-direction : column; align-items : center;">
        <div class='col-md-10 col-xs-12'>
            <div style="display : flex; justify-content : center;">
                <h1 id='titre_epreuve_live'>
                    <strong><span style="color : red">LIVE : </span><?php echo $nomEpreuve ?></strong>
                </h1>
                <button class="interactive-zone-1" type="button" style="top : 10px;"></button>
            </div>
            <div style='display : flex; justify-content : space-between;'>
                <p class='col-md-4 col'><?php echo date('d/m/Y', strtotime($dateEpreuve)) ?></p>
                <p class='col-md-4 col'>Bienvenue en <?php echo $departement['nom'] ?></p>
            </div>
        </div>
    </div>
    <div class='container-fluid' style="max-width: 75%;">
        <div class='row' style="display : flex; justify-content : space-between; align-items : center;">
            <!-- begin #carte -->
            <div id='carte' class='content col-md-6 col-sm-12 col-xs-12' data-scrollview='true'>
                <?php
                if (recuperationAfficheEpreuve($_GET['idEpreuve']) == '') $nomAffiche = "defaut_image_epreuve_occitanie.png";
                else $nomAffiche = recuperationAfficheEpreuve($_GET['idEpreuve']); ?>
                <img src="../admin/fichiers_epreuves/<?= $nomAffiche; ?>" class="img_epreuve" id="img-home"
                     alt="image par défaut">
                <div id='idEpreuve' style='display: none;'></div>
                <div id='temp' style='display: none;'></div>
            </div>
            <?php
            //$lien_video = extract_champ_epreuve("lienVideo1", $idEpreuve);
            ?>
            <!--colone de droite-->
            <div class='col-md-6 col-sm-12 col-xs-12 text-left'>
                <!-- On intègre ici la vidéo -->
                <div class="embed-responsive embed-responsive-16by9" id="twitch-embed">
                    <?php

                    $youtube = 2; // Autre que 1 on est sur la chaine twitch
                    date_default_timezone_set('Europe/Paris');
                    $today = date("Y-m-d H:i:s");
                    $fin = "2026-03-13 08:30:00";
                    $debut = "2026-03-13 19:25:00";
                    //Ancienne condition if ($youtube == 1 &&) {
                    if ($today > $fin && $today < $debut) {
                        ?>

                        <!-- <iframe class="embed-responsive-item" width="700" height="420" src="https://mymotion.dotvision.com/play?s=172421" title="Suivi Live Tracker GPS" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe> -->
                        <script src="https://player.twitch.tv/js/embed/v1.js"></script>
                        <script type="text/javascript">
                            new Twitch.Player("twitch-embed", {
                                channel: "atssport34",
                                width: 700,
                                height: 420,
                            });
                        </script>
                    <?php
                    } else {
                    ?>
                        <iframe class="embed-responsive-item" width="700" height="420"
                                src="https://www.youtube.com/embed/bTnOL1l3dqo?si=RwJ2FWvB7hWlImL3"
                                title="YouTube video player" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen></iframe>
                        <?php
                    }
                    ?>
                </div>
                <!-- <div style="text-align:center;"><a href ="https://mymotion.dotvision.com/play?s=172421" target="_blank">Full Carto Tracking GPS</a></div> -->
                <div>
                    <?php if (!empty($idEpreuve)) {
                        $message_resultat_exist = "Résultats imminents";
                        if (checkIfResultatExist($idEpreuve)) {
                            $message_resultat_exist = "Résultats officiels";
                        }
                        ?>
                        <a id='lien_classement_off' href='../resultats.php?id_epreuve=<?php echo $idEpreuve; ?>'>
                            <button class="buttonPageLive"
                                    id='bouton_lien_classement_off'><?php echo $message_resultat_exist; ?></button>
                        </a>
                    <?php } else { ?>
                        <a id='lien_classement_off' href='https://www.ats-sport.com/resultats.php?id_epreuve'>
                            <button class="buttonPageLive" id='bouton_lien_classement_off'> Résultats officiels</button>
                        </a>
                    <?php } ?>
                </div>
                <div id='info_epreuve' class='col-md-12 col-xs-12 col-lg-12'>

                    <div id='info_epreuve_coureur'>
                        <h5> Suivre un participant</h5>
                        <div id='modal_coureur'></div>
                        <div style='margin-bottom:5px'>
                            <input type='text' placeholder='Nom ou Dossard' id='info_epreuve_donnees'
                                   style='text-align:center;width: 150px; max-width: 150px'>

                            <!--Trigger popup coureur-->
                            <input type='submit' id='trouver_coureur' data-toggle='modal' data-target='#popup'
                                   name="trouver_coureur" value="Afficher"></button>
                            <!-- <button type="button" class="btn btn-link">
                                    <a href='../liste_des_inscrits.php?id_epreuve=<?php echo $idEpreuve; ?>' target="_blank">Voir la liste des engagés</a>
                                </button> -->
                            <!-- Pop-up -->
                            <div id='popup' class='modal' style='width:100%;max-height: unset;'>
                                <div id='modal_conteneur_coureur'>
                                    <div id='body_popup'></div>
                                    <button type='button' id='btn_dismissModal' class='btn btn-secondary'
                                            data-dismiss='modal'>Fermer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr style='border: 2px solid black;width: 80%'>
                </div>
                <div class="d-flex justify-content-between">

                    <a href="classementLive.php?idEpreuve=<?php echo $idEpreuve; ?>" class="btn btn-secondary">
                        Retour au classement général
                    </a>
                    <a href="liveinsport.php?idEpreuve=<?php echo $idEpreuve; ?>" class="btn btn-secondary">
                        Suivre plus de lieux
                    </a>
                    <a href="https://ats-sport.com/liste_des_inscrits.php?id_epreuve=<?php echo $idEpreuve; ?>&course=<?php echo strtolower($infos_epreuve['nomEpreuve']); ?>" class="btn btn-secondary">Liste des engagés</a>
                </div>
                <!--début tableau-->
                <h4 class='text-center text-dark mb-3'>Retrouvez le live des différents points de contrôles</h4>
                <div class="table-responsive">
                    <table id='tableau_lieux' class="table table-striped table-hover table-bordered">
                        <thead class="thead-dark">
                        <tr>
                            <th class="text-center align-middle">Lieux</th>
                            <th class="text-center align-middle d-none d-md-table-cell">Premier passage</th>
                            <th class="text-center align-middle d-none d-md-table-cell">Dernier passage</th>
                            <th class="text-center align-middle">Passages</th>
                            <th class="text-center align-middle">
                                <i class="fa fa-eye"></i>
                            </th>
                        </tr>
                        </thead>
                        <tbody id="tbody_info_lieu">
                        <?php
                        foreach ($lieuxEpreuve as $lieu) {
                            $infoLecteurEpreuve = recuperationHeureDebutFinLieu($idEpreuve, $lieu['lieu']);
                            $nbCoureurs = recuperationNbCoureurs($idEpreuve, $lieu['lieu']);
                            $lienClassement = "classementLiveReader.php?idEpreuve=" . $idEpreuve . "&idReader=" . $lieu['id'] ."&parcours=" . $premierParcoursId;
                            ?>
                            <tr>
                                <td class="align-middle" >
                                    <a href="<?php echo $lienClassement; ?>" class="d-flex align-items-center text-dark" style="text-decoration: none;">
                                        <i class="fa fa-map-marker text-danger" style="font-size: 1.2rem;"></i>
                                        <div class="text-left" style="align-items: center">
                                            <div class="text-muted font-weight-bold" style="font-size: 1.2rem; text-transform: uppercase;">Temps de passage pour</div>
                                            <div class="font-weight-bold" style="font-size: 1.1rem; color: #348fe2;">
                                                <?php echo htmlspecialchars($lieu['lieu']); ?>
                                            </div>
                                        </div>
                                    </a>

                                </td>
                                <td class="text-center align-middle d-none d-md-table-cell">
                                    <?php echo $infoLecteurEpreuve['premierPassage']; ?>
                                </td>
                                <td class="text-center align-middle d-none d-md-table-cell">
                                    <?php echo $infoLecteurEpreuve['dernierPassage']; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <span>
                                        <?php echo ($nbCoureurs == 0) ? "-" : $nbCoureurs; ?>
                                        </span>
                                </td>
                                <td class="text-center align-middle">
                                    <a href='classementLive.php?idEpreuve=<?php echo  $idEpreuve ?>' class="btn btn-outline-primary btn-sm">
                                        VOIR
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
                <!--                    --><?php //if ($_SESSION["typeInternaute"] == 'admin') {
                //                        echo    '<div class="content col-md-12 col-sm-12 col-xs-12">
                //                                            <a class="btn btn-danger center-block" role="button"
                //                                            href="https://www.ats-sport.com/admin/resultats.php?id_epreuve=' . $idEpreuve . '">Configuration (Admin) des résultats</a>
                //                                </div>';
                //                    }   ?>
            </div>
        </div>
    </div><!--fin conteneur fluid-->

    <!-- footer -->
    <?php include('footer.php') ?>

</div>
<!-- end page-container -->

<!-- ================== BEGIN BASE JS ================== -->
<script src='assets/plugins/bootstrap/js/bootstrap.min.js'></script>
<script src='assets/plugins/isotope/jquery.isotope.min.js'></script>
<script src='assets/plugins/lightbox/js/lightbox-2.6.min.js'></script>
<script src='assets/plugins/datetimepicker-master/jquery.datetimepicker.js' type='text/javascript'></script>
<script src='assets/plugins/DataTables/media/js/jquery.dataTables.js'></script>
<script src='assets/plugins/DataTables/media/js/dataTables.bootstrap.min.js'></script>
<script src='assets/plugins/DataTables/extensions/RowReorder/js/dataTables.rowReorder.min.js'></script>
<script src='assets/plugins/DataTables/extensions/Responsive/js/dataTables.responsive.min.js'></script>
<script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/1.3.0/jquery.min.js'></script>
<script src='assets/plugins/jquery-cookie/jquery.cookie.js'></script>
<!-- <script src='../assets/plugins/scrollMonitor/scrollMonitor.js'></script> -->
<script src='assets/js/apps.js'></script>
<script src='assets/js/live_luc.js'></script>

<!-- ================== LEAFLET ================== -->

<script>
    // fonction jquery ajax qui appelle le script PHP popupPageLive.php pour renvoyer les informations du coureur taper dans la barre de recherche
    $(document).ready(function () {
        $('#trouver_coureur').click(function () {
            let input = $('#info_epreuve_donnees').val();
            let idEpreuve = <?php echo $idEpreuve ?>;
            if (input != "") {
                $.ajax({
                    url: "popupPageLive.php",
                    method: "GET",
                    data: {
                        input: input,
                        idEpreuve: idEpreuve
                    },
                    success: function (data) {
                        $("#body_popup").html(data);
                    }
                });
            } else {
                $("#popup").css("display", "none");
            }
        })

    });

    // let url = window.location.href;
    // let idEp = url.split('/');
    // idEp = idEp[4];
    // let div = document.getElementById('idEpreuve');
    // div.textContent = idEp;

    // if (document.readyState == 'loading') {
    //     initCarte();
    // }

    //cache le graphique si erreur sur image
    // function getHidden() {
    //     document.getElementById('graphiqueParParcours').style.display = 'none';
    // }
    //incrémentation et affichage pour le compteur de vues
    // let nbVue = document.getElementById('nbre_vue');

    // let redirection = window.location.href; /*URL de redirection sur la page d'acceuil si l'utilisateur refuse*/

    // let titreDoc = document.getElementById('titre_epreuve_live');
    // /*récupération du titre de l'épreuve*/
    // titreDoc = titreDoc.childNodes[1].textContent;

    /*récupération de lidEpreuve avec le lien URL de la page
     * on split le tableau pour récupérer que l'id*/
    // let idEpreuve = window.location.href;
    // idEpreuve = idEpreuve.split('/');
    // idEpreuve = idEpreuve[4];

    // let dataEpreuve = titreDoc + '/' + idEpreuve; //regroupement des deux valeurs récupérées

    // function Changelieu() {
    //     let ask = window.confirm('voulez-vous accèder au live?');
    //     if (ask) {
    //         setCompteurVue(dataEpreuve);
    //     } else {
    //         window.location.href = redirection;
    //         alert(redirection);
    //     }
    // }
    // window.onload = function() {
    //     getCompteur(nbVue, idEpreuve);
    // }
</script>
<!-- ================== END BASE JS ================== -->

</body>

</html>