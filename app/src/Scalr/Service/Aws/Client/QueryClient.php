<?php
namespace Scalr\Service\Aws\Client;

use Scalr\Service\Aws;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\Event\EventType;
use Scalr\Service\Aws\Event\ErrorResponseEvent;

/**
 * Amazon Query API client.
 *
 * HTTP Query-based requests are defined as any HTTP requests using the HTTP verb GET or POST
 * and a Query parameter named either Action or Operation.
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     21.09.2012
 */

class QueryClient extends AbstractClient implements ClientInterface
{
    /**
     * Base url for API requests
     *
     * @var string
     */
    protected $url;

    /**
     * AWS Access Key Id
     *
     * @var string
     */
    protected $awsAccessKeyId;

    /**
     * Secret Access Key
     *
     * @var string
     */
    protected $secretAccessKey;

    /**
     * AWS API Version
     *
     * @var string
     */
    protected $apiVersion;

    /**
     * Useragent
     *
     * @var string
     */
    protected $useragent;

    /**
     * Constructor
     *
     * @param    string    $awsAccessKeyId    AWS Access Key Id
     * @param    string    $secretAccessKey   AWS Secret Access Key
     * @param    string    $apiVersion        YYYY-MM-DD representation of AWS API version
     * @param    string    $url
     */
    public function __construct($awsAccessKeyId, $secretAccessKey, $apiVersion, $url = null)
    {
        $this->awsAccessKeyId = $awsAccessKeyId;
        $this->secretAccessKey = $secretAccessKey;
        $this->setApiVersion($apiVersion);
        $this->setUrl($url);
        $this->useragent = sprintf('Scalr AWS Client (http://scalr.com) PHP/%s pecl_http/%s',
            phpversion(), phpversion('http')
        );
    }

    /**
     * Sets Api Version
     *
     * @param     string    $apiVersion  YYYY-MM-DD representation of AWS API version
     */
    public function setApiVersion($apiVersion)
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $apiVersion, $m)) {
            $apiVersion = $m[1] . '-' . $m[2] . '-' . $m[3];
        } else if (!preg_match('/^[\d]{4}\-[\d]{2}\-[\d]{2}$/', $apiVersion)) {
            throw new QueryClientException(
                'Invalid API version ' . $apiVersion . '. '
              . 'You should have used following format YYYY-MM-DD.'
            );
        }
        $this->apiVersion = $apiVersion;
    }

    /**
     * Gets API Version date
     *
     * @return string Returns API Version Date in YYYY-MM-DD format
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * Sets query url
     *
     * @param    string   $url  Base url for API requests
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Gets base url for API requests
     *
     * @return   string  Returns base url for API requests
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Gets expiration time for Expires option.
     *
     * @return   string   Returns expiration time form Expires option
     *                    that's used in AWS api requests.
     */
    protected function getExpirationTime()
    {
        return gmdate('c', time() + 3600);
    }

    /**
     * Calls Amazon web service method.
     *
     * It ensures execution of the certain AWS action by transporting the request
     * and receiving response.
     *
     * @param     string    $action           An Web service API action name.
     * @param     array     $options          An options array. It may contain "_host" option which overrides host.
     * @param     string    $path    optional A relative path.
     * @return    ClientResponseInterfa
     * @throws    ClientException
     */
    public function call($action, $options, $path = '/')
    {
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }
        $time = time();
        $httpMethod = 'POST';

        $this->lastApiCall = null;

        $commonDefault = array(
            'AWSAccessKeyId'   => $this->awsAccessKeyId,
            'Action'           => $action,
            'SignatureVersion' => '2',
            'SignatureMethod'  => 'HmacSHA1',
            'Version'          => $this->getApiVersion(),
            'Timestamp'        => gmdate('Y-m-d\TH:i:s', $time) . "Z",
        );

        if (isset($options['_host'])) {
            $host = $options['_host'];
            unset($options['_host']);
        } else {
            $host = $this->url;
        }


        if (strpos($host, 'http') === 0) {
            $arr = parse_url($host);
            $scheme = $arr['scheme'];
            $host = $arr['host'] . (isset($arr['port']) ? ':' . $arr['port'] : '');
            $path = (!empty($arr['path']) && $arr['path'] != '/' ? rtrim($arr['path'], '/') : '') . $path;
        } else {
            $scheme = 'https';
        }

        $options = array_merge($commonDefault, $options);
        //Sorting is necessary according to Query API rules
        ksort($options);
        $canonicalizedQueryString = '';
        foreach ($options as $k => $v) {
            $canonicalizedQueryString .= '&' . rawurlencode($k) . '=' . rawurlencode($v);
        }
        if ($canonicalizedQueryString !== '') {
            $canonicalizedQueryString = substr($canonicalizedQueryString, 1);
        }

        $stringToSign =
            $httpMethod . "\n"
          . strtolower($host) . "\n"
          . $path . "\n"
          . $canonicalizedQueryString
        ;

        switch ($options['SignatureMethod']) {
            case 'HmacSHA1':
            case 'HmacSHA256':
                $algo = strtolower(substr($options['SignatureMethod'], 4));
                break;
            default:
                throw new QueryClientException(
                    'Unknown SignatureMethod ' . $options['SignatureMethod']
                );
        }

        if (isset($options['Action'])) {
            $this->lastApiCall = $options['Action'];
        }

        $options['Signature'] = base64_encode(hash_hmac($algo, $stringToSign, $this->secretAccessKey, 1));

        $httpRequest = $this->createRequest();
        $httpRequest->addHeaders(array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'Date'         => gmdate('r', $time),
        ));
        $httpRequest->setUrl($scheme . '://' . $host . $path);
        $httpRequest->setMethod(constant('HTTP_METH_' . $httpMethod));
        $httpRequest->addPostFields($options);

        $response = $this->tryCall($httpRequest);

        if ($this->getAws() && $this->getAws()->getDebug()) {
            echo "\n";
            echo $httpRequest->getRawRequestMessage() . "\n";
            echo $httpRequest->getRawResponseMessage() . "\n";
        }
        return $response;
    }

    /**
     * Creates a new HttpRequest object.
     *
     * @return \HttpRequest Returns a new HttpRequest object.
     */
    public function createRequest()
    {
        $q = new \HttpRequest();
        //HttpRequest has a pitfall which persists cookies between different requests.
        //IMPORTANT! This line causes error with old version of curl
        //$q->resetCookies();
        $q->setOptions(array(
            'redirect'       => 10,
            'useragent'      => $this->useragent,
            'verifypeer'     => false,
            'verifyhost'     => false,
            'timeout'        => 30,
            'connecttimeout' => 30,
        ));

        $proxySettings = $this->getAws()->getProxy();
        if ($proxySettings !== false) {
            $q->setOptions(array(
                'proxyhost' => $proxySettings['host'],
                'proxyport' => $proxySettings['port'],
                'proxytype' => $proxySettings['type']
            ));
            if ($proxySettings['user']) {
                $q->setOptions(array(
                    'proxyauth' => "{$proxySettings['user']}:{$proxySettings['pass']}",
                    'proxyauthtype' => HTTP_AUTH_BASIC
                ));
            }
        }

        return $q;
    }

    /**
     * Tries to send request on several attempts.
     *
     * @param    \HttpRequest    $httpRequest
     * @param    int             $attempts     Attempts count.
     * @param    int             $interval     An sleep interval between an attempts in microseconds.
     * @returns  QueryClientResponse  Returns response on success
     * @throws   QueryClientException
     */
    protected function tryCall($httpRequest, $attempts = 1, $interval = 200)
    {
        try {
            $message = $httpRequest->send();

            if (preg_match('/^<html.+ Service Unavailable/', $message->getBody()) && --$attempts > 0) {
                usleep($interval);
                return $this->tryCall($httpRequest, $attempts, $interval * 2);
            }

            //Increments the queries quantity
            $this->_incrementQueriesQuantity();

            $response = new QueryClientResponse($message);
            $response->setRequest($httpRequest);
            $response->setQueryNumber($this->getQueriesQuantity());

            if ($response->hasError()) {
                $eventObserver = $this->getAws()->getEventObserver();
                /* @var $clientException ClientException */
                $clientException = $response->getException();
                //It does not need anymore
                //$response->setEventObserver($eventObserver);
                if (isset($eventObserver) && $eventObserver->isSubscribed(EventType::EVENT_ERROR_RESPONSE)) {
                    $eventObserver->fireEvent(new ErrorResponseEvent(array(
                        'exception' => $clientException,
                        'apicall'   => $clientException->getApiCall(),
                    )));
                }
                if ($clientException->getErrorData() instanceof ErrorData &&
                    $clientException->getErrorData()->getCode() == ErrorData::ERR_REQUEST_LIMIT_EXCEEDED) {
                    if (--$attempts > 0) {
                        //Tries to handle RequestLimitExceeded AWS Response
                        sleep(3);
                        return $this->tryCall($httpRequest, $attempts, $interval);
                    }
                }
            }
        } catch (\HttpException $e) {
            if (--$attempts > 0) {
                usleep($interval);
                return $this->tryCall($httpRequest, $attempts, $interval * 2);
            } else {
                $error = new ErrorData();
                $error->message = 'Cannot establish connection to AWS server. ' . (isset($e->innerException) ? preg_replace('/(\(.*\))/', '', $e->innerException->getMessage()) : $e->getMessage());
                throw new ClientException($error);
            }
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientInterface::getType()
     */
    public function getType()
    {
        return Aws::CLIENT_QUERY;
    }
}