<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('', name: 'app_activity_logs_index')]
    public function index(ActivityLogRepository $repo): Response
    {
        $logs = $repo->findBy([], ['createdAt' => 'DESC']);

        $response = $this->render('activity_logs/index.html.twig', [
            'logs' => $logs,
        ]);

        // Prevent caching to avoid back button access after logout
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
