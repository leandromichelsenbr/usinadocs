<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Security;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * EN: One synchronizer token per session protects every state-changing request
 * against cross-site request forgery (CSRF). A malicious page can make the
 * browser send a POST with the user's session cookie, but it cannot read this
 * token from our pages, so the forged request arrives without a valid value
 * and is rejected before any route runs.
 *
 * PT: Um único token sincronizador por sessão protege toda requisição que
 * altera estado contra falsificação de requisição entre sites (CSRF). Uma
 * página maliciosa consegue fazer o navegador enviar um POST com o cookie de
 * sessão do usuário, mas não consegue ler este token nas nossas páginas; por
 * isso a requisição forjada chega sem um valor válido e é recusada antes que
 * qualquer rota seja executada.
 *
 * EN: The check is a middleware instead of a call inside each route so that a
 * new form or route is protected by default. Before this class existed, each
 * feature kept its own session key and several administrative routes checked
 * nothing at all.
 *
 * PT: A verificação é um middleware, e não uma chamada dentro de cada rota,
 * para que um novo formulário ou rota já nasça protegido. Antes desta classe,
 * cada recurso guardava sua própria chave de sessão e várias rotas
 * administrativas não verificavam nada.
 */
final class CsrfToken
{
    /** EN: Form field that carries the token. PT: Campo de formulário que transporta o token. */
    public const FIELD = 'csrf';

    /** EN: Header alternative for scripted requests. PT: Cabeçalho alternativo para requisições feitas por script. */
    public const HEADER = 'X-CSRF-Token';

    private const SESSION_KEY = 'csrf_token';

    /** EN: Methods defined by HTTP as safe; they must never change state. PT: Métodos que o HTTP define como seguros; nunca devem alterar estado. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * EN: Returns the session token, creating it on first use. 32 random bytes
     * (256 bits) make guessing infeasible.
     * PT: Retorna o token da sessão, criando-o no primeiro uso. 32 bytes
     * aleatórios (256 bits) tornam a adivinhação inviável.
     */
    public static function current(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            $token = self::rotate();
        }

        return $token;
    }

    /**
     * EN: Replaces the token. Called right after sign-in, together with
     * session_regenerate_id(), so a token seen before authentication cannot be
     * reused by someone who planted it.
     * PT: Substitui o token. É chamado logo após o login, junto com
     * session_regenerate_id(), para que um token visto antes da autenticação
     * não possa ser reaproveitado por quem o tenha plantado.
     */
    public static function rotate(): string
    {
        return $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    /**
     * EN: Constant-time comparison (hash_equals) avoids leaking how many
     * leading characters matched through response timing.
     * PT: A comparação em tempo constante (hash_equals) evita revelar, pelo
     * tempo de resposta, quantos caracteres iniciais coincidiram.
     */
    public static function isValid(mixed $candidate): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($candidate) && is_string($expected) && $expected !== ''
            && hash_equals($expected, $candidate);
    }

    /**
     * EN: Slim middleware. It must run after body parsing, so the form field is
     * available, and after routing, so unknown paths and wrong methods keep
     * answering 404/405 instead of 403. AppFactory registers it in that order.
     * PT: Middleware do Slim. Precisa rodar depois da leitura do corpo, para
     * que o campo do formulário esteja disponível, e depois do roteamento, para
     * que caminhos inexistentes e métodos errados continuem respondendo
     * 404/405 em vez de 403. O AppFactory o registra nessa ordem.
     */
    public static function middleware(): callable
    {
        return static function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
                return $handler->handle($request);
            }

            $body = $request->getParsedBody();
            $candidate = is_array($body) ? ($body[self::FIELD] ?? null) : null;
            if ($candidate === null && $request->hasHeader(self::HEADER)) {
                $candidate = $request->getHeaderLine(self::HEADER);
            }

            if (!self::isValid($candidate)) {
                // EN: A missing or stale token is usually an expired session or an old tab;
                // the message tells the user how to recover without revealing details.
                // PT: Token ausente ou antigo geralmente indica sessão expirada ou aba antiga;
                // a mensagem orienta a recuperação sem revelar detalhes.
                $response = new Response(403);
                $response->getBody()->write('Formulário expirado ou inválido. Recarregue a página e tente novamente.');

                return $response
                    ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
                    ->withHeader('Cache-Control', 'no-store');
            }

            return $handler->handle($request);
        };
    }
}
