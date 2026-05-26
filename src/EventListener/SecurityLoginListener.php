<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SecurityLoginListener implements EventSubscriberInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onSecurityInteractiveLogin',
        ];
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $log = new ActivityLog();
        $log->setUserId(method_exists($user, 'getId') ? $user->getId() : null);
        $log->setUsername(method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null);
        $roles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
        $log->setRole(is_array($roles) && count($roles) ? $roles[0] : null);
        $log->setAction('LOGIN');
        $log->setTarget('User login');

        $this->em->persist($log);
        $this->em->flush();
    }
}
