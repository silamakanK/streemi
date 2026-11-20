<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Enum\CatalogTypeEnum;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/categories')]
class CategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $repository,
    ) {}

    #[Route('', name: 'admin_categories', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('admin/admin_categories.html.twig', [
            'categories' => $this->repository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/add', name: 'admin_category_add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $category = new Category();
        if ($this->fill($category, $request)) {
            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', "Catégorie « {$category->getNom()} » créée.");
        }

        return $this->redirectToRoute('admin_categories');
    }

    #[Route('/{id}/edit', name: 'admin_category_edit', methods: ['POST'])]
    public function edit(Category $category, Request $request): Response
    {
        if ($this->fill($category, $request)) {
            $this->em->flush();
            $this->addFlash('success', "Catégorie « {$category->getNom()} » modifiée.");
        }

        return $this->redirectToRoute('admin_categories');
    }

    #[Route('/{id}/delete', name: 'admin_category_delete', methods: ['POST'])]
    public function delete(Category $category, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_categories', $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expiré, veuillez réessayer.');
        } elseif (self::isProtected($category)) {
            $this->addFlash('error', "La catégorie « {$category->getNom()} » est utilisée par l'onglet Animés et ne peut pas être supprimée.");
        } else {
            // Les liaisons avec les médias sont supprimées en cascade par la base, les médias restent
            $this->em->remove($category);
            $this->em->flush();
            $this->addFlash('success', "Catégorie « {$category->getNom()} » supprimée.");
        }

        return $this->redirectToRoute('admin_categories');
    }

    public static function isProtected(Category $category): bool
    {
        return $category->getNom() === CatalogTypeEnum::ANIMATION_CATEGORY;
    }

    /** Valide et applique nom et label. Affiche un message et renvoie false si invalide. */
    private function fill(Category $category, Request $request): bool
    {
        if (!$this->isCsrfTokenValid('admin_categories', $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expiré, veuillez réessayer.');
            return false;
        }

        $nom = trim($request->request->getString('nom'));
        // Le label est le texte affiché sur la page Découvrir : par défaut, le même que le nom
        $label = trim($request->request->getString('label')) ?: $nom;

        if ($nom === '' || mb_strlen($nom) > 255 || mb_strlen($label) > 255) {
            $this->addFlash('error', 'Le nom est obligatoire (255 caractères maximum).');
            return false;
        }

        if ($category->getId() && self::isProtected($category) && $nom !== $category->getNom()) {
            $this->addFlash('error', "Le nom « {$category->getNom()} » est utilisé par l'onglet Animés : seul son label peut être modifié.");
            return false;
        }

        foreach ($this->repository->findAll() as $other) {
            if ($other !== $category && mb_strtolower($other->getNom()) === mb_strtolower($nom)) {
                $this->addFlash('error', "Une catégorie « {$other->getNom()} » existe déjà.");
                return false;
            }
        }

        $category->setNom($nom);
        $category->setLabel($label);

        return true;
    }
}
