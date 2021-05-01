<?php

namespace Olive_James_Bond\GoogleTranslate;

use ErrorException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Olive_James_Bond\GoogleTranslate\Tokens\GoogleTokenGenerator;
use Olive_James_Bond\GoogleTranslate\Tokens\TokenProviderInterface;
use UnexpectedValueException;

class GoogleTranslate
{

    protected $client;


    protected $source;


    protected $target;

 
    protected $lastDetectedSource;


    protected $url = 'https://translate.google.com/translate_a/single';


    protected $options = [];


    protected $urlParams = [
        'client'   => 'webapp',
        'hl'       => 'en',
        'dt'       => [
            't',   
            'bd',  
            'at',  
            'ex',  
            'ld',  
            'md',  
            'qca', 
            'rw',  
            'rm',  
            'ss'   
        ],
        'sl'       => null, 
        'tl'       => null, 
        'q'        => null, 
        'ie'       => 'UTF-8', 
        'oe'       => 'UTF-8', 
        'multires' => 1,
        'otf'      => 0,
        'pc'       => 1,
        'trs'      => 1,
        'ssel'     => 0,
        'tsel'     => 0,
        'kc'       => 1,
        'tk'       => null,
    ];


    protected $resultRegexes = [
        '/,+/'  => ',',
        '/\[,/' => '[',
    ];


    protected $tokenProvider;


    public function __construct(string $target = 'en', string $source = null, array $options = null, TokenProviderInterface $tokenProvider = null)
    {
        $this->client = new Client();
        $this->setTokenProvider($tokenProvider ?? new GoogleTokenGenerator)
            ->setOptions($options)
            ->setSource($source)
            ->setTarget($target);
    }

    public function setTarget(string $target): self
    {
        $this->target = $target;
        return $this;
    }


    public function setSource(string $source = null): self
    {
        $this->source = $source ?? 'auto';
        return $this;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }


    public function setOptions(array $options = null): self
    {
        $this->options = $options ?? [];
        return $this;
    }


    public function setTokenProvider(TokenProviderInterface $tokenProvider): self
    {
        $this->tokenProvider = $tokenProvider;
        return $this;
    }

    public function getLastDetectedSource(): ?string
    {
        return $this->lastDetectedSource;
    }


    public static function trans(string $string, string $target = 'en', string $source = null, array $options = [], TokenProviderInterface $tokenProvider = null): ?string
    {
        return (new self)
            ->setTokenProvider($tokenProvider ?? new GoogleTokenGenerator)
            ->setOptions($options)
            ->setSource($source)
            ->setTarget($target)
            ->translate($string);
    }

    public function translate(string $string): ?string
    {

        if ($this->source == $this->target) return $string;
        
        $responseArray = $this->getResponse($string);


        if (is_string($responseArray) && $responseArray != '') {
            $responseArray = [$responseArray];
        }


        if (!isset($responseArray[0]) || empty($responseArray[0])) {
            return null;
        }

        $detectedLanguages = [];


        if (!is_string($responseArray)) {
            foreach ($responseArray as $item) {
                if (is_string($item)) {
                    $detectedLanguages[] = $item;
                }
            }
        }


        if (isset($responseArray[count($responseArray) - 2][0][0])) {
            $detectedLanguages[] = $responseArray[count($responseArray) - 2][0][0];
        }


        $this->lastDetectedSource = null;


        foreach ($detectedLanguages as $lang) {
            if ($this->isValidLocale($lang)) {
                $this->lastDetectedSource = $lang;
                break;
            }
        }

        if (is_string($responseArray)) {
            return $responseArray;
        } else {
            if (is_array($responseArray[0])) {
                return (string) array_reduce($responseArray[0], function ($carry, $item) {
                    $carry .= $item[0];
                    return $carry;
                });
            } else {
                return (string) $responseArray[0];
            }
        }
    }


    public function getResponse(string $string): array
    {
        $queryArray = array_merge($this->urlParams, [
            'sl'   => $this->source,
            'tl'   => $this->target,
            'tk'   => $this->tokenProvider->generateToken($this->source, $this->target, $string),
            'q'    => $string
        ]);

        $queryUrl = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', http_build_query($queryArray));

        try {
            $response = $this->client->get($this->url, [
                    'query' => $queryUrl,
                ] + $this->options);
        } catch (RequestException $e) {
            throw new ErrorException($e->getMessage(), $e->getCode());
        }

        $body = $response->getBody();


        $bodyJson = preg_replace(array_keys($this->resultRegexes), array_values($this->resultRegexes), $body);


        if (($bodyArray = json_decode($bodyJson, true)) === null) {
            throw new UnexpectedValueException('Data cannot be decoded or it is deeper than the recursion limit');
        }

        return $bodyArray;
    }

    protected function isValidLocale(string $lang): bool
    {
        return (bool) preg_match('/^([a-z]{2})(-[A-Z]{2})?$/', $lang);
    }
}
