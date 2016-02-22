<?php
namespace SMS;

/**
 * Class GoSMS
 * @package SMS
 * @author Tomas Kavalek
 * @see https://doc.gosms.cz/#dokumentace-gosms-api
 */
class GoSMS
{
    /**
     * Service base URL
     */
    const SERVICE_BASE_URL = 'https://app.gosms.cz/';
    /**
     * Token URL
     */
    const SERVICE_OAUTH_URL = 'oauth/v2/token';
    /**
     * Info URL
     */
    const SERVICE_INFO_URL = 'api/v1/';
    /**
     * Test URL
     */
    const SERVICE_TEST_URL = 'api/v1/messages/test';
    /**
     * Send URL
     */
    const SERVICE_SEND_URL = 'api/v1/messages/';

    /**
     * Credential type
     */
    const GRANT_TYPE_CLIENT_CREDENTIALS = 'client_credentials';
    /**
     * Default credentials type
     */
    const GRANT_TYPE_DEFAULT = self::GRANT_TYPE_CLIENT_CREDENTIALS;

    /**
     * @var string
     */
    public $clientId;
    /**
     * @var string
     */
    public $clientSecret;
    /**
     * @var null|string
     */
    public $grantType;

    /**
     * @var array
     */
    public $recipients;
    /**
     * @var string
     */
    public $message;
    /**
     * @var integer
     */
    public $channel;
    /**
     * @var string
     */
    public $expectedSendStart;

    /**
     * @var \RestClient
     */
    private $api;
    /**
     * @var string
     */
    private $token;

    /**
     * GoSMS constructor.
     * @param $clientId
     * @param $clientSecret
     * @param null $grantType
     */
    public function __construct($clientId, $clientSecret, $grantType = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->grantType = ! is_null($grantType) ? $grantType : self::GRANT_TYPE_DEFAULT;

        $this->api = new \RestClient(
            array(
                'base_url' => self::SERVICE_BASE_URL,
            )
        );

        return $this;
    }

    /**
     * Authentication using credentials from constructor
     * @return $this
     * @throws GoSMSException\Another
     * @throws GoSMSException\InvalidCredentials
     */
    public function authenticate()
    {
        $result = $this->api->post(
            self::SERVICE_OAUTH_URL,
            array(
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => $this->grantType,
            )
        );

        if ( $result->info->http_code === 400 ) {
            throw new GoSMSException\InvalidCredentials('Bad credentials or grant_type missing');
        } elseif ( $result->info->http_code !== 200 ) {
            throw new GoSMSException\Another('Another error');
        }

        $response = json_decode($result->response);

        $this->token = $response->access_token;

        return $this;
    }

    /**
     * Get info about logged account
     * Only credit in this time
     * @return mixed
     * @throws GoSMSException\Another
     */
    public function getInfo()
    {
        $result = $this->api->get(
            self::SERVICE_INFO_URL,
            array(),
            array(
                'Authorization' => 'Bearer ' . $this->token,
            )
        );

        $response = json_decode($result->response);

        if ( $result->info->http_code !== 200 ) {
            throw new GoSMSException\Another($response->error_description);
        }


        return $response;
    }

    /**
     * Method for testing message before real send
     * @return null|object
     * @throws GoSMSException\Another
     * @throws GoSMSException\JSONAPIProblem
     */
    public function test()
    {
        $result = $this->api->post(
            self::SERVICE_TEST_URL,
            json_encode(array(
                'message' => $this->message,
                'recipients' => $this->recipients,
                'channel' => $this->channel,
            )),
            $this->getRequestTokenHeader()
        );

        $response = json_decode($result->response);

        if ( $result->info->http_code !== 200 ) {
            if ( false !== ( $error = $this->getJSONAPIProblem($response) ) ) {
                throw new GoSMSException\JSONAPIProblem($error->title . ' ' . $error->detail);
            } else {
                throw new GoSMSException\Another('Error not returned as JSON API Problem');
            }
        }

        return $response;
    }

    /**
     * Send message
     *
     * @return null|object
     * @throws GoSMSException\Another
     * @throws GoSMSException\JSONAPIProblem
     * @throws GoSMSException\ServerError
     * @throws GoSMSException\TokenExpired
     */
    public function send()
    {
        $result = $this->api->post(
            self::SERVICE_SEND_URL,
            json_encode(array(
                'message' => $this->message,
                'recipients' => $this->recipients,
                'channel' => $this->channel,
            )),
            $this->getRequestTokenHeader()
        );

        $response = json_decode($result->response);

        if ( $result->info->http_code !== 201 ) {
            if ( $result->info->http_code === 401 ) {
                throw new GoSMSException\TokenExpired('Token has expired');
            } elseif ( $result->info->http_code === 400 ) {
                if ( false !== ( $error = $this->getJSONAPIProblem($response) ) ) {
                    throw new GoSMSException\JSONAPIProblem($error->title . ' ' . $error->detail);
                } else {
                    throw new GoSMSException\Another('Error not returned as JSON API Problem');
                }
            } elseif ( $result->info->http_code === 500 ) {
                throw new GoSMSException\ServerError('Server error');
            } else {
                throw new GoSMSException\Another('Another error');
            }
        }

        return $response;
    }

    /**
     * Get full detail of message against its url (response from send method)
     * @param $url
     * @return null|object
     * @throws GoSMSException\AccessDenied
     * @throws GoSMSException\Another
     * @throws GoSMSException\MessageNotFound
     * @throws GoSMSException\TokenExpired
     */
    public function getMessageDetail($url)
    {
        $url = ltrim($url, '/');

        $result = $this->api->get(
            $url,
            array(),
            $this->getRequestTokenHeader()
        );

        $response = json_decode($result->response);

        if ( $result->info->http_code !== 200 ) {
            if ( $result->info->http_code === 401 ) {
                throw new GoSMSException\TokenExpired('Token has expired');
            } elseif ( $result->info->http_code === 403 ) {
                throw new GoSMSException\AccessDenied('Access denied');
            } elseif ( $result->info->http_code === 404 ) {
                throw new GoSMSException\MessageNotFound('Message not found');
            } else {
                throw new GoSMSException\Another('Another error');
            }
        }

        return $response;
    }

    /**
     * Set recipient
     * @param $recipient
     * @throws GoSMSException\InvalidFormat
     */
    public function setRecipient($recipient)
    {
        if ( ! preg_match('~^\+[0-9]{12}$~', $recipient) ) {
            throw new GoSMSException\InvalidFormat('Invalid recipient number format');
        }

        $this->recipients[] = $recipient;
    }

    /**
     * Set recipients
     * @param array $recipients
     * @throws GoSMSException\InvalidFormat
     */
    public function setRecipients($recipients)
    {
        if ( ! is_array($recipients) ) {
            throw new GoSMSException\InvalidFormat('Invalid recipients format - array required');
        }

        foreach ($recipients as $recipient) {
            $this->setRecipient($recipient);
        }
    }

    /**
     * Set message
     * @param $message
     * @throws GoSMSException\InvalidFormat
     */
    public function setMessage($message)
    {
        if ( ! is_string($message) || empty( $message ) ) {
            throw new GoSMSException\InvalidFormat('Invalid message format');
        }

        $this->message = $message;
    }

    /**
     * Set channel
     * @param integer $channel
     * @throws GoSMSException\InvalidChannel
     */
    public function setChannel($channel)
    {
        if ( ! is_int($channel) || ( $channel < 0 ) ) {
            throw new GoSMSException\InvalidChannel('Invalid channel');
        }

        $this->channel = $channel;
    }

    /**
     * Parse response
     * @param $response
     * @return bool|object
     */
    public function getJSONAPIProblem($response)
    {
        if ( isset( $response->type ) && isset( $response->title ) && isset( $response->status ) && isset( $response->detail ) ) {
            return $response;
        }

        return false;
    }

    /**
     * Generate request token - header
     * @return array
     */
    private function getRequestTokenHeader() {
        return array(
            'Content-Type' => "application/json",
            'Authorization' => 'Bearer ' . $this->token,
        );
    }
}
