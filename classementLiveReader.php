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
                     iei.categorie, ep.horaireDepart
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


$readerInfo = getReaderInfo($idReader);
if (!$readerInfo) {
    die("Reader non trouvé");
}

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

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet"/>
    <link href="assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="assets/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet"/>
    <link href="assets/css/style.min.css" rel="stylesheet"/>
    <link href="assets/css/style-responsive.min.css" rel="stylesheet"/>
    <link href="assets/css/classementLive.css" rel="stylesheet"/>

    <script src="assets/plugins/jquery/jquery-1.9.1.min.js"></script>

    <style>
        #tableau {
            font-size: 14px;
        }
        @media (max-width: 576px) {
            #tableau {
                font-size: 12px;
            }
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
                <h2><?php echo htmlspecialchars($infos_epreuve['nomEpreuve']); ?></h2>
                <p class="text-muted"><?php echo date('d/m/Y', strtotime($infos_epreuve['dateEpreuve'])); ?></p>
                <span>
                    <?php echo htmlspecialchars($readerInfo['lieu']); ?>
                    - <?php echo $readerInfo['distance_depart']; ?> km
                </span>
            </div>

            <div class="text-center mb-3">
                <span>
                    <?php echo count($classement); ?> coureurs passés
                </span>
            </div>

            <div class="content-title" style="display : flex; flex-direction: column; justify-content : center;">

                <div class="input-group input-group-lg mb-3">
                    <input class="form-control" style="text-align: center;" type="text"
                           placeholder="Rechercher un nom, prénom ou n° de dossard..." id="maRecherche"
                           onKeyUp="filtrer()">
                </div>
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
            </div>


            <div class="table-responsive">
                <table class="table table-striped table-hover " id="tableau">
                    <thead>
                    <tr>
                        <th class="text-center align-middle">Place</th>
                        <th class="text-center align-middle">Nom / Prénom</th>
                        <th class="text-center align-middle d-none d-md-table-cell">Club</th>
                        <th class="text-center align-middle">Sexe</th>
                        <th class="text-center align-middle d-none d-sm-table-cell">Cat.</th>
                        <th class="text-center align-middle">Heure</th>
                        <th class="text-center align-middle">Temps</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $place = 1;
                    foreach ($classement as $placeClassement):
                        $temps = calculTemps($placeClassement['horaire'], $placeClassement['horaireDepart']);
                        $heure = date('H:i:s', strtotime($placeClassement['horaire']));
                        ?>
                        <tr data-sexe="<?php echo $placeClassement['sexeInternaute']; ?>">
                            <td class="text-center align-middle font-weight-bold"><?php echo $place++; ?></td>
                            <td class="align-middle">
                                <strong><?php echo htmlspecialchars($placeClassement['prenomInternaute'] . ' ' . $placeClassement['nomInternaute']); ?></strong>
                                <span> (<?php echo $placeClassement['dossard']; ?>)</span>
                            </td>
                            <td class="align-middle d-none d-md-table-cell"><?php
                                echo $placeClassement['clubInternaute'] ? htmlspecialchars($placeClassement['clubInternaute']) : '-';
                          ?></td>
                            <td class="text-center align-middle">
                                <?php echo ($placeClassement['sexeInternaute'] == 'M') ? '<i class="fa fa-male text-primary"></i>' : '<i class="fa fa-female" style="color: #EE5588"></i>'; ?>
                            </td>
                            <td class="text-center align-middle d-none d-sm-table-cell">
                                <span ><?php echo $placeClassement['categorie']; ?></span>
                            </td>
                            <td class="text-center align-middle"><?php echo $heure; ?></td>
                            <td class="text-center align-middle font-weight-bold text-blue"><?php echo $temps; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($classement)): ?>
                <div class="alert alert-warning text-center">
                    Aucun coureur n'est encore passé par ce lieu.
                </div>
            <?php endif; ?>

            <div class="text-center mt-4 mb-4">
                <a href="classementLive.php?idEpreuve=<?php echo $idEpreuve; ?>" class="btn btn-secondary">
                    Retour au classement général
                </a>
            </div>

        </div>
    </div>

    <?php @include('../footer.php'); ?>
</div>

<script>
    let currentSexe = '';
    let currentRecherche = '';

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

    function appliquerFiltres() {
        var lignes = document.querySelectorAll("#tableau tbody tr");

        for (var i = 0; i < lignes.length; i++) {
            var sexeLigne = lignes[i].getAttribute("data-sexe");
            var cellule = lignes[i].getElementsByTagName("td")[1];
            var texte = cellule ? cellule.innerText.toUpperCase() : "";
            var passeSexe = !currentSexe || sexeLigne === currentSexe;
            var passeRecherche = !currentRecherche || texte.indexOf(currentRecherche) > -1;


            lignes[i].style.display = (passeSexe && passeRecherche) ? "" : "none";
        }
    }


</script>

<script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>