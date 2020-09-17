<?php
/**
 * @param string $server
 * @param string $login
 * @param string $pass
 * @param string $database
 * @param string $charset
 *
 * @return bool|resource
 */
function mysql_init($server, $login, $pass, $database, $charset = 'utf8')
{
    if (!$link = @mysql_connect($server, $login, $pass)) {
        return !trigger_error('Impossible de se connecter : '.mysql_error());
    } elseif (!$db_selected = mysql_select_db($database, $link)) {
        return !trigger_error('Impossible de sélectionner la base de données : '.mysql_error());
    } elseif (!empty($charset)) { // utf8|latin1
        if (!function_exists('mysql_set_charset')) {
            mysql_query("SET CHARACTER SET '$charset';", $link);
        } else {
            mysql_set_charset($charset, $link); // php 5.2.3
        }
    }

    return $link;
}

/**
 * @param string $sql
 *
 * @return bool|resource
 */
function mysql_req($sql)
{
    $req = mysql_query($sql) or die(!trigger_error(
        (1 === ini_get('display_errors')) ?
            '<p>Erreur dans la requête MySQL:<br /><pre>'.htmlentities($sql).'</pre><br /> Erreur trouvé: <strong>'.mysql_error().'</strong></p>'
            : 'Une erreur a été détecté, veuillez contacter un administrateur'
    ));

    return $req;
}

//-- Fonctions de récupération de données -------------------------------------------------------
/**
 * @param string $champ
 * @param string $table
 * @param string $search
 *
 * @return mixed|null
 */
function mysql_get_field($champ, $table, $search)
{
    $req = mysql_req("SELECT $champ FROM $table WHERE $search LIMIT 1;");

    if (1 === mysql_num_rows($req)) {
        $req = mysql_fetch_row($req);

        return  $req[0];
    }

    return null;
}

/**
 * Récupération SIMPLE de requete (champ devant apparaitre dans les résultats: ID + NOM).
 *
 * @param $requete
 *
 * @return array
 */
function mysql_fetch_simple($requete)
{
    $req = mysql_req($requete);

    $data = array();
    if (mysql_num_rows($req) > 0) {
        $res = mysql_fetch_row($req);
        if (1 === count($res)) {
            $data[] = $res[0];
            while ($res = mysql_fetch_row($req)) {
                $data[] = $res[0];
            }
        } else {
            $data[$res[0]] = $res[1];
            while ($res = mysql_fetch_row($req)) {
                $data[$res[0]] = $res[1];
            }
        }
    }

    return  $data;
}

/**
 * @param resource        $requete
 * @param string|int|null $id_intitule
 *
 * @return array
 */
function mysql_fetch_all($requete, $id_intitule = null)
{
    $data = array();

    if (null !== $id_intitule) {
        while ($res = mysql_fetch_assoc($requete)) {
            $data[$res[$id_intitule]] = $res;
        }
    } else {
        while ($res = mysql_fetch_assoc($requete)) {
            $data[] = $res;
        }
    }

    return  $data;
}

/**
 * @param string          $requete
 * @param string|int|null $id_intitule
 *
 * @return array
 */
function mysql_fetch_result($requete, $id_intitule = null)
{
    return mysql_fetch_all(mysql_req($requete), $id_intitule);
}

//-- Fonctions de traitement ----------------------------------------------------------------------------
function MyAddSlashes($chaine)
{
    return  1 === get_magic_quotes_gpc() ?
        $chaine :
        addslashes($chaine);
}

function MyStripSlashes($chaine)
{
    return  1 === get_magic_quotes_gpc() ?
        stripslashes($chaine) :
        $chaine;
}

/**
 * @param string $var
 * @param bool   $like
 * @param bool   $from_request_method
 *
 * @return string
 */
function sql_data($var, $like = false, $from_request_method = true)
{
    $start = (($like) ? "'%" : "'");
    $end = (($like) ? "%'" : "'");

    if (('' === trim($var) || null === $var) && !$like) {
        return 'NULL';
    } elseif (1 === get_magic_quotes_gpc() && $from_request_method) {
        return $start.mysql_real_escape_string(stripslashes($var)).$end;
    }

    return $start.mysql_real_escape_string($var).$end;
}

/**
 * Fonction permettant de former un mot pour gérer plusieurs cas de figure dans un regexp mysql.
 *
 * @param string      $mot
 * @param bool        $html_entities
 * @param string|null $lang_code
 *
 * @return string|string[]|null
 */
function sql_regexp_search_expression($mot, $html_entities = false, $lang_code = null)
{
    static $liste_search = array(), $liste_replace = array(0 => array(), 1 => array());

    // Retirer le superflut (accent, majuscule, caractères interdit)
    $mot = removeAccents(mb_strtolower(trim($mot)));
    $mot = preg_replace('#[^a-z0-9., ]#', '', $mot);

    // Créer une liste de lettre à remplacer
    if (empty($liste_replace[$html_entities])) {
        // Liste des accents/caractères supportés
        $expression = array(
            'a' => array('à', 'á', 'â', 'ã', 'å'),
            'c' => array('ç'),
            'e' => array('é', 'è', 'ê', 'ë'),
            'i' => array('ì', 'í', 'î', 'ï'),
            'n' => array('ñ'),
            'o' => array('ô', 'ð', 'ó', 'ò', 'ô', 'õ', 'ö'),
            'u' => array('ù', 'ú', 'û', 'ü'),
            'y' => array('ý'),
        );

        // Fonction interne pour ajouter automatiquement les accents à la liste
        $func_add_accent_entities = create_function(
            '&$key',
            'return htmlentities($key, ENT_QUOTES, \'ISO-8859-1\');'
        );

        $liste_search = array_keys($expression);
        foreach ($expression as $lettre => $accent) {
            // Ajouter les équivalents TAGS htmlentities
            if ($html_entities) {
                $accent = array_merge($accent, array_map($func_add_accent_entities, $accent));
            }

            $liste_replace[$html_entities][] = "($lettre|".implode('|', $accent).')';
        }

        // Ajout d'expressions supplémentaires
        $liste_search[] = '.';
        $liste_replace[$html_entities][] = '\.';
    }

    // Gérer plz cas de figures d'accents
    $mot = str_replace(
        $liste_search,
        $liste_replace[$html_entities],
        $mot
    );

    // Gérer le pluriel (FR)
    if ('fr' === $lang_code && preg_match('#(s|x)$#i', $mot)) {
        $mot = preg_replace('#(s|x)$#i', '$1?', $mot);
    }

    return $mot;
}

/**
 * @param string $str
 * @param bool   $utf8
 *
 * @return string
 */
function removeAccents($str, $utf8 = true)
{
    $str = (string) $str;
    if (null === $utf8) {
        if (!function_exists('mb_detect_encoding')) {
            $length = mb_strlen($str);
            $utf8 = true;
            for ($i = 0; $i < $length; ++$i) {
                $c = ord($str[$i]);
                if ($c < 0x80) {
                    $n = 0;
                } // 0bbbbbbb
                elseif (0xC0 === ($c & 0xE0)) {
                    $n = 1;
                } // 110bbbbb
                elseif (0xE0 === ($c & 0xF0)) {
                    $n = 2;
                } // 1110bbbb
                elseif (0xF0 === ($c & 0xF8)) {
                    $n = 3;
                } // 11110bbb
                elseif (0xF8 === ($c & 0xFC)) {
                    $n = 4;
                } // 111110bb
                elseif (0xFC === ($c & 0xFE)) {
                    $n = 5;
                } // 1111110b
                else {
                    return false;
                } // Does not match any model
                for ($j = 0; $j < $n; ++$j) {
                    // n bytes matching 10bbbbbb follow ?
                    if ((++$i === $length) || (0x80 !== (ord($str[$i]) & 0xC0))) {
                        $utf8 = false;
                        break;
                    }
                }
            }
        } else {
            $utf8 = ('utf-8' === mb_strtolower(mb_detect_encoding($str, mb_detect_order(), true)));
        }
    }

    if (!$utf8) {
        $str = utf8_encode($str);
    }

    $transliteration = array(
        'Ĳ' => 'I', 'Ö' => 'O', 'Œ' => 'O', 'Ü' => 'U', 'ä' => 'a', 'æ' => 'a',
        'ĳ' => 'i', 'ö' => 'o', 'œ' => 'o', 'ü' => 'u', 'ß' => 's', 'ſ' => 's',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'Æ' => 'A', 'Ā' => 'A', 'Ą' => 'A', 'Ă' => 'A', 'Ç' => 'C', 'Ć' => 'C',
        'Č' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Ď' => 'D', 'Đ' => 'D', 'È' => 'E',
        'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ę' => 'E', 'Ě' => 'E',
        'Ĕ' => 'E', 'Ė' => 'E', 'Ĝ' => 'G', 'Ğ' => 'G', 'Ġ' => 'G', 'Ģ' => 'G',
        'Ĥ' => 'H', 'Ħ' => 'H', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ī' => 'I', 'Ĩ' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I', 'Ĵ' => 'J',
        'Ķ' => 'K', 'Ľ' => 'K', 'Ĺ' => 'K', 'Ļ' => 'K', 'Ŀ' => 'K', 'Ł' => 'L',
        'Ñ' => 'N', 'Ń' => 'N', 'Ň' => 'N', 'Ņ' => 'N', 'Ŋ' => 'N', 'Ò' => 'O',
        'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ø' => 'O', 'Ō' => 'O', 'Ő' => 'O',
        'Ŏ' => 'O', 'Ŕ' => 'R', 'Ř' => 'R', 'Ŗ' => 'R', 'Ś' => 'S', 'Ş' => 'S',
        'Ŝ' => 'S', 'Ș' => 'S', 'Š' => 'S', 'Ť' => 'T', 'Ţ' => 'T', 'Ŧ' => 'T',
        'Ț' => 'T', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ū' => 'U', 'Ů' => 'U',
        'Ű' => 'U', 'Ŭ' => 'U', 'Ũ' => 'U', 'Ų' => 'U', 'Ŵ' => 'W', 'Ŷ' => 'Y',
        'Ÿ' => 'Y', 'Ý' => 'Y', 'Ź' => 'Z', 'Ż' => 'Z', 'Ž' => 'Z', 'à' => 'a',
        'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a',
        'å' => 'a', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ċ' => 'c',
        'ď' => 'd', 'đ' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ƒ' => 'f',
        'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g', 'ĥ' => 'h', 'ħ' => 'h',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ĩ' => 'i',
        'ĭ' => 'i', 'į' => 'i', 'ı' => 'i', 'ĵ' => 'j', 'ķ' => 'k', 'ĸ' => 'k',
        'ł' => 'l', 'ľ' => 'l', 'ĺ' => 'l', 'ļ' => 'l', 'ŀ' => 'l', 'ñ' => 'n',
        'ń' => 'n', 'ň' => 'n', 'ņ' => 'n', 'ŉ' => 'n', 'ŋ' => 'n', 'ò' => 'o',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o',
        'ŏ' => 'o', 'ŕ' => 'r', 'ř' => 'r', 'ŗ' => 'r', 'ś' => 's', 'š' => 's',
        'ť' => 't', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ū' => 'u', 'ů' => 'u',
        'ű' => 'u', 'ŭ' => 'u', 'ũ' => 'u', 'ų' => 'u', 'ŵ' => 'w', 'ÿ' => 'y',
        'ý' => 'y', 'ŷ' => 'y', 'ż' => 'z', 'ź' => 'z', 'ž' => 'z', 'Α' => 'A',
        'Ά' => 'A', 'Ἀ' => 'A', 'Ἁ' => 'A', 'Ἂ' => 'A', 'Ἃ' => 'A', 'Ἄ' => 'A',
        'Ἅ' => 'A', 'Ἆ' => 'A', 'Ἇ' => 'A', 'ᾈ' => 'A', 'ᾉ' => 'A', 'ᾊ' => 'A',
        'ᾋ' => 'A', 'ᾌ' => 'A', 'ᾍ' => 'A', 'ᾎ' => 'A', 'ᾏ' => 'A', 'Ᾰ' => 'A',
        'Ᾱ' => 'A', 'Ὰ' => 'A', 'ᾼ' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D',
        'Ε' => 'E', 'Έ' => 'E', 'Ἐ' => 'E', 'Ἑ' => 'E', 'Ἒ' => 'E', 'Ἓ' => 'E',
        'Ἔ' => 'E', 'Ἕ' => 'E', 'Ὲ' => 'E', 'Ζ' => 'Z', 'Η' => 'I', 'Ή' => 'I',
        'Ἠ' => 'I', 'Ἡ' => 'I', 'Ἢ' => 'I', 'Ἣ' => 'I', 'Ἤ' => 'I', 'Ἥ' => 'I',
        'Ἦ' => 'I', 'Ἧ' => 'I', 'ᾘ' => 'I', 'ᾙ' => 'I', 'ᾚ' => 'I', 'ᾛ' => 'I',
        'ᾜ' => 'I', 'ᾝ' => 'I', 'ᾞ' => 'I', 'ᾟ' => 'I', 'Ὴ' => 'I', 'ῌ' => 'I',
        'Θ' => 'T', 'Ι' => 'I', 'Ί' => 'I', 'Ϊ' => 'I', 'Ἰ' => 'I', 'Ἱ' => 'I',
        'Ἲ' => 'I', 'Ἳ' => 'I', 'Ἴ' => 'I', 'Ἵ' => 'I', 'Ἶ' => 'I', 'Ἷ' => 'I',
        'Ῐ' => 'I', 'Ῑ' => 'I', 'Ὶ' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M',
        'Ν' => 'N', 'Ξ' => 'K', 'Ο' => 'O', 'Ό' => 'O', 'Ὀ' => 'O', 'Ὁ' => 'O',
        'Ὂ' => 'O', 'Ὃ' => 'O', 'Ὄ' => 'O', 'Ὅ' => 'O', 'Ὸ' => 'O', 'Π' => 'P',
        'Ρ' => 'R', 'Ῥ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Ύ' => 'Y',
        'Ϋ' => 'Y', 'Ὑ' => 'Y', 'Ὓ' => 'Y', 'Ὕ' => 'Y', 'Ὗ' => 'Y', 'Ῠ' => 'Y',
        'Ῡ' => 'Y', 'Ὺ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'P', 'Ω' => 'O',
        'Ώ' => 'O', 'Ὠ' => 'O', 'Ὡ' => 'O', 'Ὢ' => 'O', 'Ὣ' => 'O', 'Ὤ' => 'O',
        'Ὥ' => 'O', 'Ὦ' => 'O', 'Ὧ' => 'O', 'ᾨ' => 'O', 'ᾩ' => 'O', 'ᾪ' => 'O',
        'ᾫ' => 'O', 'ᾬ' => 'O', 'ᾭ' => 'O', 'ᾮ' => 'O', 'ᾯ' => 'O', 'Ὼ' => 'O',
        'ῼ' => 'O', 'α' => 'a', 'ά' => 'a', 'ἀ' => 'a', 'ἁ' => 'a', 'ἂ' => 'a',
        'ἃ' => 'a', 'ἄ' => 'a', 'ἅ' => 'a', 'ἆ' => 'a', 'ἇ' => 'a', 'ᾀ' => 'a',
        'ᾁ' => 'a', 'ᾂ' => 'a', 'ᾃ' => 'a', 'ᾄ' => 'a', 'ᾅ' => 'a', 'ᾆ' => 'a',
        'ᾇ' => 'a', 'ὰ' => 'a', 'ᾰ' => 'a', 'ᾱ' => 'a', 'ᾲ' => 'a', 'ᾳ' => 'a',
        'ᾴ' => 'a', 'ᾶ' => 'a', 'ᾷ' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd',
        'ε' => 'e', 'έ' => 'e', 'ἐ' => 'e', 'ἑ' => 'e', 'ἒ' => 'e', 'ἓ' => 'e',
        'ἔ' => 'e', 'ἕ' => 'e', 'ὲ' => 'e', 'ζ' => 'z', 'η' => 'i', 'ή' => 'i',
        'ἠ' => 'i', 'ἡ' => 'i', 'ἢ' => 'i', 'ἣ' => 'i', 'ἤ' => 'i', 'ἥ' => 'i',
        'ἦ' => 'i', 'ἧ' => 'i', 'ᾐ' => 'i', 'ᾑ' => 'i', 'ᾒ' => 'i', 'ᾓ' => 'i',
        'ᾔ' => 'i', 'ᾕ' => 'i', 'ᾖ' => 'i', 'ᾗ' => 'i', 'ὴ' => 'i', 'ῂ' => 'i',
        'ῃ' => 'i', 'ῄ' => 'i', 'ῆ' => 'i', 'ῇ' => 'i', 'θ' => 't', 'ι' => 'i',
        'ί' => 'i', 'ϊ' => 'i', 'ΐ' => 'i', 'ἰ' => 'i', 'ἱ' => 'i', 'ἲ' => 'i',
        'ἳ' => 'i', 'ἴ' => 'i', 'ἵ' => 'i', 'ἶ' => 'i', 'ἷ' => 'i', 'ὶ' => 'i',
        'ῐ' => 'i', 'ῑ' => 'i', 'ῒ' => 'i', 'ῖ' => 'i', 'ῗ' => 'i', 'κ' => 'k',
        'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'k', 'ο' => 'o', 'ό' => 'o',
        'ὀ' => 'o', 'ὁ' => 'o', 'ὂ' => 'o', 'ὃ' => 'o', 'ὄ' => 'o', 'ὅ' => 'o',
        'ὸ' => 'o', 'π' => 'p', 'ρ' => 'r', 'ῤ' => 'r', 'ῥ' => 'r', 'σ' => 's',
        'ς' => 's', 'τ' => 't', 'υ' => 'y', 'ύ' => 'y', 'ϋ' => 'y', 'ΰ' => 'y',
        'ὐ' => 'y', 'ὑ' => 'y', 'ὒ' => 'y', 'ὓ' => 'y', 'ὔ' => 'y', 'ὕ' => 'y',
        'ὖ' => 'y', 'ὗ' => 'y', 'ὺ' => 'y', 'ῠ' => 'y', 'ῡ' => 'y', 'ῢ' => 'y',
        'ῦ' => 'y', 'ῧ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'p', 'ω' => 'o',
        'ώ' => 'o', 'ὠ' => 'o', 'ὡ' => 'o', 'ὢ' => 'o', 'ὣ' => 'o', 'ὤ' => 'o',
        'ὥ' => 'o', 'ὦ' => 'o', 'ὧ' => 'o', 'ᾠ' => 'o', 'ᾡ' => 'o', 'ᾢ' => 'o',
        'ᾣ' => 'o', 'ᾤ' => 'o', 'ᾥ' => 'o', 'ᾦ' => 'o', 'ᾧ' => 'o', 'ὼ' => 'o',
        'ῲ' => 'o', 'ῳ' => 'o', 'ῴ' => 'o', 'ῶ' => 'o', 'ῷ' => 'o', 'А' => 'A',
        'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'E',
        'Ж' => 'Z', 'З' => 'Z', 'И' => 'I', 'Й' => 'I', 'К' => 'K', 'Л' => 'L',
        'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S',
        'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'K', 'Ц' => 'T', 'Ч' => 'C',
        'Ш' => 'S', 'Щ' => 'S', 'Ы' => 'Y', 'Э' => 'E', 'Ю' => 'Y', 'Я' => 'Y',
        'а' => 'A', 'б' => 'B', 'в' => 'V', 'г' => 'G', 'д' => 'D', 'е' => 'E',
        'ё' => 'E', 'ж' => 'Z', 'з' => 'Z', 'и' => 'I', 'й' => 'I', 'к' => 'K',
        'л' => 'L', 'м' => 'M', 'н' => 'N', 'о' => 'O', 'п' => 'P', 'р' => 'R',
        'с' => 'S', 'т' => 'T', 'у' => 'U', 'ф' => 'F', 'х' => 'K', 'ц' => 'T',
        'ч' => 'C', 'ш' => 'S', 'щ' => 'S', 'ы' => 'Y', 'э' => 'E', 'ю' => 'Y',
        'я' => 'Y', 'ð' => 'd', 'Ð' => 'D', 'þ' => 't', 'Þ' => 'T', 'ა' => 'a',
        'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v', 'ზ' => 'z',
        'თ' => 't', 'ი' => 'i', 'კ' => 'k', 'ლ' => 'l', 'მ' => 'm', 'ნ' => 'n',
        'ო' => 'o', 'პ' => 'p', 'ჟ' => 'z', 'რ' => 'r', 'ს' => 's', 'ტ' => 't',
        'უ' => 'u', 'ფ' => 'p', 'ქ' => 'k', 'ღ' => 'g', 'ყ' => 'q', 'შ' => 's',
        'ჩ' => 'c', 'ც' => 't', 'ძ' => 'd', 'წ' => 't', 'ჭ' => 'c', 'ხ' => 'k',
        'ჯ' => 'j', 'ჰ' => 'h',
    );

    $str = str_replace(
        array_keys($transliteration),
        array_values($transliteration),
        $str
    );

    return $str;
}

//-- Gestionnaire de variable stocké en base pour projet custom -----------------------------------------
/*
    CREATE TABLE IF NOT EXISTS `variable` (
      `name` varchar(100) NOT NULL,
      `value` text,
      `serialize` tinyint(1) unsigned NOT NULL DEFAULT '0',
      `dateUpdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Diverses variables et options pour l''application';

    ALTER TABLE `variable` ADD PRIMARY KEY (`name`);
*/

// Nécessite la fonction sql_data (mysql.inc.php)
// function variable_get($var_name, $value_defaut = null, &$pdo)
function variable_get($var_name, $value_defaut = null)
{
    $req = mysql_fetch_result('
		SELECT value, serialize
		FROM `variable`
		WHERE name = '.sql_data($var_name).'
		LIMIT 1
	');

    if (isset($req[0]['value'])) {
        return ($req[0]['serialize']) ? unserialize($req[0]['value']) : $req[0]['value'];
    }

    return $value_defaut;
}

function variable_save($var_name, $value)
{
    $serialize = false;
    if (is_array($value) || is_object($value)) {
        $serialize = true;
        $value = serialize($value);
    }

    return mysql_req('
		REPLACE INTO `variable`
				 SET name = '.sql_data($var_name).',
				 	 value = '.sql_data($value).',
				 	 serialize = '.sql_data((int) $serialize)
    );
}

function variable_delete($var_name)
{
    return mysql_req('DELETE `variable` WHERE name = '.sql_data($var_name).' LIMIT 1');
}
