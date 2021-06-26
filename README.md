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

### Spécifier une table 

```php
$Queries = (new Queries($PDO))
    			->table('example');
```

La méthode `Queries::table()` permet de spécifier une table. 

### Requête SELECT 

``` php
$Queries = (new Queries($PDO))
    			->table('example')
    			->select();
```

Permet de récupérer les résultats de toute la table. 

#### Avec une clause ORDER BY 

```php
$Queries = (new Queries($PDO))
    			->table('example')
    			->orderBy('timestamp DESC')
    			->select();
```

Permet d'organiser les résultats triés par timestamp en ordre descendant.

#### Avec une clause LIMIT 

```php
$Queries = (new Queries($PDO))
    			->table('example')
    			->limit('0, 10')
    			->select();
```

Permet de ne récupèrer que les 10 premiers résultats.

#### Avec une jointure 

```php
$Queries = (new Queries($PDO))
    			->table('example')
    			->join('another_exemple', 'LEFT', 'ON another_example.ex_id = example.id',
                      	['another_example.id AS another_example_id', 	
                         'another_example.content'])
    			->select();
```

Permet de récuperer les résultats avec une jointure sur  la table *another_example*.

### Requête INSERT / UPDATE

**Requête INSERT**

```php
$Queries = (new Queries($PDO))
    			->table('example')
    			->bind('content', 'Lorem ipsum dolor sit amet', PDO::PARAM_STR)
    			->bind('timestamp', time(), PDO::PARAM_INT)
    			->save();
```

Après une requête INSERT, il est possible de récupérer l'id de l'élément inséré tel que : 

```php
$Queries = (new Queries($PDO))
    			->table('example')
    			->bind('content', 'Lorem ipsum dolor sit amet', PDO::PARAM_STR)
    			->bind('timestamp', time(), PDO::PARAM_INT)
    			->save();

$lastInsertId = $Queries->lastInsertId();
```

**Requete UPDATE** 

La différence, pour une requête UPDATE est que l'on spécifie l'id ainsi qu'une clause WHERE. 

```php
$Queries = (new Queries($PDO))
    			->table('example')
    			->bind('content', 'Lorem ipsum dolor sit amet', PDO::PARAM_STR)
    			->bind('timestamp', time(), PDO::PARAM_INT)
    			->bind('id',		'1',	PDO::PARAM_INT)
    			->where('id = :id')
    			->save();						
```

###  Requête DELETE

```php
$Queries = (new Queries($PDO))
    			->table('example')
    			->where('id > 10')
    			->delete();
```

