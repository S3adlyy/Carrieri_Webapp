<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Artifact;
use App\Entity\Track;
use App\Repository\ArtifactRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ArtifactService
{
    public function __construct(
        private readonly ArtifactRepository $artifactRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * @return Artifact[]
     */
    public function listActiveByTrack(Track $track): array
    {
        return $this->artifactRepository->createQueryBuilder('a')
            ->andWhere('a.track = :track')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('track', $track)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function create(
        Track $track,
        string $name,
        string $description,
        string $type,
        ?string $language = null,
        ?string $textContent = null
    ): Artifact {
        $artifact = new Artifact();
        $artifact->setTrack($track);
        $artifact->setTrackId($track->getId());
        $artifact->setArtifactName($name);
        $artifact->setArtifactDescription($description);
        $artifact->setArtifactType(strtoupper($type));
        $artifact->setLanguage($language);
        $artifact->setTestContent($textContent);
        $artifact->setCreatedAt(new \DateTimeImmutable());
        $artifact->setDeletedAt(null);

        $this->em->persist($artifact);
        $this->em->flush();

        return $artifact;
    }

    public function rename(Artifact $artifact, string $newName): void
    {
        $artifact->setArtifactName($newName);
        $this->em->flush();
    }

    public function updateTextContent(Artifact $artifact, ?string $content): void
    {
        $artifact->setTestContent($content);
        $this->em->flush();
    }

    public function softDelete(Artifact $artifact): void
    {
        $artifact->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function findById(int $id): ?Artifact
    {
        return $this->artifactRepository->find($id);
    }
}