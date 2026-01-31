<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class StaticTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
        #[Autowire('%env(ROOT_API_TOKEN)%')] private readonly string $rootToken,
        #[Autowire('%env(USER_API_TOKEN)%')] private readonly string $userToken
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/v1/api/users/doc')) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authHeader = (string) $request->headers->get('Authorization', '');
        if ($authHeader === '' || !str_starts_with($authHeader, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing API token.');
        }

        $token = trim(substr($authHeader, 7));
        $login = $this->resolveLogin($token);

        return new SelfValidatingPassport(new UserBadge($login, function (string $identifier): UserInterface {
            $user = $this->userRepository->findOneBy(['login' => $identifier]);
            if (!$user) {
                throw new CustomUserMessageAuthenticationException('User not found.');
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['status' => Response::HTTP_UNAUTHORIZED, 'message' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED
        );
    }

    private function resolveLogin(string $token): string
    {
        if (hash_equals($this->rootToken, $token)) {
            return 'root';
        }

        if (hash_equals($this->userToken, $token)) {
            return 'user';
        }

        throw new CustomUserMessageAuthenticationException('Invalid API token.');
    }
}
