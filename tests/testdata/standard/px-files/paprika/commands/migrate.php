<?php
echo '---------------- migrate'."\n";

$pdo = $paprika->pdo();

var_dump($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
// var_dump($pdo->getAttribute(PDO::ATTR_SERVER_INFO));
// var_dump($pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

$result = $pdo->query('CREATE TABLE IF NOT EXISTS test_table (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name VARCHAR
);');
// var_dump($result);



$stmt = $pdo->prepare('INSERT INTO test_table (name) VALUES (:name);');
// var_dump($stmt);
$name = 'Test Name';
$stmt->bindParam(':name', $name, \PDO::PARAM_STR);
$stmt->execute();



// $stmt = $pdo->prepare('SELECT name from test_table WHERE id = 1;');
$stmt = $pdo->query('SELECT name from test_table WHERE id = 1;');
$result = $stmt->fetch();
var_dump($result);

exit;
