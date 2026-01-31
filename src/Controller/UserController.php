<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/v1/api/users')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
        private readonly TranslatorInterface $translator
    ) {
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return $this->unauthorizedResponse();
        }

        if (!$this->security->isGranted('ROLE_ROOT') && $currentUser->getId() !== $id) {
            return $this->errorResponse($this->translator->trans('error.access_denied'), Response::HTTP_FORBIDDEN);
        }

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->errorResponse($this->translator->trans('error.user_not_found'), Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializeUserRead($user));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getJsonPayload($request);
        if ($data === null) {
            return $this->errorResponse($this->translator->trans('error.invalid_json_body'), Response::HTTP_BAD_REQUEST);
        }

        $login = $data['login'] ?? null;
        $phone = $data['phone'] ?? null;
        $pass = $data['pass'] ?? null;

        if ($login === null || $phone === null || $pass === null) {
            return $this->errorResponse(
                $this->translator->trans('error.required_fields', ['%fields%' => 'login, phone, pass']),
                Response::HTTP_BAD_REQUEST
            );
        }

        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return $this->unauthorizedResponse();
        }

        if (!$this->security->isGranted('ROLE_ROOT')) {
            if ($login !== $currentUser->getLogin()) {
                return $this->errorResponse($this->translator->trans('error.access_denied'), Response::HTTP_FORBIDDEN);
            }

            $currentUser
                ->setPhone((string) $phone)
                ->setPass((string) $pass);

        $errors = $this->validator->validate($currentUser);
        if (count($errors) > 0) {
            if ($this->hasUniqueViolation($errors)) {
                return $this->errorResponse($this->translator->trans('error.user_exists'), Response::HTTP_CONFLICT);
            }

            return $this->errorResponse((string) $errors, Response::HTTP_BAD_REQUEST);
        }

            $this->entityManager->flush();

            return new JsonResponse($this->serializeUserCreate($currentUser), Response::HTTP_CREATED);
        }

        $user = (new User())
            ->setLogin((string) $login)
            ->setPhone((string) $phone)
            ->setPass((string) $pass);

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            if ($this->hasUniqueViolation($errors)) {
                return $this->errorResponse($this->translator->trans('error.user_exists'), Response::HTTP_CONFLICT);
            }

            return $this->errorResponse((string) $errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeUserCreate($user), Response::HTTP_CREATED);
    }

    #[Route('', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return $this->unauthorizedResponse();
        }

        $data = $this->getJsonPayload($request);
        if ($data === null) {
            return $this->errorResponse($this->translator->trans('error.invalid_json_body'), Response::HTTP_BAD_REQUEST);
        }

        $id = $data['id'] ?? null;
        $login = $data['login'] ?? null;
        $phone = $data['phone'] ?? null;
        $pass = $data['pass'] ?? null;
        $isRoot = $this->security->isGranted('ROLE_ROOT');

        if ($id === null || $phone === null || $pass === null || ($isRoot && $login === null)) {
            $fields = $isRoot ? 'id, login, phone, pass' : 'id, phone, pass';
            return $this->errorResponse(
                $this->translator->trans('error.required_fields', ['%fields%' => $fields]),
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$isRoot && (int) $id !== $currentUser->getId()) {
            return $this->errorResponse($this->translator->trans('error.access_denied'), Response::HTTP_FORBIDDEN);
        }

        $user = $this->entityManager->getRepository(User::class)->find((int) $id);
        if (!$user) {
            return $this->errorResponse($this->translator->trans('error.user_not_found'), Response::HTTP_NOT_FOUND);
        }

        if (!$isRoot && $login !== null && $login !== $currentUser->getLogin()) {
            return $this->errorResponse($this->translator->trans('error.cannot_change_login'), Response::HTTP_FORBIDDEN);
        }

        if ($isRoot) {
            $user->setLogin((string) $login);
        }

        $user
            ->setPhone((string) $phone)
            ->setPass((string) $pass);

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            if ($this->hasUniqueViolation($errors)) {
                return $this->errorResponse($this->translator->trans('error.user_exists'), Response::HTTP_CONFLICT);
            }

            return $this->errorResponse((string) $errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $user->getId()]);
    }

    #[Route('', methods: ['DELETE'])]
    public function delete(Request $request): JsonResponse
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return $this->unauthorizedResponse();
        }

        if (!$this->security->isGranted('ROLE_ROOT')) {
            return $this->errorResponse($this->translator->trans('error.access_denied'), Response::HTTP_FORBIDDEN);
        }

        $data = $this->getJsonPayload($request);
        if ($data === null) {
            return $this->errorResponse($this->translator->trans('error.invalid_json_body'), Response::HTTP_BAD_REQUEST);
        }

        $id = $data['id'] ?? null;
        if ($id === null) {
            return $this->errorResponse(
                $this->translator->trans('error.required_fields', ['%fields%' => 'id']),
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->entityManager->getRepository(User::class)->find((int) $id);
        if (!$user) {
            return $this->errorResponse($this->translator->trans('error.user_not_found'), Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function getCurrentUser(): ?User
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            return null;
        }

        return $currentUser;
    }

    private function getJsonPayload(Request $request): ?array
    {
        $content = trim((string) $request->getContent());
        if ($content === '') {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    private function serializeUserRead(User $user): array
    {
        return [
            'login' => $user->getLogin(),
            'pass' => $user->getPass(),
            'phone' => $user->getPhone(),
        ];
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return $this->errorResponse($this->translator->trans('error.unauthorized'), Response::HTTP_UNAUTHORIZED);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(
            ['status' => $status, 'message' => $message],
            $status
        );
    }

    private function hasUniqueViolation(ConstraintViolationListInterface $errors): bool
    {
        foreach ($errors as $error) {
            if ($error->getCode() === UniqueEntity::NOT_UNIQUE_ERROR) {
                return true;
            }
        }

        return false;
    }

    private function serializeUserCreate(User $user): array
    {
        return [
            'id' => $user->getId(),
            'login' => $user->getLogin(),
            'pass' => $user->getPass(),
            'phone' => $user->getPhone(),
        ];
    }

    #[Route('/doc', methods: ['GET'], priority: 10)]
    public function doc(): JsonResponse
    {
        return new JsonResponse([
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Users API',
                'version' => '1.0',
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Bearer',
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'paths' => [
                '/v1/api/users/{id}' => [
                    'get' => [
                        'summary' => 'Get user by id',
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'required' => ['login', 'pass', 'phone'],
                                            'properties' => [
                                                'login' => ['type' => 'string'],
                                                'pass' => ['type' => 'string'],
                                                'phone' => ['type' => 'string'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '401' => ['description' => 'Unauthorized'],
                            '403' => ['description' => 'Access denied'],
                            '404' => ['description' => 'User not found'],
                        ],
                    ],
                ],
                '/v1/api/users' => [
                    'put' => [
                        'summary' => 'Update user',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['id', 'login', 'pass', 'phone'],
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                            'login' => ['type' => 'string'],
                                            'pass' => ['type' => 'string'],
                                            'phone' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'required' => ['id'],
                                            'properties' => [
                                                'id' => ['type' => 'integer'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '400' => ['description' => 'Validation error'],
                            '401' => ['description' => 'Unauthorized'],
                            '403' => ['description' => 'Access denied'],
                            '404' => ['description' => 'User not found'],
                        ],
                    ],
                    'delete' => [
                        'summary' => 'Delete user',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['id'],
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '204' => ['description' => 'No content'],
                            '400' => ['description' => 'Validation error'],
                            '401' => ['description' => 'Unauthorized'],
                            '403' => ['description' => 'Access denied'],
                            '404' => ['description' => 'User not found'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Create user',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['login', 'pass', 'phone'],
                                        'properties' => [
                                            'login' => ['type' => 'string'],
                                            'pass' => ['type' => 'string'],
                                            'phone' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Created',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'required' => ['id', 'login', 'pass', 'phone'],
                                            'properties' => [
                                                'id' => ['type' => 'integer'],
                                                'login' => ['type' => 'string'],
                                                'pass' => ['type' => 'string'],
                                                'phone' => ['type' => 'string'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '400' => ['description' => 'Validation error'],
                            '401' => ['description' => 'Unauthorized'],
                            '403' => ['description' => 'Access denied'],
                            '409' => ['description' => 'User already exists'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[Route('/doc/ui', methods: ['GET'], priority: 10)]
    public function docUi(): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
window.ui = SwaggerUIBundle({
  url: '/v1/api/users/doc',
  dom_id: '#swagger-ui',
});
</script>
</body>
</html>
HTML;

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
