<?php


declare(strict_types=1);
namespace App\Controller\FrontOffice;

use App\Entity\Feedback;
use App\Entity\RenduMission;
use App\Form\FeedbackType;
use App\Repository\FeedbackRepository;
use App\Repository\RenduMissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recruteur/feedback')]
#[IsGranted('ROLE_RECRUTEUR')]
class FeedbackController extends AbstractController
{
    use UserTypeCasterTrait;
    #[Route('/', name: 'app_recruteur_feedback_index', methods: ['GET'])]
    public function index(RenduMissionRepository $renduRepository): Response
    {
        $user = $this->getAuthenticatedUser();

        // Récupérer les rendus de mission (peut-être filtrés par recruteur)
        $rendus = $renduRepository->findAll();

        return $this->render('FrontOffice/main/feedback/index.html.twig', [
            'rendus' => $rendus,
        ]);
    }

    #[Route('/new/{renduId}', name: 'app_recruteur_feedback_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, int $renduId, RenduMissionRepository $renduRepository): Response
    {
        $user = $this->getAuthenticatedUser();

        // Récupérer le rendu de mission sélectionné
        $rendu = $renduRepository->find($renduId);

        if (!$rendu) {
            throw $this->createNotFoundException('Rendu de mission non trouvé');
        }

        // Vérifier si un feedback existe déjà pour ce rendu
        $existingFeedback = $em->getRepository(Feedback::class)->findOneBy(['renduMission' => $rendu]);

        if ($existingFeedback) {
            $this->addFlash('danger', 'Un feedback a déjà été donné pour cette mission');
            return $this->redirectToRoute('app_recruteur_feedback_index');
        }

        $feedback = new Feedback();
        $form = $this->createForm(FeedbackType::class, $feedback);
        if (!$user instanceof \App\Entity\User || $user->getId() === null) {
            throw $this->createAccessDeniedException();
        }

        // Valeurs par défaut
        $feedback->setCreatedAt(new \DateTimeImmutable());
        $feedback->setUser($user);
        $feedback->setUtilisateurId($user->getId());
        $feedback->setRenduMission($rendu);
        $feedback->setRenduId($rendu->getId());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($feedback);
            $em->flush();

            $this->addFlash('success', 'Votre feedback a été envoyé pour le rendu #' . $rendu->getId());
            return $this->redirectToRoute('app_recruteur_feedback_index');
        }

        return $this->render('FrontOffice/main/feedback/new.html.twig', [
            'form' => $form->createView(),
            'rendu' => $rendu,
        ]);
    }

    #[Route('/mes-feedbacks', name: 'app_recruteur_feedback_list', methods: ['GET'])]
    public function myFeedbacks(FeedbackRepository $repository): Response
    {
        $user = $this->getAuthenticatedUser();

        $feedbacks = $repository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('FrontOffice/main/feedback/my_feedbacks.html.twig', [
            'feedbacks' => $feedbacks,
        ]);
    }

    #[Route('/{id}', name: 'app_recruteur_feedback_show', methods: ['GET'])]
    public function show(Feedback $feedback): Response
    {
        $user = $this->getAuthenticatedUser();

        if ($feedback->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }

        return $this->render('FrontOffice/main/feedback/show.html.twig', [
            'feedback' => $feedback,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recruteur_feedback_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Feedback $feedback, EntityManagerInterface $em): Response
    {
        $user = $this->getAuthenticatedUser();

        if ($feedback->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce feedback');
        }

        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre feedback a été modifié');
            return $this->redirectToRoute('app_recruteur_feedback_list');
        }

        return $this->render('FrontOffice/main/feedback/edit.html.twig', [
            'form' => $form->createView(),
            'feedback' => $feedback,
        ]);
    }

    #[Route('/{id}', name: 'app_recruteur_feedback_delete', methods: ['POST'])]
    public function delete(Request $request, Feedback $feedback, EntityManagerInterface $em): Response
    {
        $user = $this->getAuthenticatedUser();

        if ($feedback->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce feedback');
        }

        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $feedback->getId(), is_string($token) ? $token : null)) {
            $em->remove($feedback);
            $em->flush();
            $this->addFlash('success', 'Votre feedback a été supprimé');
        }

        return $this->redirectToRoute('app_recruteur_feedback_list');
    }
}
