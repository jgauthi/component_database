<?php
/*******************************************************************************
 * @name: Mysql PDO
 * @note: Framework pour améliorer la gestion de MySql via PDO
 * @author: Jgauthi <github.com/jgauthi>, created at [12nov2013]
 * @version: 0.74
 * @Requirements:
     - Pdo
     - PHP version >= 5.2.0 (http://php.net)
 * référence: https://openclassrooms.com/courses/les-transactions-avec-mysql-et-pdo

 *******************************************************************************/

class mysql_pdo extends pdo
{
    private $debug = false;
    private $mail_admin;

    //-- CONSTRUCTEUR ------------------------------------------------------------------------
    public function __construct($host, $user, $pass, $database, $port = null, $charset = null)
    {
        $this->debug = ini_get('display_errors');
        try {
            // Paramètres de connexion
            $parametre = array('host' => $host, 'dbname' => $database);
            if (!empty($port)) $parametre['port'] = $port;
            if (!empty($charset)) $parametre['charset'] = $charset; // ex: utf8

            // Options de la connexion
            $option = array
            (
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'    //careful with this one, though
            );

            parent::__construct("mysql:" . implode(';', $parametre), $user, $pass, $option);
            $this->setAttribute(PDO::ATTR_ERRMODE, ERRMODE_EXCEPTION);
            $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $msg = 'Impossible de se connecter à la base de donnée';
            if ($this->debug)
                $msg .= ': ' . $e->getMessage();

            return die(user_error($msg));
        }
    }

    //-- GESTION DB ----------------------------------------------------------------------------
    public function erreur($message, $requete_sql = '')
    {
        // Afficher l'erreur
        if ($this->debug)
            user_error("<p>Erreur mysql: '{$message}', requete:\n<pre>{$requete_sql}</pre>\n\n");

        // Envoie d'un rapport d'erreur par email
        $this->rapport("Erreur mysql: '{$message}', \nrequete:\n{$requete_sql}");
    }


    public function set_mail_admin($email)
    {
        $this->mail_admin = $email;
    }


    // TODO: Générer un rapport global à la fin du chargement de la page
    // Au lieu d'envoyer un mail par erreur (cf batch.class.php)
    private function rapport($txt_erreur)
    {
        if (empty($this->mail_admin)) return false;

        // Générer le rapport d'erreur
        $message = "Erreurs répertoriées: \n\n";
        $message .= '* ' . $txt_erreur . "\n";

        // Donnée Serveur/Client
        $message .= "\n------------------------------------------------" .
            "\nHOST: http://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] .
            ((!empty($_SERVER['QUERY_STRING'])) ? '?' . $_SERVER['QUERY_STRING'] : '') .

            "\nNAV: " . $_SERVER['HTTP_USER_AGENT'] .
            "\nIP: " . $_SERVER['REMOTE_ADDR'] . "\n";

        // Entete du mail
        $limite = "_parties_" . md5(uniqid(rand()));

        $mail_mime = 'From: ' . $this->mail_admin . "\n";
        $mail_mime .= "Date: " . date("l j F Y, G:i") . "\n";
        $mail_mime .= "MIME-Version: 1.0\n";
        $mail_mime .= "Content-Type: multipart/alternative;\n";
        $mail_mime .= "boundary=\"----=$limite\"\n\n";

        // le message en texte normal
        $texte_msg = "------=$limite\n";
        $texte_msg .= "Content-Type: text/plain\n";
        $texte_msg .= "Content-Transfer-Encoding: 7bit\n\n" . $message;

        mail($this->mail_admin, 'Erreur sur le site: ' . $_SERVER['HTTP_HOST'] . ' : page ' . basename($_SERVER['PHP_SELF']) .
            ' - ' . date("d/m/y H:i:s"), $texte_msg, $mail_mime);

        echo '<p>Une erreur est survenue. Un rapport d\'erreur a été envoyé à l\'un de nos techniciens.</p>';

        return die();
    }

    //-- GESTION REQUETE ------------------------------------------------------------------------
    public function query($req)
    {
        if (!$requete = parent::query($req))
            return $this->erreur('Sql n°' . $this->errno . ': ' . $this->error, $req);
        else return $requete;
    }

}
