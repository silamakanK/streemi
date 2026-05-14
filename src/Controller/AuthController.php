<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserStatusEnum;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        if ($request->isMethod('POST')) {
            $token = new CsrfToken('register', $request->request->get('_csrf_token'));
            if (!$csrfTokenManager->isTokenValid($token)) {
                return $this->render('auth/register.html.twig', ['error' => 'Token invalide, veuillez réessayer.']);
            }

            $username = trim($request->request->get('username', ''));
            $email = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');

            if (!$username || !$email || !$password) {
                return $this->render('auth/register.html.twig', [
                    'error' => 'Tous les champs sont obligatoires.',
                    'username' => $username,
                    'email' => $email,
                ]);
            }

            if ($password !== $passwordConfirm) {
                return $this->render('auth/register.html.twig', [
                    'error' => 'Les mots de passe ne correspondent pas.',
                    'username' => $username,
                    'email' => $email,
                ]);
            }

            if ($userRepository->findOneBy(['email' => $email])) {
                return $this->render('auth/register.html.twig', [
                    'error' => 'Cette adresse email est déjà utilisée.',
                    'username' => $username,
                    'email' => $email,
                ]);
            }

            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setPassword($hasher->hashPassword($user, $password));
            $user->setAccountStatus(UserStatusEnum::ACTIVE);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/register.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Symfony intercepte cette route automatiquement
    }

    #[Route('/forgot', name: 'forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        MailerInterface $mailer
    ): Response {
        $email = $request->get('email');

        if ($email) {
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $user->setResetToken($token);

                $resetEmail = (new Email())
                    ->from('contact@streemi.com')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->html('<a href="http://localhost:8000/reset?token=' . $token . '">Cliquez ici pour réinitialiser votre mot de passe</a>');

                $mailer->send($resetEmail);
            }
        }

        return $this->render('auth/forgot.html.twig');
    }

    #[Route('/confirm', name: 'confirm_account')]
    public function confirm(): Response
    {
        return $this->render('auth/confirm.html.twig');
    }

    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        return $this->render('auth/index.html.twig');
    }
}
