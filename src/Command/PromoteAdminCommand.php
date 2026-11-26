<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Donne le rôle administrateur à un compte existant (utile en production, où il n'y a pas de fixtures). */
#[AsCommand(name: 'app:user:promote', description: 'Nomme administrateur le compte correspondant à une adresse email')]
class PromoteAdminCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse email du compte (créé via le site ou la connexion Google)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = $this->userRepository->findOneBy(['email' => $input->getArgument('email')]);

        if (!$user) {
            $io->error('Aucun compte avec cette adresse. Inscrivez-vous d\'abord sur le site.');
            return Command::FAILURE;
        }

        $user->addRole('ROLE_ADMIN');
        $this->em->flush();
        $io->success("{$user->getUsername()} est maintenant administrateur.");

        return Command::SUCCESS;
    }
}
