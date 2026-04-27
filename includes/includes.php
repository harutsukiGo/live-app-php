<?php 

//Include de admin/

// ini_set("display_errors", 0);
// error_reporting(E_ERROR); 
// HACK VARIABLES GLOBALES
if(isset($_GET)) $_GET = $_GET;
if(isset($_POST)) $_POST = $_POST;
if(isset($_FILES)) $HTTP_POST_FILES = $_FILES;
if(isset($_COOKIE)) $HTTP_COOKIE_VARS=$_COOKIE;
if(isset($_SESSION)) $HTTP_SESSION_VARS=$_SESSION;
if(isset($_ENV)) $HTTP_ENV_VARS=$_ENV;
if(isset($_SERVER)) $HTTP_SERVER_VARS=$_SERVER; 
// FIN HACK GLOBALES

require(__DIR__ . "/../connect_db.php");
require(__DIR__ . "/slashes.php");
require_once(__DIR__ . "/toolbox.php");

//ini_set('session.cookie_secure', 'true');
//session_set_cookie_params(['samesite' => 'None']);
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'true');
session_start();
connect_db();
global $mysqli;
//echo "xxxx".$mysqli;
	$p = new stdClass();
	$p->javascript = array();
	$p->debug = false;
	$p->centre = "";
	$p->query = array();
		

class Cri_Nonce {

 

    /**

     * @var int DUREE_SECONDES Durée de validité du nonce, en secondes.

     */

    const DUREE_SECONDES = 7200;
	
 

    // ***********************************************************

    /**

     * Créer le nonce.

     *

     * @param string $action      Chaîne de caractères qui identifie l'action que le formulaire ou le lien doit faire. La valeur de cette chaîne a peu d'importance. Son rôle est d'augmenter la sécurité du nonce. Dans le cas où le nonce sert à protéger un lien menant à une action sur un item de la BD (ex : lien pour supprimer un produit), la clé devrait inclure l'identifiant de cet item (ex : 'supprimer_8', 'enregistrer', 'modifier_3').

     * @param string $usager      Code ou identifiant de l'usager qui était authentifié lors de la création initiale ou lors de la vérification du nonce.

     * @param mysqli $mysqli      Objet mysqli qui permettra d'accéder à la base de données pour enregistrer ou retrouver des valeurs dans la table nonce.

 

     * @return string Valeur du nonce ou '' si la création n'a pas réussi.

     */

    public static function creer_nonce($action, $usager, $mysqli) {

        // sous PHP 7, il est possible d'utiliser random_bytes(), qui, selon la doc : Generates cryptographically secure pseudo-random bytes

        if (function_exists('random_bytes')) {

            $salage = random_bytes(32);

        }

        else {

            // attention : avec openssl_random_pseudo_bytes(), il arrive que l'algorithme fort de cryptologie ne puisse pas être été utilisé

            if (function_exists('openssl_random_pseudo_bytes')) {

                $salage = openssl_random_pseudo_bytes(32);

            }

            // à défaut d'une meilleure solution, on utilise une fonction qui ne génère pas de valeurs sécurisées d'un point de vue cryptologie

            else {

                $salage = mt_rand();

            }

        }

 

        $expiration = time() + self::DUREE_SECONDES;

        $valeur = self::valeur_nonce($action, $usager, $salage);

 

        if (!self::enregistrer_nonce_bd($valeur, $salage, $expiration, $mysqli)) {

            $valeur = '';

        }

 

        return $valeur;

    }

 

    // ***********************************************************

    /**

     * Vérifier si le nonce est valide en créant un nouveau nonce et en le comparant avec la valeur originale.

     *

     * @param string $valeur      Valeur du nonce.

     * @param string $action      Chaîne de caractères qui identifie l'action que le formulaire ou le lien doit faire.

     * @param string $usager      Code ou identifiant de l'usager qui était authentifié lors de la vérification du nonce.

     * @param mysqli $mysqli      Objet mysqli qui permettra d'accéder à la base de données pour enregistrer ou retrouver des valeurs dans la table nonce.

     *

     * @return bool Indique si le nonce est valide.

     */

    public static function verifier_nonce($valeur, $action, $usager, $mysqli) {

        $valide = false;

 

        // retrouver les informations sur le nonce dans la base de données

        $reussi = false;

 

        $requete = "SELECT nonce_salage, nonce_expiration FROM nonce WHERE nonce_valeur = ?";

        $stmt = $mysqli->prepare($requete);

 

        if ($stmt) {

 

            $stmt->bind_param("s", $valeur);

 

            $stmt->execute();

            $stmt->bind_result($salage, $expiration);

            $stmt->fetch();

 

            if ($stmt->num_rows != -1) {

                $reussi = true;

            }

 

            $stmt->close();

        }

 

        if ($reussi) {

            // si le nonce n'a pas expiré

            if ($expiration > time()) {

 

                // recrée le nonce pour pouvoir le comparer à l'original

                $nouvelle_valeur = self::valeur_nonce($action, $usager, $salage);

 

                if ($nouvelle_valeur == $valeur) {

                    $valide = true;

                }

            }

 

            // supprime le nonce de la BD ainsi que tous les nonces qui sont expirés

            self::nettoyer_bd($valeur, $mysqli);

        }

 

        return $valide;

    }

 

    // ***********************************************************

    /**

     * Monter la valeur du nonce.

     *

     * @param string $action      Chaîne de caractères qui identifie l'action que le formulaire ou le lien doit faire.

     * @param string $usager      Code ou identifiant de l'usager qui était authentifié lors de la création initiale ou lors de la vérification du nonce.

     * @param string $salage      Clé de salage utilisée lors de la génération initiale.

 

     * @return string Valeur du nonce.

     */

    private static function valeur_nonce($action, $usager, $salage) {

        return sha1($action . $usager . $salage);

    }

 

    // ***********************************************************

    /**

     * Enregistrer le nonce dans la base de données

     *

     * @param string $valeur      Valeur du nonce.

     * @param string $salage      Clé de salage utilisée lors de la génération initiale.

     * @param int $expiration     Expiration du nonce, mesurée en secondes depuis le début de l'époque UNIX (1er janvier 1970 00:00:00 GMT).

     * @param mysqli $mysqli      Objet mysqli qui permettra d'accéder à la base de données pour enregistrer les données dans la table nonce.

     *

     * @return bool Indique si l'enregistrement a réussi.

     */
    private static function enregistrer_nonce_bd($valeur, $salage, $expiration, $mysqli) {

 

        $reussi = false;

 

        $requete = "INSERT INTO nonce(nonce_valeur, nonce_salage, nonce_expiration) VALUES (?, ?, ?)";

        $stmt = $mysqli->prepare($requete);

 

        if ($stmt) {

 

            $stmt->bind_param("ssd", $valeur, $salage, $expiration);

 

            $stmt->execute();

 

            if ($stmt->affected_rows != -1) {

                $reussi = true;

            }

 

            $stmt->close();

        }

 

        return $reussi;

    }

 

    // ***********************************************************

    /**

     * Supprimer le nonce de la base de données, ainsi que tous les nonces qui sont expirés.

     *

     * @param string $valeur      Valeur du nonce.

     * @param mysqli $mysqli      Objet mysqli qui permettra d'accéder à la base de données pour supprimer des enregistrements de la table nonce.

     *

     * @return bool Indique si la suppression a réussi.

     */
    private static function nettoyer_bd($valeur, $mysqli) {

 

        $reussi = false;

 

        $requete = "DELETE FROM nonce WHERE nonce_expiration <= ? OR nonce_valeur = ?";

        $stmt = $mysqli->prepare($requete);

 

        if ($stmt) {

            $time = time();

 

            $stmt->bind_param("ds", $time, $valeur);

 

            $stmt->execute();

 

            if ($stmt->affected_rows != -1) {

                $reussi = true;

            }

 

            $stmt->close();

        }

 

        return $reussi;

    }

 

}   // fin de la définition de la classe





	//if((!isset($_GET['epre_id'])) || (!isset($_GET['file'])) || (isset($_GET['file']) && ($_GET['file'] != "epre_submit") && $_GET['file'] != "valide_tarif_submit" && $_GET['file'] != "valide_tarif" && (($_GET['file'] != "epre") || !isset($_POST['epre_nbparc']))))	
	/*if((!isset($_GET['epre_id']))  || !isset($_POST['epre_nbparc']))
	{

		unset($_SESSION['mod_epre_id']);
		unset($_SESSION['mod_epre_ids']);
		unset($_SESSION['mod_epre_ids_dotation']);
		unset($_SESSION['mod_epre_ids_participation']);
		unset($_SESSION['mod_epre_ids_questiondiverse']);
		unset($_SESSION['mod_epre_ids_code_promo']);
	}

	if(isset($_GET['file']) && ($_GET['file'] != ""))
	{
		unset($_SESSION['accueil_mois']);
		unset($_SESSION['accueil_annee']);
		unset($_SESSION['accueil_dept']);
		unset($_SESSION['accueil_type']);
		unset($_SESSION['accueil_from']);
	}
	@include"includes/js/UploadFile.js";  
	*/

?>