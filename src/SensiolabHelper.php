<?php
/**
 * Created by PhpStorm.
 * User: Alessandro
 * Date: 02/12/2015
 * Time: 13:47
 */

namespace Padosoft\Composer;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

class SensiolabHelper
{

    protected $guzzle;

    protected $command;

    protected $tableVulnerabilities = [];

    /**
     * SensiolabHelper constructor.
     * @param Client $objguzzle
     * @param Command $objcommand
     */
    public function __construct(Client $objguzzle, Command $objcommand)
    {
        $this->guzzle = $objguzzle;
        $this->command = $objcommand;
    }

    /**
     *
     * Send Request to sensiolab and return array of sensiolab vulnerabilities.
     * Empty array if here is no vulnerabilities.
     *
     * @param $fileLock path to composer.lock file.
     *
     * @return array
     */
    public function getSensiolabVulnerabilties($fileLock)
    {
        $this->addVerboseLog('Send request to sensiolab: <info>'.$fileLock.'</info>');

        $debug = false;//set to true to log into console output
        //$debug = fopen("guzzle.log",'w+'); //to log into file
        $headers = [
            //OPTIONS
            'allow_redirects' => [
                'max'             => 3,        // allow at most 10 redirects.
                'strict'          => true,      // use "strict" RFC compliant redirects.
                'referer'         => true,      // add a Referer header
                'protocols'       => ['http', 'https'], // only allow http and https URLs
                'track_redirects' => false
            ],
            'connect_timeout' => 20,//Use 0 to wait connection indefinitely
            'timeout' => 30, //Use 0 to wait response indefinitely
            'debug' => $debug,
            //HEADERS
            'headers'  => [
                'Accept' => 'application/json'
            ],
            //UPLOAD FORM FILE
            'multipart' => [
                [
                    'name' => 'lock',
                    'contents' => fopen($fileLock, 'r')
                ]
            ]
        ];
        $response = null;

        try {
            $iResponse = $this->guzzle->request('POST', 'https://security.sensiolabs.org/check_lock', $headers);
            $responseBody = $iResponse->getBody()->getContents();
            //$this->info(substr($responseBody,0,200));
            $response = json_decode($responseBody, true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->command->error("ClientException!\nMessage: ".$e->getMessage());
            $colorTag = $this->getColorTagForStatusCode($e->getResponse()->getStatusCode());
            $this->command->line("HTTP StatusCode: <{$colorTag}>".$e->getResponse()->getStatusCode()."<{$colorTag}>");
            $this->printResponse($e->getResponse());
            $this->printRequest($e->getRequest());
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->command->error("RequestException!\nMessage: ".$e->getMessage());
            $this->printRequest($e->getRequest());
            if ($e->hasResponse()) {
                $colorTag = $this->getColorTagForStatusCode($e->getResponse()->getStatusCode());
                $this->command->line("HTTP StatusCode: <{$colorTag}>".$e->getResponse()->getStatusCode()."<{$colorTag}>");
                $this->printResponse($e->getResponse());
            }
        }
        return $response;
    }

    /**
     * @param $name
     * @param $vulnerability
     */
    public function parseVulnerability($name, $vulnerability)
    {
        $data = [
            'name' => $name,
            'version' => $vulnerability['version'],
            'advisories' => array_values($vulnerability['advisories'])
        ];
        unset($this->tableVulnerabilities);
        foreach ($data['advisories'] as $key2 => $advisory) {
            $data2 = [
                'title' => $advisory['title'],
                'link' => $advisory['link'],
                'cve' => $advisory['cve']
            ];

            $dataTable = [
                'name' => $data['name'],
                'version' => $data['version'],
                'advisories' => $data2["title"]
            ];

            $this->addVerboseLog($data['name'] . " " . $data['version'] . " " . $data2["title"], true);
            $this->tableVulnerabilities[] =$dataTable;
        }

        //dd($this->tableVulnerabilities);
        return $this->tableVulnerabilities;
    }

    /**
     * @param            $msg
     * @param bool|false $error
     */
    private function addVerboseLog($msg, $error = false)
    {
        $verbose = $this->command->option('verbose');
        if ($verbose) {
            if ($error) {
                $this->command->error($msg);
            } else {
                $this->command->line($msg);
            }
        }
    }

    /**
     * @param Response $response
     */
    private function printResponse(\Psr\Http\Message\ResponseInterface $response)
    {
        $this->command->info('RESPONSE:');
        $headers = '';
        foreach ($response->getHeaders() as $name => $values) {
            $headers .= $name . ': ' . implode(', ', $values) . "\r\n";
        }
        $this->command->comment($headers);
        $this->command->comment($response->getBody()->getContents());
    }

    /**
     * @param Request $request
     */
    private function printRequest(\Psr\Http\Message\RequestInterface $request)
    {
        $this->command->info('REQUEST:');
        $headers='';
        foreach ($request->getHeaders() as $name => $values) {
            $headers .= $name . ': ' . implode(', ', $values) . "\r\n";
        }
        $this->command->comment($headers);
        $this->command->comment($request->getBody());
    }

    /**
     * Get the color tag for the given status code.
     *
     * @param string $code
     *
     * @return string
     *
     * @see https://github.com/spatie/http-status-check/blob/master/src/CrawlLogger.php#L96
     */
    protected function getColorTagForStatusCode($code)
    {
        if (starts_with($code, '2')) {
            return 'info';
        }
        if (starts_with($code, '3')) {
            return 'comment';
        }
        return 'error';
    }
}