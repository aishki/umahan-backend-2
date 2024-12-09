<?php

namespace App;

use GuzzleHttp\Client;

class TursoClient
{
    private $client;
    private $authToken;

    public function __construct($databaseUrl, $authToken)
    {
        $this->authToken = $authToken;

        $this->client = new Client([
            'base_uri' => preg_replace('/^libsql:\/\//', 'https://', $databaseUrl),
            'timeout'  => 30.0,
        ]);
    }

    public function executeQuery($sql, $params = [])
    {
        try {
            $requests = [
                [
                    'type' => 'execute',
                    'stmt' => [
                        'sql' => $sql,
                    ],
                ],
                [
                    'type' => 'close',
                ],
            ];

            // Convert all parameters to strings before sending
            if (!empty($params)) {
                $requests[0]['stmt']['args'] = array_map(function ($param) {
                    return ['type' => 'text', 'value' => (string)$param]; 
                }, $params);
            }

            $response = $this->client->post('/v2/pipeline', [
                'json' => ['requests' => $requests],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->authToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $body = $response->getBody();
            return json_decode($body, true);

        } catch (\Exception $e) {
            throw new \Exception("Database query failed: " . $e->getMessage());
        }
    }
}