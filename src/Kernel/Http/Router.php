<?php
// =============================================
// ROUTER — wrapper fino sobre bramus/router.
// Suporta handlers como closure ou [Classe, 'metodo']
// (a classe é resolvida pelo container). O handler
// recebe o ServerRequest PSR-7 como primeiro argumento
// e retorna ResponseInterface|string.
// =============================================

declare(strict_types=1);

namespace Kernel\Http;

use Bramus\Router\Router as BramusRouter;
use Kernel\Container\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    private BramusRouter $router;
    private Container $container;
    private ?ServerRequestInterface $request = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->router = new BramusRouter();
    }

    public function get(string $pattern, callable|array $handler): void
    {
        $this->router->get($pattern, fn (...$args) => $this->emit($this->call($handler, $args)));
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->router->post($pattern, fn (...$args) => $this->emit($this->call($handler, $args)));
    }

    public function set404(callable $handler): void
    {
        $this->router->set404(fn () => $this->emit($this->call($handler, [])));
    }

    /**
     * Executa o roteamento da requisição corrente.
     */
    public function run(): void
    {
        $this->router->run();
    }

    /**
     * Resolve o handler (closure ou [classe, método]) e o executa.
     */
    private function call(callable|array $handler, array $args): mixed
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = $this->container->get($class);
            return $controller->{$method}($this->request(), ...$args);
        }
        return $handler($this->request(), ...$args);
    }

    /**
     * Monta o ServerRequest PSR-7 a partir dos superglobals do PHP
     * (sem a dependência nyholm/psr7-server).
     */
    private function request(): ServerRequestInterface
    {
        if ($this->request !== null) {
            return $this->request;
        }

        $factory = new Psr17Factory();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $target = $_SERVER['REQUEST_URI'] ?? '/';

        $request = $factory->createServerRequest($method, $scheme . '://' . $host . $target, $_SERVER);
        $request = $request->withQueryParams($_GET);
        $request = $request->withCookieParams($_COOKIE);

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $request = $request->withParsedBody($_POST);
        }

        // Copia os headers HTTP para o request PSR-7 (o Nyholm não lê
        // automaticamente de $_SERVER ao criar via createServerRequest).
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
                $request = $request->withHeader(
                    str_replace('_', '-', substr($key, 5)),
                    $value
                );
            }
        }
        if (!empty($_SERVER['CONTENT_TYPE'])) {
            $request = $request->withHeader('Content-Type', (string) $_SERVER['CONTENT_TYPE']);
        }
        if (!empty($_SERVER['CONTENT_LENGTH'])) {
            $request = $request->withHeader('Content-Length', (string) $_SERVER['CONTENT_LENGTH']);
        }

        return $this->request = $request;
    }

    /**
     * Converte o retorno do handler em resposta e a envia.
     */
    private function emit(mixed $response): void
    {
        if (!$response instanceof ResponseInterface) {
            $response = new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                is_string($response) ? $response : (string) $response
            );
        }

        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value);
            }
        }
        echo (string) $response->getBody();
    }
}
