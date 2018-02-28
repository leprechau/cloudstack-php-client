<?php namespace MyENA\CloudStackClientGenerator;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use MyENA\CloudStackClientGenerator\Configuration\Environment;

/**
 * Class Client
 * @package MyENA\CloudStackClientGenerator
 */
class Client {
    /** @var \MyENA\CloudStackClientGenerator\Configuration\Environment */
    protected $env;

    /**
     * Client constructor.
     * @param \MyENA\CloudStackClientGenerator\Configuration\Environment $e
     */
    public function __construct(Environment $e) {
        $this->env = $e;
    }

    /**
     * @param string $command
     * @param array $parameters
     * @param array $headers
     * @return \stdClass
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function do(string $command, array $parameters = [], array $headers = []): \stdClass {
        static $defaultHeaders =
            ['Accept' => ['application/json'], 'Content-Type' => ['application/x-www-form-urlencoded']];

        $params = ['apikey' => $this->env->getKey(), 'command' => $command, 'response' => 'json'] + $parameters;

        ksort($params);

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $uri = new Uri(sprintf(
            '%s/%s?%s&signature=%s',
            $this->env->getCompiledAddress(),
            $this->env->getApiPath(),
            $query,
            $this->env->buildSignature($query)
        ));

        $r = new Request('GET', $uri, $headers + $defaultHeaders);

        $resp = $this->env->getHttpClient()->send($r, [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::DECODE_CONTENT => false,
        ]);

        if (200 !== $resp->getStatusCode()) {
            // attempt to decode response...
            $data = $resp->getBody()->getContents();
            $decoded = @json_decode($data, true);
            if (JSON_ERROR_NONE === json_last_error()) {
                if (1 === count($decoded)) {
                    $decoded = reset($decoded);
                }
                if (isset($decoded['errortext'])) {
                    throw new \RuntimeException($decoded);
                }
            }
            throw new \RuntimeException(sprintf('Received non-200 response: %d %s.  Body: %s', $resp->getStatusCode(), $resp->getReasonPhrase(), $data), NO_VALID_JSON_RECEIVED);
        }

        $body = $resp->getBody();

        if (0 === $body->getSize()) {
            throw new \RuntimeException(NO_DATA_RECEIVED_MSG, NO_DATA_RECEIVED);
        }

        $decoded = @json_decode($body->getContents());
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException(sprintf('%s: %s', NO_VALID_JSON_RECEIVED_MSG, json_last_error_msg()), NO_VALID_JSON_RECEIVED);
        }

        return $decoded;
    }
}