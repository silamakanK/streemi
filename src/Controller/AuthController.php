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
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    #[Route('/forgot', name: 'forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        $sent = false;

        if ($request->isMethod('POST')) {
            $emailAddress = trim($request->request->get('email', ''));
            $user = $userRepository->findOneBy(['email' => $emailAddress]);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $user->setResetToken($token);
                $em->flush();

                $resetUrl = $request->getSchemeAndHttpHost() . '/reset?token=' . $token;
                $resetEmail = (new Email())
                    ->from('contact@streemi.com')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe — Streemi')
                    ->html("
                        <p>Bonjour <strong>{$user->getUsername()}</strong>,</p>
                        <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
                        <p><a href=\"{$resetUrl}\">{$resetUrl}</a></p>
                        <p>Ce lien est valable 1 heure. Si vous n'avez pas fait cette demande, ignorez cet email.</p>
                    ");
                $mailer->send($resetEmail);
            }

            $sent = true;
        }

        return $this->render('auth/forgot.html.twig', ['sent' => $sent]);
    }

    #[Route('/reset', name: 'reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $token = $request->query->get('token') ?? $request->request->get('token', '');
        $user  = $token ? $userRepository->findOneBy(['resetToken' => $token]) : null;

        if (!$user) {
            return $this->render('auth/reset.html.twig', ['invalid' => true, 'token' => '']);
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $confirm  = $request->request->get('password_confirm', '');

            if ($password !== $confirm || strlen($password) < 6) {
                return $this->render('auth/reset.html.twig', [
                    'invalid' => false,
                    'token'   => $token,
                    'error'   => $password !== $confirm
                        ? 'Les mots de passe ne correspondent pas.'
                        : 'Le mot de passe doit contenir au moins 6 caractères.',
                ]);
            }

            $user->setPassword($hasher->hashPassword($user, $password));
            $user->setResetToken(null);
            $em->flush();

            $this->addFlash('success', 'Mot de passe réinitialisé. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/reset.html.twig', ['invalid' => false, 'token' => $token]);
    }

    #[Route('/confirm', name: 'confirm_account')]
    public function confirm(): Response
    {
        return $this->render('auth/confirm.html.twig');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        return $this->render('profile.html.twig');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/profile/update', name: 'profile_update', methods: ['POST'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('profile_update', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('profile');
        }

        $username = trim($request->request->get('username', ''));
        $email    = trim($request->request->get('email', ''));

        if (!$username || !$email) {
            $this->addFlash('error', 'Le nom d\'utilisateur et l\'email sont obligatoires.');
            return $this->redirectToRoute('profile');
        }

        $existing = $userRepository->findOneBy(['email' => $email]);
        if ($existing && $existing->getId() !== $user->getId()) {
            $this->addFlash('error', 'Cette adresse email est déjà utilisée.');
            return $this->redirectToRoute('profile');
        }

        $user->setUsername($username);
        $user->setEmail($email);
        $em->flush();

        $this->addFlash('success', 'Profil mis à jour avec succès.');
        return $this->redirectToRoute('profile');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/profile/password', name: 'profile_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('change_password', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('profile');
        }

        $current = $request->request->get('current_password', '');
        $new     = $request->request->get('new_password', '');
        $confirm = $request->request->get('new_password_confirm', '');

        if (!$hasher->isPasswordValid($user, $current)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect.');
            return $this->redirectToRoute('profile');
        }

        if ($new !== $confirm) {
            $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
            return $this->redirectToRoute('profile');
        }

        if (strlen($new) < 6) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            return $this->redirectToRoute('profile');
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $em->flush();

        $this->addFlash('success', 'Mot de passe changé avec succès.');
        return $this->redirectToRoute('profile');
    }
}
