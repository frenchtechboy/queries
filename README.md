# La classe Queries, un générateur de requêtes écrit en PHP & SQL 

La classe Queries est un générateur de requête qui permet de ne pas écrire de code SQL pour toutes les requêtes basiques d'un projet. Elle est écrite avec PHP, dans le but de générer le code SQL propre aux requêtes à éxecuter. 

## Exemples

### Initialisation 

```php
[$host, $db, $user, $pass, $charset] = ['127.0.0.1', 'test', 'test', '', 'utf8mb4'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];

try {
    $PDO = new PDO($dsn, $user, $pass, $options);
}
catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
}

$Queries = new Queries($PDO);
```

.. permet d'instancier un nouvel objet de la classe Queries sur `$Queries` prenant en paramètre un objet de la classe PDO.

