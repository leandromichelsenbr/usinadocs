<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Social;

use Closure;

final class SocialPublisher
{
    public function __construct(private readonly SocialPublication $publication)
    {
    }

    /**
     * Deliver queued articles through a substitutable transport so tests never contact a social network.
     * Envia os artigos da fila por um transporte substituível para que os testes nunca contatem uma rede social.
     */
    public function run(string $origin, array $credentials, Closure $post): void
    {
        $channels = $this->publication->channels();
        foreach (array_keys(SocialPublication::NETWORKS) as $network) {
            $credential = $credentials[$network] ?? null;
            $configuredDestination = $channels[$network]['destination_id'] ?? null;
            // A saved destination takes precedence. Legacy installations can still use an ID in the private file.
            // O destino salvo tem prioridade. Instalações antigas ainda podem usar o ID no arquivo privado.
            if (is_array($credential) && $configuredDestination !== null) {
                $credential['id'] = $configuredDestination;
            }
            if (!self::validCredential($network, $credential)) {
                continue;
            }

            foreach ($this->publication->pending($network) as $article) {
                $url = ArticleLinks::url($origin, $article);
                [$endpoint, $headers, $body] = self::request($network, $credential, $article, $url);
                if ($this->publication->claim($article['page_id'], $network, $configuredDestination)) {
                    $status = $post($endpoint, $headers, $body);
                    $this->publication->finish($article['page_id'], $network, $status);
                }
            }
        }
    }

    private static function validCredential(string $network, mixed $config): bool
    {
        return is_array($config)
            && is_string($config['id'] ?? null)
            && preg_match('/\A[0-9]+\z/', $config['id'])
            && is_string($config['token'] ?? null)
            && preg_match('/\A[!-~]+\z/', $config['token'])
            && is_string($config['version'] ?? null)
            && preg_match($network === 'linkedin' ? '/\A[0-9]{6}\z/' : '/\Av[0-9]+\.[0-9]+\z/', $config['version']);
    }

    private static function request(string $network, array $config, array $article, string $url): array
    {
        // Fixed provider hosts keep bearer tokens scoped to the intended APIs.
        // Hosts fixos dos provedores mantêm os tokens restritos às APIs correspondentes.
        $headers = ['Authorization: Bearer '.$config['token']];
        if ($network === 'facebook') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $body = http_build_query([
                'link' => $url,
                'message' => trim($article['title']."\n\n".$article['summary']),
            ]);
            return ['https://graph.facebook.com/'.$config['version'].'/'.$config['id'].'/feed', $headers, $body];
        }

        $headers[] = 'Content-Type: application/json';
        $headers[] = 'LinkedIn-Version: '.$config['version'];
        $headers[] = 'X-Restli-Protocol-Version: 2.0.0';

        // LinkedIn requires article metadata explicitly because its Posts API does not scrape article URLs.
        // O LinkedIn exige metadados explícitos do artigo porque a API Posts não extrai dados do endereço.
        $body = json_encode([
            'author' => 'urn:li:organization:'.$config['id'],
            'commentary' => preg_replace_callback(
                '/[|{}@\[\]()<>#\\\\*_~]/',
                static fn (array $character): string => '\\'.$character[0],
                $article['title'],
            ),
            'visibility' => 'PUBLIC',
            'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
            'content' => ['article' => ['source' => $url, 'title' => $article['title'], 'description' => $article['summary']]],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        return ['https://api.linkedin.com/rest/posts', $headers, $body];
    }
}
