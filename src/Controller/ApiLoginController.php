<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST','GET'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        // Expect "username" and "password" keys to match Postman body
        if (!$data || !isset($data['username'], $data['password'])) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // lookup by username since security provider is configured that way
        $user = $userRepository->findOneBy(['username' => $data['username']]);

        // avoid giving away whether the username exists
        if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        // Check if user's email is verified
        if (!$user->isVerified()) {
            return new JsonResponse([
                'error' => 'Email not verified',
                'message' => 'Please verify your email address before logging in. Check your inbox for the verification link.'
            ], 403);
        }

        $token = $jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail()
            ]
        ]);
    }
}