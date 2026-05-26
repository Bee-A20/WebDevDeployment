<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class GoogleStaffAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);

        $email = $googleUser->getEmail();
        if (!$email) {
            throw new CustomUserMessageAuthenticationException('Google did not return an email address.');
        }

        if (!$googleUser->getEmailVerified()) {
            throw new CustomUserMessageAuthenticationException('Verify your email with Google before signing in.');
        }

        $userIdentifier = mb_strtolower($email);

        return new SelfValidatingPassport(
            new UserBadge($userIdentifier, function () use ($googleUser, $email) {
                $user = $this->userRepository->findOneByGoogleId((string) $googleUser->getId());
                if (!$user) {
                    $user = $this->userRepository->findOneByEmailIgnoreCase($email);
                }

                if (!$user instanceof User) {
                    // Create new user with ROLE_STAFF if email doesn't exist
                    $user = new User();
                    $user->setEmail($email);
                    $user->setUsername($email); // Use email as username for Google users
                    $user->setRoles(['ROLE_STAFF']);
                    $passwordPlaceholder = 'oauth_user_' . time() . '_' . random_int(1000, 9999);
                    $user->setPassword($this->passwordHasher->hashPassword($user, $passwordPlaceholder));
                    $user->setIsActive(true);
                    $user->setIsVerified(true);
                    $this->entityManager->persist($user);
                } else {
                    // Existing user - check if they have staff role
                    $roles = $user->getRoles();
                    if (!\in_array('ROLE_STAFF', $roles, true)) {
                        throw new CustomUserMessageAuthenticationException('Google sign-in is only available for staff accounts.');
                    }
                }

                $user->setGoogleId((string) $googleUser->getId());
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $roles = method_exists($token, 'getRoleNames') ? $token->getRoleNames() : $token->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_STAFF', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_shop'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('danger', $exception->getMessage());

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
