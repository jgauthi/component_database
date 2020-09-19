<?php
/*******************************************************************************
 * @name: NavigationPage
 * @note: Page pagination with mysql or sqlite
 * @author: Jgauthi <github.com/jgauthi>, created at [5nov2007]
 * @version: 1.4 (mysqli version)

 *******************************************************************************/

namespace Jgauthi\Component\Database;

use InvalidArgumentException;
use PDO;

class NavigationPage
{
    /** @var PDO $database */
    protected $database;

    // Donnée
    protected $nbLines;
    protected $nbPages;
    protected $nbTotalPage;
    protected $nbCurrentRequest = null;

    // Navigation
    public $link;
    protected $currentPage = 1;
    protected $title = [];
    public $queryString = false;

    // Template
    public $previousPlus = '&lt;&lt;'; // <<
    public $previous = '&lt;'; // <
    public $next = '&gt;'; // >
    public $nextPlus = '&gt;&gt;'; // >>
    public $upArrow = '&#9757;'; // Main pointant vers le haut
    public $downArrow = '&#9759;'; // "	"		 "	  "	 bas

    /**
     * NavigationPage constructor.
     * @param PDO $databaseLib
     * @param int $nb_ligne
     * @param int $nb_page
     */
    public function __construct(PDO $databaseLib, $nb_ligne = 10, $nb_page = 10)
    {
        $this->database = $databaseLib;
        $this->initSettingsPage($nb_ligne, $nb_page)
            ->initLink();
    }

    /**
     * @param int $nbLine
     * @param int $nbPage
     * @return self
     */
    protected function initSettingsPage($nbLine = 10, $nbPage = 10)
    {
        $this->nbPages = max($nbPage, 3);
        $this->nbLines = filter_var($nbLine, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 5, 'max_range' => 100, 'default' => 10]
        ]);

        if (!empty($_GET['p']) && $_GET['p'] > 1) {
            $this->currentPage = $_GET['p'];
        }

        return $this;
    }

    // Link management

    /**
     * @return self
     */
    protected function initLink()
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
                    $this->link .= $id . '=' . urlencode($data) . '&';
                }
            }
        }

        // Enregistrement du link actuel pour la generation de la barre
        $this->queryString = $this->link;
        $this->link .= 'p=';

        if (isset($_GET['o'], $_GET['f'], $this->title[$_GET['o']])) {
            $this->link = str_replace("o={$_GET['o']}&f={$_GET['f']}&p=", '', $this->link);
        }

        return $this;
    }

    /**
     * @param string $query
     * @return \PDOStatement|bool
     */
    public function query($query)
    {
        /* CALCUL - Pour obtenir les maximuns: 01-10, 11-20, 21-30 ---
        Résultat max: Numéro de la page * Nombre de ligne par page
        Résultat min: Résultat max - Nombre de ligne par page + 1

        --------------------------------------------------------------*/

        $index = ($this->currentPage - 1) * $this->nbLines;
        $query .= " \n";

        // Si gestion des titres activer, filtrer les résultats ORDER BY
        // $_GET['o'] -> Titre sélectionné
        // $_GET['f'] -> Flèche sélectionné (1 -> Haut, 0 -> Bas)
        if (is_array($this->title) && count($this->title) > 0
            && isset($_GET['o'], $this->title[$_GET['o']], $_GET['f'])
            && preg_match('#^0|1$#', $_GET['f'])
        ) {
            $order = (1 === $_GET['f'] ? 'DESC' : 'ASC');
            $order = " ORDER BY {$_GET['o']} {$order}";

            if (preg_match('#ORDER BY#i', $query)) {
                $query = preg_replace('#(ORDER BY[^$]+)$#i', $order, $query);
            } else {
                $query .= $order;
            }
        }

        // Requête MySQL
        $sqlRequestCurrentPage = "{$query} LIMIT {$index}, {$this->nbLines}";
        $sqlRequestNbPage = "SELECT COUNT(*) as nb FROM ({$query}) AS req_nb_element;";

        return $this->queryExecute($sqlRequestCurrentPage, $sqlRequestNbPage);
    }

    /**
     * Execute the sql command in classic library mysql
     * --> YOU CAN OVERRIDE THIS FUNCTION WITH "EXTENDS THIS CLASS" AND customize this function with YOUR connexion class.
     *
     * @param string $sqlRequestCurrentPage SQL req to get current line page
     * @param string $sqlRequestNbPage SQL req to get nb page
     * @return \PDOStatement|bool
     */
    protected function queryExecute($sqlRequestCurrentPage, $sqlRequestNbPage)
    {
        $request = $this->database->query($sqlRequestCurrentPage);
        $this->nbCurrentRequest = $request->rowCount();
        $this->nbTotalPage = $this->database->query($sqlRequestNbPage)->fetch(PDO::FETCH_COLUMN);

        return $request;
    }

    /**
     * @return int|null
     */
    public function nbQuery()
    {
        return $this->nbCurrentRequest;
    }

    /**
     * @return int
     */
    public function nbPage()
    {
        return ceil($this->nbTotalPage / $this->nbLines);
    }

    //-- NAVIGATION ------------------------------------------------------------------------------

    /**
     * @return string|null
     */
    public function currentPage()
    {
        if (!empty($this->currentPage) && !empty($this->nbTotalPage)) {
            return $this->currentPage . '/' . ceil($this->nbTotalPage / $this->nbLines);
        }

        return null;
    }

    /**
     * @return array|null
     */
    public function getNavigation()
    {
        // Vérification de la page
        $nbPage = $this->nbPage();
        if ($this->currentPage > $nbPage) {
            $this->currentPage = $nbPage;
        }

        // Ne construire la barre que si il y a plusieurs pages
        if ($nbPage <= 1) {
            return null;
        }

        $navigation = [];

        // Navigation AVANT
        if (1 !== $this->currentPage) {
            // Navigation Début <<
            if ($this->currentPage > 2) {
                $navigation[] = [
                    'url' => $this->link . '1',
                    'text' => $this->previousPlus,
                    'status' => null,
                ];
            }

            // Navigation Précédent <
            $navigation[] = [
                'url' => $this->link . ($this->currentPage - 1),
                'text' => $this->previous,
                'status' => null,
            ];
        }

        // Gestion des numeros de page
        $debut = 1;
        $fin = $nbPage;

        // Limiter le nombre de lien de page montrée
        if (is_numeric($this->nbPages) && $this->nbPages !== 0 && $this->nbPages < $nbPage) {
            // exemple $this->nb_page = 5
            // si page en cours = 5, afficher <<..<..2..3..4..[5]..6..7..>..>>

            // Page de départ
            $debut = ceil($this->nbPages / 2);
            $fin = floor($this->nbPages / 2);

            // Ajustement
            if ($this->currentPage < $debut) {
                $diff_deb = $debut - $this->currentPage;
                $fin += $diff_deb;
            }
            $debut = $this->currentPage <= $debut ? '1' : $this->currentPage - $debut;

            // Dernière page affiché
            $fin += $this->currentPage;
            if ($fin > $nbPage) {
                $diff_fin = $fin - $nbPage;
                $fin = $nbPage;
                $debut -= $diff_fin;
            }
        }

        for ($i = $debut; $i <= $fin; ++$i) {
            $status = $this->currentPage == $i ? 'active' : null;
            $navigation[] = [
                'url' => $this->link . $i,
                'text' => $i,
                'status' => $status,
            ];
        }

        // NAVIGATION APRES
        if ($this->currentPage !== $nbPage) {
            // Navigation Suivant >
            $navigation[] = [
                'url' => $this->link . ($this->currentPage + 1),
                'text' => $this->next,
                'status' => null,
            ];

            // Navigation Fin >>
            if ($this->currentPage < $nbPage - 1) {
                $navigation[] = [
                    'url' => $this->link . $nbPage,
                    'text' => $this->nextPlus,
                    'status' => null,
                ];
            }
        }

        return $navigation;
    }

    /**
     * @param string|null $classLink
     * @return string|null
     */
    public function navigationBar($classLink = null)
    {
        // Class des liens
        $html = '';
        $lien = '<a ';
        if (!empty($classLink)) {
            $lien .= 'class="' . $classLink . '" ';
        }

        $navigation = $this->getNavigation();
        if (empty($navigation)) {
            return null;
        }

        foreach ($navigation as $page) {
            if ('active' === $page['status']) {
                $html .= "<strong>{$page['text']}</strong>";
            } else {
                $html .= $lien . sprintf('href="%s">%s</a>', $page['url'], $page['text']);
            }

            $html .= " \n";
        }

        return $html;
    }

    /**
     * @param array $title
     * @return self
     */
    public function setNavigationTitle($title)
    {
        if (empty($title)) {
            throw new InvalidArgumentException(
                'Class ' . __CLASS__ . '::' . __FUNCTION__ . '(): ' .
                'la variable $title n\'est pas un array valide.'
            );
        }

        $this->title = $title;

        return $this;
    }

    /**
     * @param string $balise
     * @return string|null
     */
    public function displayTitles($balise = 'th')
    {
        if (empty($this->title)) {
            return null;
        }

        $content = '';
        foreach ($this->title as $id => $value) {
            $content .= '<' . $balise . ' scope="col">' . htmlentities($value);
            $url = "{$this->queryString}o={$id}&f=";

            // Flèche HAUT (desc)
            if (isset($_GET['o'], $_GET['f']) && $_GET['o'] === $id && $_GET['f'] === 1) {
                $content .= "&nbsp;<em>{$this->upArrow}</em>";
            } else {
                $content .= '&nbsp;<a href="' .$url. '1">'. $this->upArrow .'</a>';
            }

            // Flèche BAS (asc)
            if (isset($_GET['o'], $_GET['f']) && $_GET['o'] === $id && $_GET['f'] === 0) {
                $content .= "&nbsp;<em>{$this->downArrow}</em>";
            } else {
                $content .= '&nbsp;<a href="' .$url. '0">'. $this->downArrow .'</a>';
            }

            $content .= "</{$balise}>\n";
        }

        return $content;
    }
}