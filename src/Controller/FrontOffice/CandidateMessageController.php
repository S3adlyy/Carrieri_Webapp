<?php

namespace App\Controller\FrontOffice;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/messagerie')]
class CandidateMessageController extends AbstractController
{
    #[Route('/', name: 'app_candidate_messages')]
    public function index(EntityManagerInterface $em): Response
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

        $recruiters = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.type = :type')
            ->setParameter('type', 'RECRUITER')
            ->getQuery()
            ->getResult();

        return $this->render('FrontOffice/main/messagerie.html.twig', [
            'conversations' => $conversations,
            'recruiters' => $recruiters,
        ]);
    }

    #[Route('/conversation/{id}', name: 'app_candidate_conversation_show')]
    public function showConversation(Conversation $conversation): Response
    {
        $user = $this->getUser();

        if ($conversation->getUser1() !== $user && $conversation->getUser2() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette conversation');
        }

        $otherUser = $conversation->getUser1()->getId() === $user->getId()
            ? $conversation->getUser2()
            : $conversation->getUser1();

        return $this->render('FrontOffice/main/conversation.html.twig', [
            'conversation' => $conversation,
            'otherUser' => $otherUser,
        ]);
    }

    #[Route('/conversations', name: 'app_candidate_get_conversations', methods: ['GET'])]
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
            ];
        }

        return $this->json($data);
    }

    #[Route('/conversation/{id}/messages', name: 'app_candidate_get_messages', methods: ['GET'])]
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

    #[Route('/send-message', name: 'app_candidate_send_message', methods: ['POST'])]
    public function sendMessage(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $conversationId = $request->request->get('conversation_id');
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

    #[Route('/new-conversation', name: 'app_candidate_new_conversation', methods: ['POST'])]
    public function newConversation(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $recruiterId = $request->request->get('recruiter_id');
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

        $recruiter = $em->getRepository(User::class)->find($recruiterId);

        if (!$recruiter) {
            return $this->json(['success' => false, 'error' => 'Destinataire introuvable']);
        }

        $existingConversation = $em->createQueryBuilder()
            ->select('c')
            ->from(Conversation::class, 'c')
            ->where('(c.user1 = :user AND c.user2 = :recruiter) OR (c.user1 = :recruiter AND c.user2 = :user)')
            ->setParameter('user', $user)
            ->setParameter('recruiter', $recruiter)
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
            $message->setDestinataire($recruiter);

            $em->persist($message);
            $existingConversation->setDernierMessage($content);
            $existingConversation->setDateCreation(new \DateTime());

            $em->flush();

            return $this->json(['success' => true, 'conversation_id' => $existingConversation->getId()]);
        }

        $conversation = new Conversation();
        $conversation->setUser1($user);
        $conversation->setUser2($recruiter);
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
        $message->setDestinataire($recruiter);

        $em->persist($conversation);
        $em->persist($message);
        $em->flush();

        return $this->json(['success' => true, 'conversation_id' => $conversation->getId()]);
    }

    #[Route('/conversation/{id}/archive', name: 'app_candidate_archive_conversation', methods: ['POST'])]
    public function archiveConversation(Conversation $conversation, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if ($conversation->getUser1() !== $user && $conversation->getUser2() !== $user) {
            return $this->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        $conversation->setStatut('archived');
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/edit-message/{id}', name: 'app_candidate_edit_message', methods: ['POST'])]
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

    #[Route('/delete-message/{id}', name: 'app_candidate_delete_message', methods: ['DELETE'])]
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