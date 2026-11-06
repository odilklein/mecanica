<?php
// =============================================
// ROUTER — wrapper fino sobre bramus/router.
// Handlers retornam uma ResponseInterface (PSR-7)
// ou string; o Router cuida da emissão.
// =============================================

declare(strict_types=1);

namespace Kernel\Http;

use Bramus\Router\Router as BramusRouter;
use Kernel\Container\Container;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class Router
{
    private BramusRouter $router;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->router = new BramusRouter();
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->router->get($pattern, fn (...$args) => $this->emit($handler(...$args)));
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->router->post($pattern, fn (...$args) => $this->emit($handler(...$args)));
    }

    public function set404(callable $handler): void
    {
        $this->router->set404(fn () => $this->emit($handler()));
    }

    /**
     * Executa o roteamento da requisição corrente.
     */
    public function run(): void
    {
        $this->router->run();
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
