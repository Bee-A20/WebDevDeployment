<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('google')->redirect(
            [],
            ['prompt' => 'select_account']
        );
    }

    /**
     * Google redirects here; {@see \App\Security\GoogleStaffAuthenticator} completes login.
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheckAction(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
