<?php
require_once("includes/includes.php");
require_once("includes/functions.php");

global $mysqli;

$idEpreuve = isset($_GET['idEpreuve']) ? (int)$_GET['idEpreuve'] : 0;
$idReader = isset($_GET['idReader']) ? (int)$_GET['idReader'] : 0;

function getReaderInfo($idReader)
{
    global $mysqli;
    $query = "SELECT id, lieu, distance_depart, idParcours, idEpreuve, date_min, date_max, nb_passage
              FROM live_reader WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $idReader);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
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


function getClassementLieu($idEpreuve, $lieu)
{
    global $mysqli;
    $query = "SELECT lh.dossard, lh.horaire, lh.passage, lh.idInscription,
                     ri.nomInternaute, ri.prenomInternaute, ri.sexeInternaute, ri.clubInternaute,
                     iei.categorie, ep.horaireDepart,ep.idEpreuveParcours,ri.villeInternaute, ri.paysInternaute, ep.nomParcours,iei.equipe
              FROM live_Horaire lh
              INNER JOIN r_inscriptionepreuveinternaute iei ON lh.idInscription = iei.idInscriptionEpreuveInternaute
              INNER JOIN r_internaute ri ON iei.idInternaute = ri.idInternaute
              INNER JOIN live_reader cl ON lh.idEpreuve = cl.idEpreuve AND LOWER(lh.lieu) = LOWER(cl.lieu)
              INNER JOIN r_epreuveparcours ep ON iei.idEpreuveParcours = ep.idEpreuveParcours
              WHERE lh.idEpreuve = ?
                AND LOWER(lh.lieu) = LOWER(?)
                AND ep.horaireDepart < lh.horaire
                AND cl.date_min < lh.horaire
                AND cl.date_max > lh.horaire
                AND lh.passage <= cl.nb_passage
              GROUP BY ri.idInternaute
              ORDER BY lh.horaire ASC";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("is", $idEpreuve, $lieu);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

function recuperationNbCoureurs($idEpreuve, $lieu, $idParcours)
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
    AND liv.lieu = ?
    AND cl.idParcours = ?";

    $stmt = $mysqli->prepare($req_nbCoureurs);
    $stmt->bind_param("isi", $idEpreuve, $lieu, $idParcours);
    $stmt->execute();
    $result = $stmt->get_result();
    $req_result_nbCoureurs = mysqli_fetch_assoc($result);
    $nbCoureurs = $req_result_nbCoureurs['nbCoureurs'];

    return $nbCoureurs;
}

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

$readerInfo = getReaderInfo($idReader);
if (!$readerInfo) {
    die("Reader non trouvé");
}

$listeParcours = getParcours($idEpreuve);
$infos_epreuve = getInfosEpreuve($idEpreuve);
$classement = getClassementLieu($idEpreuve, $readerInfo['lieu']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <title>ATS-SPORT | <?php echo htmlspecialchars($readerInfo['lieu']); ?>
        - <?php echo htmlspecialchars($infos_epreuve['nomEpreuve']); ?></title>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>

    <link href="assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="assets/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet"/>
    <link href="assets/css/animate.min.css" rel="stylesheet"/>
    <link href="assets/css/style_c.css" rel="stylesheet"/>
    <link href="assets/css/theme/blue.css" id="theme" rel="stylesheet"/>
    <link href="assets/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css" rel="stylesheet"/>
    <link href="assets/css/classementLive.css" rel="stylesheet"/>
    <script src="assets/plugins/jquery/jquery-1.9.1.min.js"></script>
    <script src="assets/plugins/jquery/jquery-migrate-1.1.0.min.js"></script>
    <script src="assets/plugins/jquery-ui/ui/minified/jquery-ui.min.js"></script>

    <style>
        #tableau {
            font-size: 14px;
        }

        @media (max-width: 576px) {
            #tableau {
                font-size: 12px;
            }
        }

        .dashed {
            border-bottom: 3px dashed;
        }

    </style>
</head>

<body>
<div id="page-container" style="background:rgb(225, 225, 225)">
    <?php include('header.php'); ?>

    <div style="margin-top:60px;"></div>
    <div class="content" style="margin-top:20px;">
        <div class="container-fluid" style="max-width: 90%;">

            <div class="text-center mb-4">
                <h2><?php echo htmlspecialchars($infos_epreuve['nomEpreuve']) . " - " ; ?>
                    <?php echo htmlspecialchars($readerInfo['lieu']); ?>
                    - <?php echo $readerInfo['distance_depart']; ?> km
                </h2>
                <p class="text-muted"><?php echo date('d/m/Y', strtotime($infos_epreuve['dateEpreuve'])); ?></p>
            </div>


            <div class="content-title" style="display : flex; flex-direction: column; justify-content : center;">
                <div class="input-group input-group-lg mb-3">
                    <input class="form-control" style="border-radius: 8px;text-align: center" type="text"
                           placeholder="Rechercher un nom, prénom ou n° de dossard..." id="maRecherche"
                           onKeyUp="filtrer()" >
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
            </div>


            <?php
            $listeParcours = getParcours($idEpreuve);
            $premierParcoursId = isset($_GET['parcours']) ? (int)$_GET['parcours'] : (!empty($listeParcours) ? $listeParcours[0]['idEpreuveParcours'] : null);
            foreach ($listeParcours as $p) {
                $isActive = ($p['idEpreuveParcours'] == $premierParcoursId) ? 'dashed' : '';
                echo "<a href='?idEpreuve=" . $idEpreuve . "&parcours=" . $p['idEpreuveParcours'] . "&idReader=" . $idReader . "' class='btn btn-outline-secondary m-b-10 m-r-10 " . $isActive . "' style='background:rgba(238,238,238,.3); color:black;'>" . $p['nomParcours'] . "</a>";
            }
            ?>



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
                <button class="btn btn-secondary">
                    <?php echo recuperationNbCoureurs($idEpreuve, $readerInfo['lieu'], $premierParcoursId) ?> coureurs passés
                </button>
            </div>


            <div class="table-responsive">
                <table class="table table-striped table-hover" id="tableau">
                    <thead>
                    <tr>
                        <th class="text-center align-middle">Place</th>
                        <th class="text-center align-middle">Nom / Name</th>
                        <th class="text-center align-middle d-none d-md-table-cell">Club / Team</th>
                        <th class="text-center align-middle">Heure</th>
                        <th class="text-center align-middle">Temps</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $classementFiltre = array_filter($classement, function($c) use ($premierParcoursId) {
                        return !$premierParcoursId || $c['idEpreuveParcours'] == $premierParcoursId;
                    });

                    if (empty($classementFiltre)) {
                        echo "<tr><td colspan='5' class='text-center'>Aucun coureur n'est encore passé par ce lieu.</td></tr>";
                    }

                    $place = 1;
                    foreach ($classement as $placeClassement) {
                        if ($readerInfo['distance_depart'] > 0) {
                            $temps = calculTemps($placeClassement['horaire'], $placeClassement['horaireDepart']);
                        } else {
                            $temps = '00:00:00';
                        }
                        $heure = date('H:i:s', strtotime($placeClassement['horaire']));
                        if ($premierParcoursId && $placeClassement['idEpreuveParcours'] != $premierParcoursId) {
                            continue;
                        }
                        ?>

                        <?php
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
                        } else {
                            $club = ($placeClassement['clubInternaute'] ?: "-") . "</br>" . $placeClassement['villeInternaute'];
                            $cat = "<span id='cat'>" . "&nbsp;&nbsp;<b>" . getflag($placeClassement['paysInternaute']) . (($placeClassement['sexeInternaute'] == "M") ? "<i class='fa fa-male' ;></i>" : "<i class='fa fa-female' style='color:#f50666;'></i>") . " - " . $placeClassement['categorie'] . "</b>&nbsp;&nbsp;(" . $placeClassement['dossard'] . ")</span>";
                            $nom = "<b>" . $placeClassement['prenomInternaute'] . "</span>&nbsp;<span id='prenom'>" . $placeClassement['nomInternaute'] . "</b></br>" . $cat . "</span>";
                        }
                        ?>
                        <tr data-sexe="<?php echo $placeClassement['sexeInternaute']; ?>"
                            data-parcours="<?php echo $placeClassement['idEpreuveParcours']; ?>">

                            <td class='text-center' style="vertical-align:middle;">
                                <span title="<?php echo $placeClassement['passage']; ?>° passage"
                                      style="color:#348fe2; font-size:38px; font-weight:normal;"><?php echo $place++; ?></span>
                            </td>
                            <?php
                            echo "<td class='text-center' style='vertical-align:middle;color:black;font-size:17px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'><b>" . $nom . "</b></td>";
                            echo "<td class='text-center d-none d-md-table-cell' style='vertical-align:middle;color:black;font-size:15px;font-weight:normal;' title='" . $placeClassement['passage'] . "° passage'>" . $club . "</td>";
                            echo '<td class="text-center" style="vertical-align:middle;color:black;font-size:17px;font-weight:normal;">' . $heure . '</td>';
                            echo '<td class="text-center" style="vertical-align:middle;color:#348FE2FF;font-size:17px;font-weight:normal;">' . $temps . '</td>';


                            ?>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($classement)): ?>
                <div class="alert alert-warning text-center">
                    Aucun coureur n'est encore passé par ce lieu pour ce parcours.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php @include('../footer.php'); ?>
</div>

<script>
    let currentSexe = '';
    let currentRecherche = '';
    let currentParcours = '';

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

    function filtrer() {
        currentRecherche = document.getElementById("maRecherche").value.toUpperCase();
        appliquerFiltres();
    }

    function filtrerParParcours(idParcours) {
        window.location.href = '?idEpreuve=<?php echo $idEpreuve; ?>&parcours=' + idParcours;
    }

    function appliquerFiltres() {
        var lignes = document.querySelectorAll("#tableau tbody tr");

        for (var i = 0; i < lignes.length; i++) {
            var sexeLigne = lignes[i].getAttribute("data-sexe");
            var cellule = lignes[i].getElementsByTagName("td")[1];
            var texte = cellule ? cellule.innerText.toUpperCase() : "";
            var passeSexe = !currentSexe || sexeLigne === currentSexe;
            var passeRecherche = !currentRecherche || texte.indexOf(currentRecherche) > -1;
            var parcoursLigne = lignes[i].getAttribute("data-parcours");
            var passeParcours = !currentParcours || parcoursLigne === currentParcours;


            lignes[i].style.display = (passeSexe && passeRecherche && passeParcours) ? "" : "none";
        }
    }


</script>

<script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>