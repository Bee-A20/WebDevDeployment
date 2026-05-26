<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ProductsRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator, private ProductsRepository $productsRepository)
    {
    }

    public function authenticate(Request $request): Passport
    {
        // Symfony issue: older auto-generated authenticators expect JSON payloads
        // but traditional form submissions use $request->request.  When the wrong
        // bag is accessed the username/password are empty, leading to "Bad
        // credentials" even if the form was filled correctly.
        // determine whether the request body is JSON; older Symfony
        // versions don't provide isJson(), so we inspect the content-type
        $contentType = $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'json')) {
            $payload = $request->getPayload();
            $username = $payload->getString('username');
            $password = $payload->getString('password');
            $csrf     = $payload->getString('_csrf_token');
        } else {
            $username = $request->request->get('username', '');
            $password = $request->request->get('password', '');
            $csrf     = $request->request->get('_csrf_token');
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrf),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        $pending = $session->get('shop_cart_pending');

        if (is_array($pending) && isset($pending['product_id'])) {
            $product = $this->productsRepository->find((int) $pending['product_id']);
            if ($product) {
                $quantity = max(1, (int) ($pending['quantity'] ?? 1));
                $cart = $session->get('shop_cart', []);
                $cart[$product->getId()] = ($cart[$product->getId()] ?? 0) + $quantity;

                $stock = $product->getStock();
                if ($stock !== null && $cart[$product->getId()] > $stock) {
                    $cart[$product->getId()] = $stock;
                    $session->getFlashBag()->add('info', 'Quantity limited by available stock.');
                }

                $session->set('shop_cart', $cart);
                $session->getFlashBag()->add('success', sprintf('Added "%s" to your cart.', $product->getName()));
            }

            $session->remove('shop_cart_pending');
        }

        if ($targetPath = $this->getTargetPath($session, $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $roles = method_exists($token, 'getRoleNames') ? $token->getRoleNames() : $token->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_STAFF', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_shop'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
