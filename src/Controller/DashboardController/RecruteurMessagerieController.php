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

        // Récupérer tous les candidats (utilisateurs avec type 'CANDIDATE')
        $candidats = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.type = :type')
            ->setParameter('type', 'CANDIDATE')
            ->getQuery()
            ->getResult();

        return $this->render('BackOffice/dashboard/messages/index.html.twig', [
            'conversations' => $conversations,
            'candidats' => $candidats,
        ]);
    }

    #[Route('/conversations', name: 'app_recruteur_get_conversations', methods: ['GET'])]
    public function getConversations(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

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
            $otherUser = $conv->getUser1()->getId() === $user->getId() ? $conv->getUser2() : $conv->getUser1();
            $data[] = [
                'id' => $conv->getId(),
                'other_user_id' => $otherUser->getId(),
                'other_user_name' => $otherUser->getFirstName() . ' ' . $otherUser->getLastName(),
                'last_message' => $conv->getDernierMessage() ?? 'Aucun message',
                'date' => $conv->getDateCreation()->format('d/m'),
                'unread_count' => 0,
            ];
        }

        return $this->json($data);
    }

    #[Route('/conversation/{id}/messages', name: 'app_recruteur_get_messages', methods: ['GET'])]
    public function getMessages(int $id, MessageRepository $messageRepo): JsonResponse
    {
        try {
            $user = $this->getUser();

            // Récupérer les messages via le repository
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
                    'est_moi' => $message->getExpediteur()->getId() === $user->getId(),
                    'statut' => $message->getStatut(),
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/send-message', name: 'app_recruteur_send_message', methods: ['POST'])]
    public function sendMessage(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $conversationId = $request->request->get('conversation_id');
        $content = $request->request->get('content');

        // Validation du message
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

        $conversation = $em->getRepository(Conversation::class)->find($conversationId);

        if (!$conversation) {
            return $this->json(['success' => false, 'error' => 'Conversation introuvable']);
        }

        $destinataire = $conversation->getUser1()->getId() === $user->getId() ?
            $conversation->getUser2() : $conversation->getUser1();

        $message = new Message();
        $message->setContenu($content);
        $message->setDateEnvoi(new \DateTime());
        $message->setStatut('sent');
        $message->setType('text');
        $message->setConversation($conversation);
        $message->setExpediteur($user);
        $message->setDestinataire($destinataire);

        $em->persist($message);
        $conversation->setDernierMessage($content);
        $conversation->setDateCreation(new \DateTime());

        $em->flush();

        return $this->json([
            'success' => true,
            'message_id' => $message->getId(),
        ]);
    }

    #[Route('/new-conversation', name: 'app_recruteur_new_conversation', methods: ['POST'])]
    public function newConversation(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $candidatId = $request->request->get('candidat_id');
        $content = $request->request->get('content');

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

        // Vérifier si une conversation existe déjà
        $existingConversation = $em->createQueryBuilder()
            ->select('c')
            ->from(Conversation::class, 'c')
            ->where('(c.user1 = :user AND c.user2 = :candidat) OR (c.user1 = :candidat AND c.user2 = :user)')
            ->setParameter('user', $user)
            ->setParameter('candidat', $candidat)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingConversation) {
            // Ajouter un message à la conversation existante
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

        // Créer une nouvelle conversation
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
    }

    #[Route('/edit-message/{id}', name: 'app_recruteur_edit_message', methods: ['POST'])]
    public function editMessage(Message $message, Request $request, EntityManagerInterface $em): JsonResponse
    {
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
        $conversation->setDernierMessage($newContent);

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/delete-message/{id}', name: 'app_recruteur_delete_message', methods: ['DELETE'])]
    public function deleteMessage(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if ($message->getExpediteur()->getId() !== $user->getId()) {
            return $this->json(['success' => false, 'error' => 'Vous ne pouvez pas supprimer ce message']);
        }

        $conversation = $message->getConversation();
        $em->remove($message);

        $lastMessage = $em->getRepository(Message::class)
            ->createQueryBuilder('m')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.dateEnvoi', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $conversation->setDernierMessage($lastMessage ? $lastMessage->getContenu() : null);

        $em->flush();

        return $this->json(['success' => true]);
    }
}