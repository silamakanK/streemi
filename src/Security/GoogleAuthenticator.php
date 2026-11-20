<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserStatusEnum;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class GoogleAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);
                $email = $googleUser->getEmail();

                // Google peut renvoyer une adresse non vérifiée : on refuse pour éviter de se faire passer pour quelqu'un
                if (!$email || !($googleUser->toArray()['email_verified'] ?? false)) {
                    throw new CustomUserMessageAuthenticationException('Votre adresse Google n\'est pas vérifiée.');
                }

                $user = $this->userRepository->findOneBy(['googleId' => $googleUser->getId()]);
                if ($user) {
                    return $user;
                }

                // Compte existant avec la même adresse : on le relie à Google
                $user = $this->userRepository->findOneBy(['email' => $email]);
                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setUsername($googleUser->getName() ?: strstr($email, '@', true));
                    // Mot de passe aléatoire : le compte se connecte via Google ou « mot de passe oublié »
                    $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));
                    $user->setAccountStatus(UserStatusEnum::ACTIVE);
                    $this->em->persist($user);
                }

                $user->setGoogleId($googleUser->getId());
                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse($targetPath ?: $this->urlGenerator->generate('homepage'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Affiche l'erreur sur la page de connexion, comme pour le formulaire classique
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
