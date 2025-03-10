<?php

namespace Core\Common\Infrastructure\Command;

use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'vault:http:test')]
class VaultHttpTestCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', -1);

        $httpClient = new AmpHttpClient();

        $vaultCredentials = [
            'role_id' => 'xxxxxx',
            'secret_id' => 'xxxxxx',
        ];
        try {
            $responseToken = $httpClient->request(
                'POST',
                'https://xxxx:443/v1/auth/approle/login',
                [
                    'json' => $vaultCredentials,
                ]
            );

            $token = json_decode($responseToken->getContent(), true)['auth']['client_token'];
            $responses = [];
            $start = microtime(true);
            for ($i = 0; $i < 10000; $i++) {
                $responses[] = $httpClient->request(
                    'GET',
                    'https://xxxxx:443/v1/jeremy/data/configuration/broker/'
                        . 'e3646db4-7776-4b17-afe0-f330391a0548',
                    [
                        'headers' => [
                            'X-Vault-Token' => $token,
                            'Connection' => 'keep-alive'
                        ],
                    ]
                );
            }

            $i = 0;
            foreach ($httpClient->stream($responses) as $response => $chunk) {
                if ($chunk->isFirst()) {
                    if ($response->getStatusCode() !== 200) {
                        echo 'Error HTTP CODE:' . $response->getStatusCode();
                        continue;
                    }
                }
                if ($chunk->isLast()) {
                    echo $i  . PHP_EOL;
                }

                $i++;
            }
            $executionTimeHttpClient = microtime(true) - $start;

            // $start = microtime(true);
            // $ch = curl_init();
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: keep-alive']);
            // curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Vault-Token: ' . $token]);
            // $baseUrl = 'https://vault.internal-centreon.click:443/v1/jeremy/data/configuration/broker/'
            //             . 'e3646db4-7776-4b17-afe0-f330391a0548';
            // for ($i = 1; $i <= 10000; $i++) {
            //     curl_setopt($ch, CURLOPT_URL, $baseUrl);
            //     $response = curl_exec($ch);
            //     if (curl_errno($ch)) {
            //         echo "Error fetching $baseUrl: " . curl_error($ch) . "\n";
            //     } else {
            //         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            //         echo "$i" . PHP_EOL;
            //     }
            // }
            // curl_close($ch);
            // $executionTimeCurl = microtime(true) - $start;

            echo 'Execution Time HttpClient: ' . $executionTimeHttpClient . PHP_EOL;
            // echo 'Execution Time Curl: ' . $executionTimeCurl . PHP_EOL;
        } catch (\Exception $ex) {
            dump((string) $ex);
            exit(1);
        }


        return Command::SUCCESS;
    }
}