<?php
/*******************************************************************************
 * @name: Mysqli+
 * @note: Framework pour étendre la class mysqli
 * @author: Jgauthi <github.com/jgauthi>, created at [3avril2010]
 * @version: 1.2
 * @Requirements:
    - PHP version >= 5.2.0 (http://php.net)
    - PHP extension: mysqli

 * -- CONFIGURATION ---------
 * define('ADMIN_EMAIL', 'john@doe.com');
 * ---------------------------

 *******************************************************************************/

class mysqliplus extends mysqli
{
    protected $config = array();

    //-- CONSTRUCTEUR ------------------------------------------------------------------------
    public function __construct($host, $user, $pass, $db)
    {
        @parent::__construct($host, $user, $pass, $db);

        // Gestion rapport erreur
        if(defined('ADMIN_EMAIL'))
        {
            $this->config['mail'] = ADMIN_EMAIL;
            $this->config['titre'] = $_SERVER['HTTP_HOST'];

            // Erreur de connexion
            if($this->connect_error)
                $this->erreur('Erreur de connexion (' . $this->connect_errno . ') '. $this->connect_error);
        }
    }

    //-- GESTION DB ----------------------------------------------------------------------------
    public function erreur($message, $requete_sql = '')
    {
        // Afficher l'erreur
        if(ini_get('display_errors'))
            user_error(nl2br("Requete:\n<em>$requete_sql</em>\n\nErreur: $message"));

        // Envoie d'un rapport d'erreur par email
        if(!empty($this->config['mail']))
        {
            if(!empty($requete_sql))
                $message = "Requete: $requete_sql\n\nErreur: $message";

            $this->rapport($message);
        }
    }

    // TODO: Générer un rapport global à la fin du chargement de la page
    // Au lieu d'envoyer un mail par erreur (cf batch.class.php)
    private function rapport($txt_erreur)
    {
        // Générer le rapport d'erreur
        $message = "Erreurs répertoriées: \n\n";
        $message .= '* '.$txt_erreur."\n";

        // Donnée Serveur/Client
        $message .= "\n------------------------------------------------" .
            "\nHOST: http://". $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] .
            ((!empty($_SERVER['QUERY_STRING'])) ? '?'.$_SERVER['QUERY_STRING']:'') .

            "\nNAV: " . $_SERVER['HTTP_USER_AGENT'] .
            "\nIP: " . $_SERVER['REMOTE_ADDR'] . "\n";

        // Entete du mail
        $limite = "_parties_".md5 (uniqid (rand()));

        $mail_mime  = 'From: '. $this->config['mail'] ."\n";
        $mail_mime .= "Date: ".date("l j F Y, G:i")."\n";
        $mail_mime .= "MIME-Version: 1.0\n";
        $mail_mime .= "Content-Type: multipart/alternative;\n";
        $mail_mime .= "boundary=\"----=$limite\"\n\n";

        // le message en texte normal
        $texte_msg  = "------=$limite\n";
        $texte_msg .= "Content-Type: text/plain\n";
        $texte_msg .= "Content-Transfer-Encoding: 7bit\n\n".$message;

        mail($this->config['mail'], 'ERREUR SUR LE SITE '. $this->config['titre'] .' : page '.basename($_SERVER['PHP_SELF']).
            ' - '.date("d/m/y H:i:s"), $texte_msg, $mail_mime);

        echo '<p>Une erreur est survenue. Un rapport d\'erreur a été envoyé à l\'un de nos techniciens.</p>';

        return die();
    }

    //-- GESTION REQUETE ------------------------------------------------------------------------
    public function query($req)
    {
        if(!$requete = parent::query($req))
            return $this->erreur('Sql n°'. $this->errno .': '. $this->error, $req);
        else	return $requete;
    }

    public function real_escape_string($data)
    {
        /*if(empty($value))
            return (NULL);
        else
        {
            if(get_magic_quotes_gpc() == 1)
                    return ("'$value'");
            else	return ("'".addslashes($value)."'");
        }*/


        return parent::real_escape_string($data);
    }

    //-- Gestion des slashs ----------------------------------------------------------------------
    public function AddSlashes($chaine)
    {
        return( get_magic_quotes_gpc() == 1 ?
            $chaine :
            addslashes($chaine)
        );
    }

    public function StripSlashes($chaine)
    {
        return( get_magic_quotes_gpc() == 1 ?
            stripslashes($chaine) :
            $chaine
        );
    }
}
