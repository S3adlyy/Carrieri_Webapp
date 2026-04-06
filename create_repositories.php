<?php
// create_repositories.php
echo "========================================\n";
echo "Génération automatique des repositories\n";
echo "========================================\n\n";

// Récupérer toutes les entités
$entities = glob("src/Entity/*.php");

foreach ($entities as $entityFile) {
    $entityName = basename($entityFile, '.php');

    // Vérifier si le repository existe déjà
    $repositoryFile = "src/Repository/{$entityName}Repository.php";

    if (!file_exists($repositoryFile)) {
        echo "📝 Création du repository pour: $entityName\n";

        // Créer le contenu du repository
        $content = "<?php\n\n";
        $content .= "namespace App\\Repository;\n\n";
        $content .= "use App\\Entity\\$entityName;\n";
        $content .= "use Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository;\n";
        $content .= "use Doctrine\\Persistence\\ManagerRegistry;\n\n";
        $content .= "/**\n";
        $content .= " * @extends ServiceEntityRepository<$entityName>\n";
        $content .= " */\n";
        $content .= "class {$entityName}Repository extends ServiceEntityRepository\n";
        $content .= "{\n";
        $content .= "    public function __construct(ManagerRegistry \$registry)\n";
        $content .= "    {\n";
        $content .= "        parent::__construct(\$registry, $entityName::class);\n";
        $content .= "    }\n\n";
        $content .= "    // Ajoutez vos méthodes personnalisées ici\n";
        $content .= "    // public function findBySomething(\$value): array\n";
        $content .= "    // {\n";
        $content .= "    //     return \$this->createQueryBuilder('e')\n";
        $content .= "    //         ->andWhere('e.exampleField = :val')\n";
        $content .= "    //         ->setParameter('val', \$value)\n";
        $content .= "    //         ->orderBy('e.id', 'ASC')\n";
        $content .= "    //         ->setMaxResults(10)\n";
        $content .= "    //         ->getQuery()\n";
        $content .= "    //         ->getResult()\n";
        $content .= "    //     ;\n";
        $content .= "    // }\n";
        $content .= "}\n";

        // Créer le dossier Repository s'il n'existe pas
        if (!is_dir('src/Repository')) {
            mkdir('src/Repository', 0777, true);
        }

        // Écrire le fichier
        file_put_contents($repositoryFile, $content);
        echo "  ✅ Créé: src/Repository/{$entityName}Repository.php\n";
    } else {
        echo "⚠️  Le repository pour $entityName existe déjà\n";
    }
}

echo "\n========================================\n";
echo "✨ Tous les repositories ont été créés !\n";
echo "========================================\n";