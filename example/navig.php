<?php
// In this example, the vendor folder is located in "example/"
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/inc/config.inc.php';

// Requête
mysql_init(DB_HOST, DB_USER, DB_PASS, DB_NAME, 'utf8');
$req = 'SELECT code, nom FROM `departement` ORDER BY code';

$nav = new NavigationPage($req, 10, 5);
$nav->navig_titre(array('code' => 'Code', 'nom' => 'Département'));
$departement = $nav->query();

$navigation = $nav->get_navigation();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Test et optimisation Navig</title>

    <link rel="stylesheet" href="//stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<body>
<main role="main" class="container">

    <h1>Test et optimisation Navig</h1>
    <?php if ($nav->nb_query() > 0): ?>
    <table class="table table-striped">
        <thead class="thead-dark">
        <tr>
            <?=$nav->afficher_titre(); ?>
        </tr>
        </thead>
        <tbody>
        <?php while ($res = mysql_fetch_all($departement)): ?>
            <tr>
                <th scope="row"><?=$res['code']; ?></th>
                <td><?=htmlentities($res['nom'], ENT_QUOTES, 'utf-8'); ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>


    <?php if ($nav->nb_page() > 1): // // Navigation page?>
        <nav aria-label="Page navigation" style="margin-top: 15px;">
            <ul class="pagination justify-content-center">
                <?php foreach ($navigation as $pagin_page): ?>
                    <li class="page-item <?=$pagin_page['status']; ?>">
                        <a class="page-link" href="<?=$pagin_page['url']; ?>"><?=$pagin_page['text']; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>


<?php else: ?>
    <p>Aucun département trouvé.</p>
<?php endif; ?>

</main>
</body>
</html>