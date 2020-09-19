# Component Database
Some class and tools for database usage: Pdo functions, Pagination, Table Configuration...

* [Navigation Page](src/NavigationPage.php): Page pagination with mysql or sqlite.
* [Pdo Utils](src/PdoUtils.php): Some utils method with PDO (connexion, insert, update, etc).
* [Pdo Table Configuration](src/PdoTableConfiguration.php): Management of a configuration table, inspired by the `wp_options` table (wordpress).
* [Pdo Alice](src/PdoAlice.php): Generation of fixtures in the database from YAML file _(alice)_ with PDO for PHP Legacy.


## Prerequisite

* PHP 4 (v1.0), PHP 5.3 (v1.1), PHP 5.4 (v1.2), PHP 5.5 (v1.3), PHP 5.6+ (v1.4+) or 7.4 (v2)
* Pdo Mysql, Sqlite 3
* (optional) [Alice fixtures](https://github.com/nelmio/alice)

## Install
Edit your [composer.json](https://getcomposer.org) (launch `composer update` after edit):
```json
{
  "repositories": [
    { "type": "git", "url": "git@github.com:jgauthi/component_database.git" }
  ],
  "require": {
    "jgauthi/component_database": "1.*"
  }
}
```

## Documentation
You can look at [folder example](example).

