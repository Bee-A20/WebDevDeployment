<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify-email/{token}', name: 'app_verify_email')]
    public function verifyEmail(
        string $token,
        EmailVerificationService $emailVerificationService
    ): Response {
        $user = $emailVerificationService->verifyToken($token);

        if (!$user) {
            $this->addFlash('error', 'Invalid or expired verification token.');
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Email verified successfully! You can now log in.');
        return $this->redirectToRoute('app_login');
    }
}