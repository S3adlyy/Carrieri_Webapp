<?php

namespace App\Controller\DashboardController;

use App\Repository\MessageRepository;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/recruteur/messagerie')]
class RecruteurMessagerieController extends AbstractController
{
    #[Route('/', name: 'app_recruteur_messages')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Récupérer les conversations où le recruteur est user1 ou user2
        $conversations = $em->createQueryBuilder()
            ->select('c', 'u1', 'u2')
            ->from(Conversation::class, 'c')
            ->leftJoin('c.user1', 'u1')
            ->leftJoin('c.user2', 'u2')
            ->where('c.user1 = :userId OR c.user2 = :userId')
            ->andWhere('c.statut != :archived')
            ->setParameter('userId', $user)
            ->setParameter('archived', 'archived')
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();

        // Récupérer tous les candidats
        $candidats = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.type IN (:types)')
            ->setParameter('types', ['CANDIDATE', 'CANDIDAT'])
            ->getQuery()
            ->getResult();

        return $this->render('BackOffice/dashboard/messages/index.html.twig', [
            'conversations' => $conversations,
            'candidats' => $candidats,
        ]);
    }

    #[Route('/stats', name: 'app_recruteur_stats', methods: ['GET'])]
    public function getStats(EntityManagerInterface $em): JsonResponse
    {
        try {
            $recruteur = $this->getUser();

            if (!$recruteur) {
                return $this->json(['error' => 'Utilisateur non connecté'], 401);
            }

            $totalCandidats = $em->getRepository(User::class)
                ->createQueryBuilder('u')
                ->where('u.type IN (:types)')
                ->setParameter('types', ['CANDIDATE', 'CANDIDAT'])
                ->select('COUNT(u.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $candidatsEnLigne = $em->getRepository(Conversation::class)
                ->createQueryBuilder('c')
                ->select('COUNT(DISTINCT CASE WHEN c.user1 = :recruteur THEN IDENTITY(c.user2) ELSE IDENTITY(c.user1) END)')
                ->where('c.user1 = :recruteur OR c.user2 = :recruteur')
                ->setParameter('recruteur', $recruteur)
                ->getQuery()
                ->getSingleScalarResult();

            $messagesNonLus = $em->getRepository(Message::class)
                ->createQueryBuilder('m')
                ->where('m.destinataire = :recruteur')
                ->setParameter('recruteur', $recruteur)
                ->select('COUNT(m.id)')
                ->getQuery()
                ->getSingleScalarResult();

            return $this->json([
                'total_candidats' => (int)$totalCandidats,
                'candidats_en_ligne' => (int)$candidatsEnLigne,
                'messages_non_lus' => (int)$messagesNonLus,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/conversations', name: 'app_recruteur_get_conversations', methods: ['GET'])]
    public function getConversations(EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Utilisateur non connecté'], 401);
            }

            $conversations = $em->createQueryBuilder()
                ->select('c', 'u1', 'u2')
                ->from(Conversation::class, 'c')
                ->leftJoin('c.user1', 'u1')
                ->leftJoin('c.user2', 'u2')
                ->where('c.user1 = :userId OR c.user2 = :userId')
                ->andWhere('c.statut != :archived')
                ->setParameter('userId', $user)
                ->setParameter('archived', 'archived')
                ->orderBy('c.dateCreation', 'DESC')
                ->getQuery()
                ->getResult();

            $data = [];
            foreach ($conversations as $conv) {
                $otherUser = null;
                if ($conv->getUser1() && $conv->getUser1()->getId() === $user->getId()) {
                    $otherUser = $conv->getUser2();
                } else {
                    $otherUser = $conv->getUser1();
                }

                if (!$otherUser) {
                    continue;
                }

                $data[] = [
                    'id' => $conv->getId(),
                    'other_user_id' => $otherUser->getId(),
                    'other_user_name' => $otherUser->getFirstName() . ' ' . $otherUser->getLastName(),
                    'last_message' => $conv->getDernierMessage() ?? 'Aucun message',
                    'date' => $conv->getDateCreation() ? $conv->getDateCreation()->format('d/m') : '',
                    'unread_count' => 0,
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/conversation/{id}/messages', name: 'app_recruteur_get_messages', methods: ['GET'])]
    public function getMessages(int $id, MessageRepository $messageRepo): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Utilisateur non connecté'], 401);
            }

            $messages = $messageRepo->createQueryBuilder('m')
                ->where('m.conversation = :convId')
                ->setParameter('convId', $id)
                ->orderBy('m.dateEnvoi', 'ASC')
                ->getQuery()
                ->getResult();

            $data = [];
            foreach ($messages as $message) {
                $data[] = [
                    'id' => $message->getId(),
                    'contenu' => $message->getContenu(),
                    'date_envoi' => $message->getDateEnvoi()->format('H:i'),
                    'est_moi' => $message->getExpediteur() && $message->getExpediteur()->getId() === $user->getId(),
                    'statut' => $message->getStatut(),
                    'fileUrl' => $message->getFileUrl(),
                    'fileName' => $message->getFileName(),
                    'fileType' => $message->getFileType(),
                    'fileSize' => $message->getFileSize(),
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/send-message', name: 'app_candidate_send_message', methods: ['POST'])]
    public function sendMessage(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();
            $conversationId = $request->request->get('conversation_id');
            $content = $request->request->get('content');

            if (!$user) {
                return $this->json(['success' => false, 'error' => 'Utilisateur non connecté'], 401);
            }

            // Gestion du fichier
            $uploadedFile = $request->files->get('file');
            $fileUrl = null;
            $fileName = null;
            $fileType = null;
            $fileSize = null;

            if ($uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                $uploadDir = __DIR__ . '/../../public/uploads/messages/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $destination = $uploadDir . $newFilename;

                // Copier le fichier
                if (copy($uploadedFile->getPathname(), $destination)) {
                    $fileUrl = '/uploads/messages/' . $newFilename;
                    $fileName = $uploadedFile->getClientOriginalName();
                    $fileType = $uploadedFile->getClientMimeType();
                    $fileSize = filesize($destination);
                } else {
                    return $this->json(['success' => false, 'error' => 'Erreur lors de la copie du fichier']);
                }
            }

            // Validation
            if (empty(trim($content)) && !$uploadedFile) {
                return $this->json(['success' => false, 'error' => 'Message ou fichier requis']);
            }

            if ($content && !preg_match('/^[A-Z]/', trim($content))) {
                return $this->json(['success' => false, 'error' => 'Le message doit commencer par une majuscule']);
            }

            $conversation = $em->getRepository(Conversation::class)->find($conversationId);
            if (!$conversation) {
                return $this->json(['success' => false, 'error' => 'Conversation introuvable']);
            }

            $destinataire = $conversation->getUser1()->getId() === $user->getId()
                ? $conversation->getUser2()
                : $conversation->getUser1();

            if (!$destinataire) {
                return $this->json(['success' => false, 'error' => 'Destinataire introuvable']);
            }

            $message = new Message();
            $message->setContenu($content);
            $message->setDateEnvoi(new \DateTime());
            $message->setStatut('sent');
            $message->setType($uploadedFile ? 'file' : 'text');
            $message->setConversation($conversation);
            $message->setExpediteur($user);
            $message->setDestinataire($destinataire);

            if ($uploadedFile) {
                $message->setFileUrl($fileUrl);
                $message->setFileName($fileName);
                $message->setFileType($fileType);
                $message->setFileSize($fileSize);
            }

            $em->persist($message);
            $conversation->setDernierMessage($content ?: '[Fichier]');
            $conversation->setDateCreation(new \DateTime());
            $em->flush();

            return $this->json([
                'success' => true,
                'message_id' => $message->getId(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    #[Route('/new-conversation', name: 'app_recruteur_new_conversation', methods: ['POST'])]
    public function newConversation(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();
            $candidatId = $request->request->get('candidat_id');
            $content = $request->request->get('content');

            if (!$user) {
                return $this->json(['success' => false, 'error' => 'Utilisateur non connecté'], 401);
            }

            if (empty(trim($content))) {
                return $this->json(['success' => false, 'error' => 'Le message ne peut pas être vide']);
            }
            if (strlen($content) < 2) {
                return $this->json(['success' => false, 'error' => 'Le message doit contenir au moins 2 caractères']);
            }
            if (strlen($content) > 1000) {
                return $this->json(['success' => false, 'error' => 'Le message ne peut pas dépasser 1000 caractères']);
            }
            if (!preg_match('/^[A-Z]/', trim($content))) {
                return $this->json(['success' => false, 'error' => 'Le message doit commencer par une majuscule']);
            }

            $candidat = $em->getRepository(User::class)->find($candidatId);
            if (!$candidat) {
                return $this->json(['success' => false, 'error' => 'Candidat introuvable']);
            }

            $existingConversation = $em->createQueryBuilder()
                ->select('c')
                ->from(Conversation::class, 'c')
                ->where('(c.user1 = :user AND c.user2 = :candidat) OR (c.user1 = :candidat AND c.user2 = :user)')
                ->setParameter('user', $user)
                ->setParameter('candidat', $candidat)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingConversation) {
                $message = new Message();
                $message->setContenu($content);
                $message->setDateEnvoi(new \DateTime());
                $message->setStatut('sent');
                $message->setType('text');
                $message->setConversation($existingConversation);
                $message->setExpediteur($user);
                $message->setDestinataire($candidat);

                $em->persist($message);
                $existingConversation->setDernierMessage($content);
                $existingConversation->setDateCreation(new \DateTime());
                $em->flush();

                return $this->json(['success' => true, 'conversation_id' => $existingConversation->getId()]);
            }

            $conversation = new Conversation();
            $conversation->setUser1($user);
            $conversation->setUser2($candidat);
            $conversation->setDateCreation(new \DateTime());
            $conversation->setStatut('active');
            $conversation->setDernierMessage($content);

            $message = new Message();
            $message->setContenu($content);
            $message->setDateEnvoi(new \DateTime());
            $message->setStatut('sent');
            $message->setType('text');
            $message->setConversation($conversation);
            $message->setExpediteur($user);
            $message->setDestinataire($candidat);

            $em->persist($conversation);
            $em->persist($message);
            $em->flush();

            return $this->json(['success' => true, 'conversation_id' => $conversation->getId()]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/edit-message/{id}', name: 'app_recruteur_edit_message', methods: ['POST'])]
    public function editMessage(Message $message, Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();

            if ($message->getExpediteur()->getId() !== $user->getId()) {
                return $this->json(['success' => false, 'error' => 'Vous ne pouvez pas modifier ce message']);
            }

            $newContent = $request->request->get('content');

            if (empty(trim($newContent))) {
                return $this->json(['success' => false, 'error' => 'Le message ne peut pas être vide']);
            }
            if (strlen($newContent) < 2) {
                return $this->json(['success' => false, 'error' => 'Le message doit contenir au moins 2 caractères']);
            }
            if (strlen($newContent) > 1000) {
                return $this->json(['success' => false, 'error' => 'Le message ne peut pas dépasser 1000 caractères']);
            }
            if (!preg_match('/^[A-Z]/', trim($newContent))) {
                return $this->json(['success' => false, 'error' => 'Le message doit commencer par une majuscule']);
            }

            $message->setContenu($newContent);
            $message->setStatut('edited');
            $message->setDateModification(new \DateTime());

            $conversation = $message->getConversation();
            if ($conversation) {
                $conversation->setDernierMessage($newContent);
            }

            $em->flush();

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/delete-message/{id}', name: 'app_recruteur_delete_message', methods: ['DELETE'])]
    public function deleteMessage(Message $message, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();

            if ($message->getExpediteur()->getId() !== $user->getId()) {
                return $this->json(['success' => false, 'error' => 'Vous ne pouvez pas supprimer ce message']);
            }

            $conversation = $message->getConversation();
            $em->remove($message);

            if ($conversation) {
                $lastMessage = $em->getRepository(Message::class)
                    ->createQueryBuilder('m')
                    ->where('m.conversation = :conv')
                    ->setParameter('conv', $conversation)
                    ->orderBy('m.dateEnvoi', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                $conversation->setDernierMessage($lastMessage ? $lastMessage->getContenu() : null);
            }

            $em->flush();

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}