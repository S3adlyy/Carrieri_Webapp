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

        if (!$user) {
            return $this->redirectToRoute('app_login');
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

        // Get all RECRUITER type users
        $recruiters = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles = :role')
            ->setParameter('role', 'RECRUITER')
            ->orderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        echo"test";


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
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/conversation/{id}/messages', name: 'app_candidate_get_messages', methods: ['GET'])]
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
                $expediteur = $message->getExpediteur();
                if (!$expediteur) {
                    continue;
                }

                $estMoi = ($expediteur->getId() === $user->getId());

                $data[] = [
                    'id' => $message->getId(),
                    'contenu' => $message->getContenu(),
                    'date_envoi' => $message->getDateEnvoi()->format('H:i'),
                    'est_moi' => $estMoi,
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
        try {
            $user = $this->getUser();
            $conversationId = $request->request->get('conversation_id');
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

            $conversation = $em->getRepository(Conversation::class)->find($conversationId);

            if (!$conversation) {
                return $this->json(['success' => false, 'error' => 'Conversation introuvable']);
            }

            $destinataire = $conversation->getUser1()->getId() === $user->getId() ?
                $conversation->getUser2() : $conversation->getUser1();

            if (!$destinataire) {
                return $this->json(['success' => false, 'error' => 'Destinataire introuvable']);
            }

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
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/new-conversation', name: 'app_candidate_new_conversation', methods: ['POST'])]
    public function newConversation(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();
            $recruiterId = $request->request->get('recruiter_id');
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
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/conversation/{id}/archive', name: 'app_candidate_archive_conversation', methods: ['POST'])]
    public function archiveConversation(Conversation $conversation, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();

            if ($conversation->getUser1() !== $user && $conversation->getUser2() !== $user) {
                return $this->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
            }

            $conversation->setStatut('archived');
            $em->flush();

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/edit-message/{id}', name: 'app_candidate_edit_message', methods: ['POST'])]
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

    #[Route('/delete-message/{id}', name: 'app_candidate_delete_message', methods: ['DELETE'])]
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