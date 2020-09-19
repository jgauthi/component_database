<?php
use Jgauthi\Component\Database\PdoUtils;

// In this example, the vendor folder is located in "example/"
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/inc/config.inc.php';

try {
    $pdo = PdoUtils::sqlite_init(__DIR__ . '/inc/mindsymfony4.sqlite3');

    $sql = "
    SELECT post.id, post.title, user.fullname as author, post.published
    FROM blog_post post
    INNER JOIN user ON user.id = post.author_id
    ORDER BY post.published DESC
    LIMIT 10
    ";

    $stmt = $pdo->query($sql); // Simple, but has several drawbacks
    $results = $stmt->fetchAll();
    $titles = array_keys($results[0]);

} catch (Exception $e) {
    die("<p>{$e->getMessage()} on {$e->getFile()}:{$e->getLine()}</p>");
}

?>
<table class="table table-striped table-hover table-bordered">
    <caption>Recent Blog Post</caption>
    <thead class="thead-dark">
    <tr>
        <?php foreach ($titles as $title): ?>
            <th scope="col"><?=ucfirst($title)?></th>
        <?php endforeach ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($results as $result): ?>
    <tr>
        <td class="td_id"><?=$result['id']?></td>
        <td class="td_title"><?=$result['title']?></td>
        <td class="td_author"><?=$result['author']?></td>
        <td class="td_published"><?=$result['published']?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
