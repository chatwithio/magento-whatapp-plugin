<?php

namespace Tochat\Whatsapp\Helper;

use Tochat\Whatsapp\Helper\Data as DataHelper;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Api
{
    /**
     * @var DataHelper
     */
    protected $dataHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    private $endpoint = [
        'contact' => [
            'method' => 'POST',
            'url' => 'contacts'
        ],
        'message' => [
            'method' => 'POST',
            'url' => 'messages'
        ]
        ,
        'template' => [
            'method' => 'GET',
            'url' => 'configs/templates'
        ],
    ];

    public function __construct(
        DataHelper $dataHelper,
        LoggerInterface $logger
    ) {
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
    }

    private function send($endpoint, $data = [])
    {
        try {
            $client = new Client();

            $url = $this->dataHelper->getModuleConfig('automation/endpoint');

            $request = $client->request(
                $this->endpoint[$endpoint]['method'],
                $url . $this->endpoint[$endpoint]['url'],
                [
                    "headers" => [
                        'Content-Type' => 'application/json',
                        'D360-API-KEY' => $this->dataHelper->getModuleConfig('automation/apikey')
                    ],
                    "json" => $data
                ]
            );

            if ($request->getStatusCode() == 200 || $request->getStatusCode() == 201) {
                return json_decode($request->getBody()->getContents());
            } else {
                return json_decode($request->getBody()->getContents());
            }
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }

    private function buildMessage($messageTemplate, $placeholders)
    {
        $counter = 1;
        foreach ($placeholders as $placeholder) {
            $messageTemplate = str_replace("{$counter}", $placeholder, $messageTemplate);
        }
        return $messageTemplate;
    }

    public function getTemplates()
    {
        return $this->send('template');
    }

    public function sendWhatsApp($to, $placeholders, $template, $language, $namespace)
    {
        $payload = [
            "to" => $to,
            "type" => "template",
            "template" => [
                "namespace" => $namespace,
                "language" => [
                    "policy" => "deterministic",
                    "code" => $language
                ],
                "name" => $template,
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => $this->buildParams($placeholders)

                    ]
                ]
            ]
        ];
        return $this->send('message', $payload);
    }

    /**
     * @param $placeholders (an array of text only placeholders)
     * @return array
     */
    private function buildParams($placeholders)
    {
        $arr = [];
        foreach ($placeholders as $placeholder) {
            $arr[] = [
                "type" => "text",
                "text" => $placeholder
            ];
        }
        return $arr;
    }

    public function checkContact($contact)
    {
        try {
            //Since sanbox does not provide contact validation
            $payload = [
                "blocking" => "wait",
                "contacts" => ["+" . $contact],
                "force_check" => true
            ];
            $response = $this->send('contact', $payload);
            if (!empty($response->contacts)) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $exception) {
            $this->logger->debug($exception);
        }
        return false;
    }

    public function isActive()
    {
        return !empty($this->dataHelper->getModuleConfig('automation/apikey'))
        && $this->dataHelper->getModuleConfig('automation/status');
    }
}
