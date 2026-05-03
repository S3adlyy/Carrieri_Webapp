<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\DBAL\Schema\AbstractSchemaManager;


#[AsCommand(
    name: 'app:generate:entities',
    description: 'Generate complete entities with properties and relations from database',
)]
class GenerateEntitiesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $connection = $this->entityManager->getConnection();
            $schemaManager = $connection->createSchemaManager();

            $allTables = $schemaManager->listTableNames();
            $excludeTables = ['doctrine_migration_versions', 'messenger_messages'];
            $tables = array_filter($allTables, function($table) use ($excludeTables) {
                return !in_array($table, $excludeTables);
            });

            $io->success(sprintf('Found %d tables', count($tables)));

            $filesystem = new Filesystem();
            if (!$filesystem->exists('src/Entity')) {
                $filesystem->mkdir('src/Entity');
            }

            $generated = 0;

            // First pass: generate all entities
            foreach ($tables as $table) {
                $this->generateEntityWithProperties($table, $schemaManager, $io);
                $generated++;
            }

            $io->success(sprintf("Generated %d complete entities with properties!", $generated));
            $io->note('Run "php bin/console doctrine:schema:validate" to verify everything is correct');

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function generateEntityWithProperties(string $tableName,  $schemaManager, SymfonyStyle $io): void
    {
        // Get columns and foreign keys
        $columns = $schemaManager->listTableColumns($tableName);
        $foreignKeys = $schemaManager->listTableForeignKeys($tableName);

        $entityName = $this->tableToEntityName($tableName);
        $properties = [];
        $useStatements = [];

        // Track if we have relations
        $hasManyToOne = false;
        $hasOneToMany = false;

        foreach ($columns as $column) {
            $columnName = $column->getName();
            $propertyName = lcfirst($this->tableToEntityName($columnName));

            // Get type
            $dbType = $column->getType()->getName();
            $phpType = $this->getPhpType($dbType);
            $nullable = !$column->getNotnull() ? 'nullable: true' : '';

            // Check if it's a primary key
            if ($column->getAutoincrement()) {
                $properties[] = "    #[ORM\\Id]\n    #[ORM\\GeneratedValue]\n    #[ORM\\Column]\n    private ?int \$$propertyName = null;";
            } else {
                $properties[] = sprintf(
                    "    #[ORM\\Column(type: '%s'%s)]\n    private ?%s \$$propertyName = null;",
                    $dbType,
                    $nullable ? ', nullable: true' : '',
                    $phpType,
                    $propertyName
                );
            }
        }

        // Add ManyToOne relationships (foreign keys)
        foreach ($foreignKeys as $foreignKey) {
            $hasManyToOne = true;
            $localColumns = $foreignKey->getLocalColumns();
            $foreignTableName = $foreignKey->getForeignTableName();
            $foreignColumns = $foreignKey->getForeignColumns();

            if (!empty($localColumns)) {
                $localColumn = $localColumns[0];
                $propertyName = lcfirst($this->tableToEntityName($foreignTableName));

                // Remove _id suffix if present
                $propertyName = str_replace('Id', '', $propertyName);

                $useStatements[] = "use App\\Entity\\" . $this->tableToEntityName($foreignTableName) . ";";
                $properties[] = sprintf(
                    "    #[ORM\\ManyToOne]\n    #[ORM\\JoinColumn(name: '%s', referencedColumnName: '%s')]\n    private ?%s \$%s = null;",
                    $localColumn,
                    $foreignColumns[0],
                    $this->tableToEntityName($foreignTableName),
                    $propertyName
                );
            }
        }

        // Generate the complete entity class
        $useStatementsUnique = array_unique($useStatements);
        $useStatementsString = !empty($useStatementsUnique) ? "\n" . implode("\n", $useStatementsUnique) : '';

        $entityContent = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Entity;{$useStatementsString}\n\nuse Doctrine\\ORM\\Mapping as ORM;\n\n#[ORM\\Table(name: '{$tableName}')]\n#[ORM\\Entity(repositoryClass: 'App\\Repository\\{$entityName}Repository')]\nclass {$entityName}\n{\n" . implode("\n\n", $properties) . "\n\n";

        // Add getters and setters
        foreach ($columns as $column) {
            $columnName = $column->getName();
            $propertyName = lcfirst($this->tableToEntityName($columnName));
            $dbType = $column->getType()->getName();
            $phpType = $this->getPhpType($dbType);

            if ($column->getAutoincrement()) {
                $entityContent .= $this->generateGetterSetter($propertyName, $phpType);
            } else {
                $entityContent .= $this->generateGetterSetter($propertyName, $phpType);
            }
        }

        // Add getters and setters for ManyToOne relations
        foreach ($foreignKeys as $foreignKey) {
            $localColumns = $foreignKey->getLocalColumns();
            $foreignTableName = $foreignKey->getForeignTableName();

            if (!empty($localColumns)) {
                $propertyName = lcfirst($this->tableToEntityName($foreignTableName));
                $propertyName = str_replace('Id', '', $propertyName);
                $targetEntity = $this->tableToEntityName($foreignTableName);

                $entityContent .= $this->generateRelationGetterSetter($propertyName, $targetEntity);
            }
        }

        $entityContent .= "}\n";

        // Write the entity file
        $fullPath = 'src/Entity/' . $entityName . '.php';
        file_put_contents($fullPath, $entityContent);
        $io->text(sprintf('  ✓ Generated: <info>%s</info> with %d properties', $fullPath, count($columns)));
    }

    private function generateGetterSetter(string $propertyName, string $phpType): string
    {
        $getterName = 'get' . ucfirst($propertyName);
        $setterName = 'set' . ucfirst($propertyName);

        return "\n    public function {$getterName}(): ?{$phpType}\n    {\n        return \$this->{$propertyName};\n    }\n\n    public function {$setterName}(?{$phpType} \${$propertyName}): self\n    {\n        \$this->{$propertyName} = \${$propertyName};\n        return \$this;\n    }\n";
    }

    private function generateRelationGetterSetter(string $propertyName, string $targetEntity): string
    {
        $getterName = 'get' . ucfirst($propertyName);
        $setterName = 'set' . ucfirst($propertyName);

        return "\n    public function {$getterName}(): ?{$targetEntity}\n    {\n        return \$this->{$propertyName};\n    }\n\n    public function {$setterName}(?{$targetEntity} \${$propertyName}): self\n    {\n        \$this->{$propertyName} = \${$propertyName};\n        return \$this;\n    }\n";
    }

    private function tableToEntityName(string $table): string
    {
        return str_replace('_', '', ucwords($table, '_'));
    }

    private function getPhpType(string $doctrineType): string
    {
        $mapping = [
            'integer' => 'int',
            'smallint' => 'int',
            'bigint' => 'int',
            'string' => 'string',
            'text' => 'string',
            'datetime' => '\\DateTimeInterface',
            'datetimetz' => '\\DateTimeInterface',
            'date' => '\\DateTimeInterface',
            'time' => '\\DateTimeInterface',
            'boolean' => 'bool',
            'decimal' => 'float',
            'float' => 'float',
            'json' => 'array',
        ];

        return $mapping[$doctrineType] ?? 'string';
    }
}


