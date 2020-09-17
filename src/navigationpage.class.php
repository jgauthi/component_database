<?php
/**
 * NavigationPage - Session numéro page SQL
 * @author: Jgauthi <github.com/jgauthi>
 * @maj 24/07/2012
 * @version 1.0
 *
 * Caractère spéciaux pour flèche
 * http://www.theorem.ca/~mvcorks/cgi-bin/unicode.pl.cgi?start=2600&amp;end=26FF
 */

class NavigationPage
{
    // Donnée
    var $nb_ligne;
    var $nb_page;
    var $sql;
    var $req;
    var $req_nb;

    // Navigation
    var $link;
    var $page;
    var $nb;
    var $html = '';
    var $nb_num_navig = 5;
    var $titre = false;
    var $query_string = false;

    // Template
    var $precplus = '&lt;&lt;'; 	// <<
    var $prec = '&lt;'; 			// <
    var $suiv = '&gt;'; 			// >
    var $suivplus = '&gt;&gt;'; 	// >>
    var $fleche_haut = '&#9757;';	// Main pointant vers le haut
    var $fleche_bas = '&#9759;';	// "	"		 "	  "	 bas

    //-- CONSTRUCTEUR ------------------------------------
    function NavigationPage($sql, $nb_ligne = 10, $nb_page = 10)
    {
        $this->nb_ligne = $nb_ligne;
        $this->nb_page = $nb_page;
        $this->sql = $sql . " \n";

        // Page actuelle
        if (!isset($_GET['p']) || $_GET['p'] <= 1)
            $this->page = 1;
        else
            $this->page = $_GET['p'];
    }

    // Gestion des liens
    function link()
    {
        $this->link = basename($_SERVER['PHP_SELF']) . '?';
        if (!empty($_SERVER['QUERY_STRING']))
        {
            $query = $_GET;
            if (isset($query['p'])) unset($query['p']);
            if (isset($query['o'])) unset($query['o']);
            if (isset($query['f'])) unset($query['f']);
            if (count($query) > 0)
            {
                foreach($query as $id => $data)
                    $this->link .= "$id=$data&";

                // Enregistrement du link actuel pour la generation de la barre
                $this->query_string = $this->link;
            }
        }
        $this->link .= 'p=';

        if (isset($_GET['o'], $_GET['f'], $this->titre[$_GET['o']]))
            $this->link = str_replace('p=', 'o='. $_GET['o'] .'&f='. $_GET['f'] .'&p=', $this->link);
    }

    function Query()
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
            && isset($_GET['o'], $this->titre[$_GET['o']], $_GET['f']) && eregi("^0|1$", $_GET['f']) )
        {
            $order  = ' ORDER BY '. $_GET['o'] . ' ';
            $order .= (($_GET['f'] == 1) ? 'DESC':'ASC').' ';

            if (eregi('ORDER BY', $this->sql))
                $this->sql = eregi_replace("(ORDER BY.+)$", $order, $this->sql);
            else	$this->sql .= $order;
        }

        // Requête SQL
        $this->req = mysql_query($this->sql . "LIMIT $index, ".$this->nb_ligne);
        $this->req_nb = mysql_num_rows($this->req);

        // Calculer le nombre de page TOTAL
        $sql = eregi_replace("^(SELECT)(.+)(FROM.+)$", "\\1 COUNT(*) as nb \\3", trim($this->sql));
        $sql = mysql_fetch_row(mysql_query($sql));
        $this->nb = $sql[0];

        return ($this->req);
    }

    Function Nb_query()
    {
        if ($this->req_nb != '')
            return $this->req_nb;
        else
            return false;
    }

    Function Nb_page()
    {
        return(ceil($this->nb / $this->nb_ligne));
    }

    //-- NAVIGATION ------------------------------------------------------------------------------
    Function PageActuelle()
    {
        if (!empty($this->page) && !empty($this->nb))
            return ($this->page.'/'.ceil($this->nb / $this->nb_ligne));
    }

    Function BarreNavigation($class_lien = '')
    {
        // Si la barre de navigation à déjà été crée, l'envoyé
        if (!empty($this->html))
            return ($this->html);

        // Vérification de la page
        $nb_page = $this->Nb_page();
        if ($this->page > $nb_page)
            $this->page = $nb_page;

        // Ne construire la barre que si il y a plusieurs pages
        if ($nb_page > 1)
        {
            // Activer la variable link si ce n'est pas déjà fait
            if (empty($this->link))
                $this->link();

            // Class des liens
            if (!empty($class_lien))
                $lien = '<a class="'.$class_lien.'" ';
            else
                $lien = '<a ';

            $this->html = '';

            // Navigation AVANT
            if ($this->page != 1)
            {
                // Navigation Début <<
                if ($this->page > 2)
                    $this->html .= $lien.'href="'.$this->link.'1">'.$this->precplus."</a> \n";

                // Navigation Précédent <
                $this->html .= $lien.'href="'.$this->link.($this->page - 1).'">'.$this->prec."</a> \n";
            }

            // Gestion des numeros de page
            $debut = 1;
            $fin = $nb_page;

            // Limiter le nombre de lien de page montrée
            if (is_numeric($this->nb_page) && $this->nb_page != 0 && $this->nb_page < $nb_page)
            {
                // exemple $this->nb_page = 5
                // si page en cours = 5, afficher <<..<..2..3..4..[5]..6..7..>..>>

                // Page de départ
                $debut = ceil($this->nb_page / 2);
                $fin = floor($this->nb_page / 2);

                // Ajustement
                if ($this->page < $debut)
                {
                    $diff_deb = $debut - $this->page;
                    $fin += $diff_deb;
                }
                $debut = (($this->page <= $debut) ? '1':$this->page - $debut);

                // Dernière page affiché
                $fin += $this->page;
                if ($fin > $nb_page)
                {
                    $diff_fin = $fin - $nb_page;
                    $fin = $nb_page;
                    $debut -= $diff_fin;
                }
            }

            for($i = $debut; $i <= $fin; $i++)
            {
                if ($this->page == $i)
                    $this->html .= '<strong>'.$this->page."</strong> \n";
                else	$this->html .= $lien.'href="'.$this->link."$i\">$i</a> \n";
            }

            // NAVIGATION APRES
            if ($this->page != $nb_page)
            {
                // Navigation Suivant >
                $this->html .= $lien.'href="'.$this->link.($this->page + 1).'">'.$this->suiv."</a> \n";

                // Navigation Fin >>
                if ($this->page < ($nb_page - 1))
                    $this->html .= $lien.'href="'.$this->link.$nb_page.'">'.$this->suivplus."</a> \n";
            }
        }
        else
            $this->html = '';

        return ($this->html);
    }


    function Navig_Titre ($titre)
    {
        if (!is_array($titre) || count($titre) == 0)
        {
            user_error('Class navig -> function Navig_titre: la variable $titre n\'est pas un array valide.');
            return(exit());
        }

        $this->titre = $titre;
    }

    function afficher_titre()
    {
        if (!is_array($this->titre))
            return (false);

        // Activer la variable link si ce n'est pas déjà fait
        if (empty($this->link))
            $this->link();

        $content = '';
        foreach($this->titre as $id => $value)
        {
            $content .= '<th>' . htmlentities($value);

            // Flèche HAUT (desc)
            if (isset($_GET['o'], $_GET['f']) && $_GET['o'] == $id && $_GET['f'] == 1)
                $content .= ' <em>'. $this->fleche_haut .'</em>';
            else	$content .= ' <a href="'. $this->query_string . 'f=1&o='.$id.'">'. $this->fleche_haut .'</a>';

            // Flèche BAS (asc)
            if (isset($_GET['o'], $_GET['f']) && $_GET['o'] == $id && $_GET['f'] == 0)
                $content .= ' <em>'. $this->fleche_bas .'</em>';
            else	$content .= ' <a href="'. $this->query_string . 'f=0&o='.$id.'">'. $this->fleche_bas .'</a>';

            $content .= "</th>\n";
        }

        return($content);
    }
}

?>