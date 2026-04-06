<?php
// generate_entities_simple.php
require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$connectionParams = [
    'dbname' => 'carrieri',
    'user' => 'root',
    'password' => '',
    'host' => '127.0.0.1',
    'driver' => 'pdo_mysql',
];

$conn = DriverManager::getConnection($connectionParams);

// Récupérer toutes les tables directement avec SQL
$stmt = $conn->prepare("SHOW TABLES");
$result = $stmt->executeQuery();
$tables = $result->fetchFirstColumn();

$excludeTables = ['doctrine_migration_versions', 'messenger_messages'];
$tables = array_filter($tables, function($table) use ($excludeTables) {
    return !in_array($table, $excludeTables);
});

echo "\n========================================\n";
echo "Génération des entités complètes\n";
echo "========================================\n\n";

foreach ($tables as $table) {
    echo "📝 Traitement: $table\n";

    // Récupérer les colonnes avec SQL
    $stmt = $conn->prepare("DESCRIBE $table");
    $result = $stmt->executeQuery();
    $columns = $result->fetchAllAssociative();

    // Récupérer les clés étrangères avec SQL
    $stmt = $conn->prepare("
        SELECT 
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'carrieri'
        AND TABLE_NAME = '$table'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $result = $stmt->executeQuery();
    $foreignKeys = $result->fetchAllAssociative();

    $entityName = str_replace('_', '', ucwords($table, '_'));
    $properties = [];
    $relations = [];

    // Analyser les colonnes
    foreach ($columns as $column) {
        $columnName = $column['Field'];
        $propertyName = lcfirst(str_replace('_', '', ucwords($columnName, '_')));

        // Déterminer le type PHP à partir du type MySQL
        $mysqlType = $column['Type'];
        $phpType = 'string';

        if (str_contains($mysqlType, 'int')) {
            $phpType = 'int';
        } elseif (str_contains($mysqlType, 'bool') || str_contains($mysqlType, 'tinyint(1)')) {
            $phpType = 'bool';
        } elseif (str_contains($mysqlType, 'float') || str_contains($mysqlType, 'double') || str_contains($mysqlType, 'decimal')) {
            $phpType = 'float';
        } elseif (str_contains($mysqlType, 'datetime') || str_contains($mysqlType, 'timestamp')) {
            $phpType = '\\DateTimeInterface';
        } elseif (str_contains($mysqlType, 'date')) {
            $phpType = '\\DateTimeInterface';
        } elseif (str_contains($mysqlType, 'json')) {
            $phpType = 'array';
        }

        $nullable = $column['Null'] === 'YES' ? 'nullable: true' : '';
        $isPrimary = $column['Key'] === 'PRI';
        $isAuto = str_contains($column['Extra'], 'auto_increment');

        if ($isPrimary && $isAuto) {
            $properties[] = "    #[ORM\\Id]\n    #[ORM\\GeneratedValue]\n    #[ORM\\Column]\n    private ?int \$$propertyName = null;";
        } else {
            $properties[] = "    #[ORM\\Column]\n    private ?$phpType \$$propertyName = null;";
        }
    }

    // Analyser les relations
    foreach ($foreignKeys as $fk) {
        $foreignTable = $fk['REFERENCED_TABLE_NAME'];
        $foreignEntity = str_replace('_', '', ucwords($foreignTable, '_'));
        $relationName = lcfirst(str_replace('_', '', ucwords($foreignTable, '_')));
        $columnName = $fk['COLUMN_NAME'];

        $relations[] = "    #[ORM\\ManyToOne]\n    #[ORM\\JoinColumn(name: '$columnName', referencedColumnName: 'id')]\n    private ?$foreignEntity \$$relationName = null;";
    }

    // Générer le contenu
    $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Entity;\n\nuse Doctrine\\ORM\\Mapping as ORM;\n\n#[ORM\\Table(name: '$table')]\n#[ORM\\Entity]\nclass $entityName\n{\n";

    if (!empty($properties)) {
        $content .= implode("\n\n", $properties);
    }

    if (!empty($relations)) {
        $content .= "\n\n" . implode("\n\n", $relations);
    }

    // Getters et setters pour les propriétés
    foreach ($columns as $column) {
        $columnName = $column['Field'];
        $propertyName = lcfirst(str_replace('_', '', ucwords($columnName, '_')));

        $mysqlType = $column['Type'];
        $phpType = 'string';

        if (str_contains($mysqlType, 'int')) {
            $phpType = 'int';
        } elseif (str_contains($mysqlType, 'bool') || str_contains($mysqlType, 'tinyint(1)')) {
            $phpType = 'bool';
        } elseif (str_contains($mysqlType, 'float') || str_contains($mysqlType, 'double') || str_contains($mysqlType, 'decimal')) {
            $phpType = 'float';
        } elseif (str_contains($mysqlType, 'datetime') || str_contains($mysqlType, 'timestamp')) {
            $phpType = '\\DateTimeInterface';
        } elseif (str_contains($mysqlType, 'date')) {
            $phpType = '\\DateTimeInterface';
        } elseif (str_contains($mysqlType, 'json')) {
            $phpType = 'array';
        }

        $getter = 'get' . ucfirst($propertyName);
        $setter = 'set' . ucfirst($propertyName);

        $content .= "\n\n    public function $getter(): ?$phpType\n    {\n        return \$this->$propertyName;\n    }";
        $content .= "\n\n    public function $setter(?$phpType \$$propertyName): self\n    {\n        \$this->$propertyName = \$$propertyName;\n        return \$this;\n    }";
    }

    // Getters et setters pour les relations
    foreach ($foreignKeys as $fk) {
        $foreignTable = $fk['REFERENCED_TABLE_NAME'];
        $foreignEntity = str_replace('_', '', ucwords($foreignTable, '_'));
        $relationName = lcfirst(str_replace('_', '', ucwords($foreignTable, '_')));

        $getter = 'get' . ucfirst($relationName);
        $setter = 'set' . ucfirst($relationName);

        $content .= "\n\n    public function $getter(): ?$foreignEntity\n    {\n        return \$this->$relationName;\n    }";
        $content .= "\n\n    public function $setter(?$foreignEntity \$$relationName): self\n    {\n        \$this->$relationName = \$$relationName;\n        return \$this;\n    }";
    }

    $content .= "\n}\n";

    file_put_contents("src/Entity/$entityName.php", $content);
    echo "  ✅ Généré: src/Entity/$entityName.php\n";
}

echo "\n========================================\n";
echo "✨ Génération terminée avec succès !\n";
echo "📊 " . count($tables) . " entités générées\n";
echo "========================================\n";