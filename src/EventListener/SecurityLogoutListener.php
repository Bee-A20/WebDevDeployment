<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SecurityLogoutListener implements EventSubscriberInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        $log = new ActivityLog();
        $log->setUserId(method_exists($user, 'getId') ? $user->getId() : null);
        $log->setUsername(method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null);
        $roles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
        $log->setRole(is_array($roles) && count($roles) ? $roles[0] : null);
        $log->setAction('LOGOUT');
        $log->setTarget('User logout');

        $this->em->persist($log);
        $this->em->flush();
    }
}
