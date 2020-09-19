<?php
/*******************************************************************************
 * @name: NavigationPage
 * @note: Session numéro page SQL
 * @author: Jgauthi <github.com/jgauthi>, created at [5nov2007]
 * @version: 1.2

 *******************************************************************************/

class NavigationPage
{
    // Donnée
    protected $nb_ligne;
    protected $nb_page;
    protected $sql;
    protected $req;
    protected $nb;
    protected $req_nb;

    // Navigation
    public $link;
    public $page;
    protected $html = '';
    public $nb_num_navig = 5;
    public $titre = false;
    public $query_string = false;

    // Template
    public $precplus = '&lt;&lt;'; 	// <<
    public $prec = '&lt;'; 			// <
    public $suiv = '&gt;'; 			// >
    public $suivplus = '&gt;&gt;'; 	// >>
    public $fleche_haut = '&#9757;';	// Main pointant vers le haut
    public $fleche_bas = '&#9759;';	// "	"		 "	  "	 bas

    //-- CONSTRUCTEUR ------------------------------------
    public function __construct($sql, $nb_ligne = 10, $nb_page = 10)
    {
        $this->nb_ligne = $nb_ligne;
        $this->nb_page = $nb_page;
        $this->sql = $sql." \n";

        // Page actuelle
        if (!isset($_GET['p']) || $_GET['p'] <= 1) {
            $this->page = 1;
        } else {
            $this->page = $_GET['p'];
        }
    }

    // Gestion des liens
    public function link()
    {
        $this->link = str_replace($_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
        if (!mb_strstr($this->link, '?')) {
            $this->link .= '?';
        }

        if (!empty($_SERVER['QUERY_STRING'])) {
            $query = $_GET;
            if (isset($query['p'])) {
                unset($query['p']);
            }
            if (isset($query['o'])) {
                unset($query['o']);
            }
            if (isset($query['f'])) {
                unset($query['f']);
            }

            if (count($query) > 0) {
                foreach ($query as $id => $data) {
                    $this->link .= $id.'='.urlencode($data).'&';
                }
            }
        }

        // Enregistrement du link actuel pour la generation de la barre
        $this->query_string = $this->link;
        $this->link .= 'p=';

        if (isset($_GET['o'], $_GET['f'], $this->titre[$_GET['o']])) {
            $this->link = str_replace('p=', 'o='.$_GET['o'].'&f='.$_GET['f'].'&p=', $this->link);
        }
    }

    /**
     * @return mysqli_result
     */
    public function query()
    {
        /* CALCUL - Pour obtenir les maximuns: 01-10, 11-20, 21-30 ---
        Résultat max: Numéro de la page * Nombre de ligne par page
        Résultat min: Résultat max - Nombre de ligne par page + 1

        --------------------------------------------------------------*/

        $index = (($this->page - 1) * $this->nb_ligne);

        // Si gestion des titres activer, filtrer les résultats ORDER BY
        // $_GET['o'] -> Titre sélectionné
        // $_GET['f'] -> Flèche sélectionné (1 -> Haut, 0 -> Bas)
        if (is_array($this->titre) && count($this->titre) > 0
            && isset($_GET['o'], $this->titre[$_GET['o']], $_GET['f']) && preg_match('#^0|1$#', $_GET['f'])) {
            $order = ' ORDER BY '.$_GET['o'].' ';
            $order .= ((1 === $_GET['f']) ? 'DESC' : 'ASC').' ';

            if (preg_match('#ORDER BY#i', $this->sql)) {
                $this->sql = preg_replace('#(ORDER BY[^$]+)$#i', $order, $this->sql);
            } else {
                $this->sql .= $order;
            }
        }

        // Requête MySQL
        $mysql_req_result = "{$this->sql} LIMIT {$index}, {$this->nb_ligne}";
        $mysql_req_nbpage = "SELECT COUNT(*) as nb FROM ({$this->sql}) AS req_nb_element;";

        return $this->query_execute($mysql_req_result, $mysql_req_nbpage);
    }

    /**
     * Execute the sql command in classic library mysql
     * --> YOU CAN OVERRIDE THIS FUNCTION WITH "EXTENDS THIS CLASS" AND customize this function with YOUR connexion class.
     *
     * @param string $mysql_req_result SQL req to get current line page
     * @param string $mysql_req_nbpage SQL req to get nb page
     *
     * @return mysqli_result
     */
    protected function query_execute($mysql_req_result, $mysql_req_nbpage)
    {
        // Requête SQL
        $this->req = mysqli_query($GLOBALS['mysqli'], $mysql_req_result);
        $this->req_nb = mysqli_num_rows($this->req);

        // Calculer le nombre de page TOTAL
        $sql = mysqli_fetch_row(mysqli_query($GLOBALS['mysqli'], $mysql_req_nbpage));
        $this->nb = $sql[0];

        return $this->req;
    }

    /**
     * @return bool
     */
    public function nb_query()
    {
        if ('' !== $this->req_nb) {
            return $this->req_nb;
        }

        return false;
    }

    /**
     * @return float
     */
    public function nb_page()
    {
        return ceil($this->nb / $this->nb_ligne);
    }

    //-- NAVIGATION ------------------------------------------------------------------------------

    /**
     * @return string|null
     */
    public function PageActuelle()
    {
        if (!empty($this->page) && !empty($this->nb)) {
            return $this->page.'/'.ceil($this->nb / $this->nb_ligne);
        }

        return null;
    }

    /**
     * @return array|null
     */
    public function get_navigation()
    {
        // Vérification de la page
        $nb_page = $this->Nb_page();
        if ($this->page > $nb_page) {
            $this->page = $nb_page;
        }

        // Ne construire la barre que si il y a plusieurs pages
        if ($nb_page <= 1) {
            return null;
        }

        // Activer la variable link si ce n'est pas déjà fait
        if (empty($this->link)) {
            $this->link();
        }

        $navigation = [];

        // Navigation AVANT
        if (1 !== $this->page) {
            // Navigation Début <<
            if ($this->page > 2) {
                $navigation[] = ['url' => $this->link.'1', 'text' => $this->precplus, 'status' => null];
            }

            // Navigation Précédent <
            $navigation[] = ['url' => $this->link.($this->page - 1), 'text' => $this->prec, 'status' => null];
        }

        // Gestion des numeros de page
        $debut = 1;
        $fin = $nb_page;

        // Limiter le nombre de lien de page montrée
        if (is_numeric($this->nb_page) && 0 !== $this->nb_page && $this->nb_page < $nb_page) {
            // exemple $this->nb_page = 5
            // si page en cours = 5, afficher <<..<..2..3..4..[5]..6..7..>..>>

            // Page de départ
            $debut = ceil($this->nb_page / 2);
            $fin = floor($this->nb_page / 2);

            // Ajustement
            if ($this->page < $debut) {
                $diff_deb = $debut - $this->page;
                $fin += $diff_deb;
            }
            $debut = (($this->page <= $debut) ? '1' : $this->page - $debut);

            // Dernière page affiché
            $fin += $this->page;
            if ($fin > $nb_page) {
                $diff_fin = $fin - $nb_page;
                $fin = $nb_page;
                $debut -= $diff_fin;
            }
        }

        for ($i = $debut; $i <= $fin; ++$i) {
            $status = (($this->page === $i) ? 'active' : null);

            $navigation[] = ['url' => $this->link.$i, 'text' => $i, 'status' => $status];
        }

        // NAVIGATION APRES
        if ($this->page !== $nb_page) {
            // Navigation Suivant >
            $navigation[] = ['url' => $this->link.($this->page + 1), 'text' => $this->suiv, 'status' => null];

            // Navigation Fin >>
            if ($this->page < ($nb_page - 1)) {
                $navigation[] = ['url' => $this->link.$nb_page, 'text' => $this->suivplus, 'status' => null];
            }
        }

        return $navigation;
    }

    /**
     * @param string $class_lien
     *
     * @return string|null
     */
    public function BarreNavigation($class_lien = null)
    {
        // Si la barre de navigation à déjà été crée, l'envoyer
        if (!empty($this->html)) {
            return $this->html;
        }

        // Class des liens
        $this->html = '';
        if (!empty($class_lien)) {
            $lien = '<a class="'.$class_lien.'" ';
        } else {
            $lien = '<a ';
        }

        $navigation = $this->get_navigation();
        if (empty($navigation)) {
            return null;
        }

        foreach ($navigation as $page) {
            if ('active' === $page['status']) {
                $this->html .= '<strong>'.$page['text'].'</strong>';
            } else {
                $this->html .= $lien.sprintf('href="%s">%s</a>', $page['url'], $page['text']);
            }

            $this->html .= " \n";
        }

        return $this->html;
    }

    /**
     * @param $titre
     *
     * @throws Exception
     */
    public function navig_titre($titre)
    {
        if (!is_array($titre) || 0 === count($titre)) {
            throw new InvalidArgumentException('Class '.__CLASS__.'::'.__FUNCTION__.'(): '.'la variable $titre n\'est pas un array valide.');
        }

        $this->titre = $titre;
    }

    /**
     * @param string $balise
     *
     * @return bool|string
     */
    public function afficher_titre($balise = 'th')
    {
        if (!is_array($this->titre)) {
            return false;
        }

        // Activer la variable link si ce n'est pas déjà fait
        if (empty($this->link)) {
            $this->link();
        }

        $content = '';
        foreach ($this->titre as $id => $value) {
            $content .= '<'.$balise.' scope="col">'.htmlentities($value);

            // Flèche HAUT (desc)
            if (isset($_GET['o'], $_GET['f']) && $_GET['o'] === $id && 1 === $_GET['f']) {
                $content .= '&nbsp;<em>'.$this->fleche_haut.'</em>';
            } else {
                $content .= '&nbsp;<a href="'.$this->query_string.'f=1&o='.$id.'">'.$this->fleche_haut.'</a>';
            }

            // Flèche BAS (asc)
            if (isset($_GET['o'], $_GET['f']) && $_GET['o'] === $id && 0 === $_GET['f']) {
                $content .= '&nbsp;<em>'.$this->fleche_bas.'</em>';
            } else {
                $content .= '&nbsp;<a href="'.$this->query_string.'f=0&o='.$id.'">'.$this->fleche_bas.'</a>';
            }

            $content .= "</{$balise}>\n";
        }

        return $content;
    }
}
