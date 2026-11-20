<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Movie;
use App\Entity\Serie;
use App\Enum\CatalogTypeEnum;
use App\Repository\CategoryRepository;
use App\Repository\LanguageRepository;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/movies')]
class MediaController extends AbstractController
{
    private const PER_PAGE = 10;

    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $categoryRepository,
        private LanguageRepository $languageRepository,
    ) {}

    #[Route('', name: 'admin_movies')]
    public function list(Request $request, MediaRepository $repository): Response
    {
        $query = trim($request->query->getString('q'));
        $type = CatalogTypeEnum::tryFrom($request->query->getString('type'));
        $page = max(1, $request->query->getInt('page', 1));

        $medias = $repository->findForAdmin($query, $type, $page, self::PER_PAGE);

        return $this->render('admin/admin_films.html.twig', [
            'medias' => $medias,
            'query'  => $query,
            'type'   => $type,
            'page'   => $page,
            'pages'  => max(1, (int) ceil(count($medias) / self::PER_PAGE)),
        ]);
    }

    #[Route('/add', name: 'admin_movies_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $media = $request->request->get('type') === 'serie' ? new Serie() : new Movie();

        if ($request->isMethod('POST')) {
            $errors = $this->handleForm($request, $media);
            if (!$errors) {
                $this->em->persist($media);
                $this->em->flush();

                if ($media instanceof Serie) {
                    $this->addFlash('success', "Série « {$media->getTitle()} » créée. Ajoutez maintenant ses saisons et épisodes.");
                    return $this->redirectToRoute('admin_serie_seasons', ['id' => $media->getId()]);
                }

                $this->addFlash('success', "Film « {$media->getTitle()} » créé.");
                return $this->redirectToRoute('admin_movies');
            }
        }

        return $this->renderForm($media, $errors ?? [], true);
    }

    #[Route('/{id}/edit', name: 'admin_movies_edit', methods: ['GET', 'POST'])]
    public function edit(Media $media, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $errors = $this->handleForm($request, $media);
            if (!$errors) {
                $this->em->flush();
                $this->addFlash('success', "« {$media->getTitle()} » a été modifié.");
                return $this->redirectToRoute('admin_movies');
            }
        }

        return $this->renderForm($media, $errors ?? [], false);
    }

    #[Route('/{id}/delete', name: 'admin_movies_delete', methods: ['POST'])]
    public function delete(Media $media, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_media_' . $media->getId(), $request->request->get('_token'))) {
            $this->em->remove($media);
            $this->em->flush();
            $this->addFlash('success', "« {$media->getTitle()} » a été supprimé.");
        }

        return $this->redirectToRoute('admin_movies');
    }

    private function renderForm(Media $media, array $errors, bool $isNew): Response
    {
        return $this->render('admin/media_form.html.twig', [
            'media'      => $media,
            'isNew'      => $isNew,
            'errors'     => $errors,
            'categories' => $this->categoryRepository->findBy([], ['nom' => 'ASC']),
            'languages'  => $this->languageRepository->findBy([], ['nom' => 'ASC']),
        ], new Response(status: $errors ? 422 : 200));
    }

    /**
     * Remplit le média avec les données du formulaire.
     * Rien n'est enregistré ici : en cas d'erreur, le contrôleur réaffiche le formulaire sans flush.
     *
     * @return array<string, string> erreurs par champ
     */
    private function handleForm(Request $request, Media $media): array
    {
        $errors = [];
        if (!$this->isCsrfTokenValid('admin_media_form', $request->request->get('_csrf_token'))) {
            return ['form' => 'Le formulaire a expiré, veuillez réessayer.'];
        }

        $data = $request->request;

        $title = trim($data->getString('title'));
        $media->setTitle($title);
        if ($title === '') {
            $errors['title'] = 'Le titre est obligatoire.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Le titre ne doit pas dépasser 255 caractères.';
        }

        $media->setShortDescription(trim($data->getString('short_description')));
        if ($media->getShortDescription() === '') {
            $errors['short_description'] = 'La description courte est obligatoire.';
        }

        $media->setLongDescription(trim($data->getString('long_description')));
        if ($media->getLongDescription() === '') {
            $errors['long_description'] = 'La description longue est obligatoire.';
        }

        $cover = trim($data->getString('cover'));
        $media->setCoverImage($cover);
        if (!$this->isHttpUrl($cover)) {
            $errors['cover'] = 'Indiquez l\'URL complète d\'une image (https://…).';
        }

        $releaseDate = \DateTime::createFromFormat('!Y-m-d', $data->getString('release_date'));
        if ($releaseDate) {
            $media->setReleaseDate($releaseDate);
        } else {
            $errors['release_date'] = 'La date de sortie est obligatoire.';
        }

        foreach ($media->getCategories()->toArray() as $category) {
            $media->removeCategory($category);
        }
        foreach ($this->categoryRepository->findBy(['id' => $data->all('categories')]) as $category) {
            $media->addCategory($category);
        }
        if ($media->getCategories()->isEmpty()) {
            $errors['categories'] = 'Choisissez au moins une catégorie.';
        }

        foreach ($media->getLanguages()->toArray() as $language) {
            $media->removeLanguage($language);
        }
        foreach ($this->languageRepository->findBy(['id' => $data->all('languages')]) as $language) {
            $media->addLanguage($language);
        }

        $media->setStaff($this->readPeople($data->all('staff'), 'staff', $errors));
        $media->setCasting($this->readPeople($data->all('casting'), 'casting', $errors));

        return $errors;
    }

    /**
     * Lignes « nom / rôle / photo » du staff ou du casting. Les lignes sans nom sont ignorées.
     *
     * @return array<int, array{name: string, role: string, image: string}>
     */
    private function readPeople(array $rows, string $field, array &$errors): array
    {
        $people = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $image = trim((string) ($row['image'] ?? ''));
            if ($image !== '' && !$this->isHttpUrl($image)) {
                $errors[$field] = sprintf('La photo de « %s » doit être une URL complète (https://…).', $name);
            }
            $people[] = ['name' => $name, 'role' => trim((string) ($row['role'] ?? '')), 'image' => $image];
        }

        return $people;
    }

    private function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1;
    }
}
