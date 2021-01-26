<?php


namespace Newsletter2Go\Services;



class ApiService
{
    const GRANT_TYPE = 'https://nl2go.com/jwt';
    const REFRESH_GRANT_TYPE = 'https://nl2go.com/jwt_refresh';
    const API_BASE_URL = 'https://api.newsletter2go.com';

    private $config;

    private $apiKey;
    private $accessToken;
    private $refreshToken;
    private $lastStatusCode;
    private $ref;

    /**
     * ApiService constructor.
     */
    public function __construct()
    {
        $this->config = new Configuration();
        $this->apiKey = $this->config->getConfigParam('authKey');
        $this->accessToken = $this->config->getConfigParam('accessToken');
        $this->refreshToken = $this->config->getConfigParam('refreshToken');
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param array $headers
     * @param bool $authorize
     * @return array
     */
    public function httpRequest($method, $endpoint, $params = [], $headers = ['Content-Type: application/json'], $authorize = false)
    {
        $response = [];
        $response['status'] = 0;

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            if ($authorize) {
                // this is needed for refresh token
                curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey);
            }

            switch ($method) {
                case 'PATCH':
                case 'PUT':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                    $headers[] = 'Content-Length: ' .  strlen(json_encode($params));
                    break;
                case 'POST':
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    break;
                case 'GET':
                    $encodedParams = array();
                    if (count($params) > 0) {
                        foreach ($params as $key => $value) {
                            $encodedParams[] = urlencode($key) . '=' . urlencode($value);
                        }

                        $getParams = "?" . http_build_query($params);
                        $endpoint = $endpoint . $getParams;
                    }
                    break;
                default:
                    return null;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_URL, self::API_BASE_URL . $endpoint);

            $response = json_decode(curl_exec($ch), true);
            $this->setLastStatusCode(curl_getinfo($ch, CURLINFO_HTTP_CODE));

            curl_close($ch);

        } catch (\Exception $exception) {
            $response['error'] = $exception->getMessage();
        }

        return $response;
    }

    /**
     * @return array
     */
    private function refreshToken()
    {
        if ($this->getRefreshToken()) {

            $data = [
                'refresh_token' => $this->getRefreshToken(),
                'grant_type' => self::REFRESH_GRANT_TYPE
            ];

            $result = $this->httpRequest('POST', '/oauth/v2/token', $data, ['Content-Type' => 'application/json'], true);

            if (isset($result['access_token'])) {
                $this->setAccessToken($result['access_token']);
                $this->setRefreshToken($result['refresh_token']);
                try {
                    $this->config->saveConfigParam('refreshToken', $result['refresh_token']);
                } catch (\Exception $exception) {

                }
            }
        } else {
            $this->setLastStatusCode(203);
            $result = ['status' => 203, 'error' => 'no refresh token found'];
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @param string $ref
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
    }

    /**
     * @return mixed
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param mixed $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @return mixed
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @param mixed $refreshToken
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;
    }

    /**
     * @return array
     */
    public function testConnection()
    {
        $refreshResult = $this->refreshToken();

        if ($this->getLastStatusCode() === 200) {
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()];

            $companyResult =  $this->httpRequest('GET', '/companies', [], $headers);

            return [
                'status' => $companyResult['status'],
                'account_id' => $refreshResult['account_id'],
                'company_id' => $companyResult['value'][0]['id'],
                'company_name' => $companyResult['value'][0]['name'],
                'company_bill_address' => $companyResult['value'][0]['bill_address']
            ];

        } else {
            $response['error'] = $refreshResult['error'];
        }

        $response['status'] = $this->getLastStatusCode();

        return $response;
    }

    public function getUserIntegration($userIntegrationId)
    {
        if ($this->getLastStatusCode() === 200) {
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()];
            $userIntegrationResult = $this->httpRequest(
                'GET',
                '/users/integrations/' . $userIntegrationId,
                [],
                $headers
            );
            return $userIntegrationResult['value'][0];
        }
        $userIntegrationResult['status'] = $this->getLastStatusCode();
        return $userIntegrationResult;
    }

    public function getTransactionalMailings($listId)
    {
        if ($this->getLastStatusCode() === 200) {
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()];
            $params = [
                '_fields' => 'id,name',
                '_filter' => '(type=IN=("transaction"));state=IN=("active");sub_type=IN=("cart")'
            ];
            $transactionalMailingsResult = $this->httpRequest(
                'GET',
                '/lists/' . $listId . '/newsletters',
                $params,
                $headers
            );
            return $transactionalMailingsResult['value'];
        }
        $transactionalMailingsResult['status'] = $this->getLastStatusCode();
        return $transactionalMailingsResult;
    }

    public function addTransactionMailingToUserIntegration($userIntegrationId, $transactionMailingId, $handleCartAfter)
    {
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()];
        $params = [
            'newsletter_id' => $transactionMailingId,
            'handle_cart_as_abandoned_after' => $handleCartAfter*60
        ];
        $result = $this->httpRequest(
            'PATCH',
            '/users/integrations/' . $userIntegrationId,
            $params,
            $headers
        );
        return [
            'status' => $result['status'],
        ];
    }

    /**
     * @return mixed
     */
    public function getLastStatusCode()
    {
        return $this->lastStatusCode;
    }

    /**
     * @param mixed $lastStatusCode
     */
    public function setLastStatusCode($lastStatusCode)
    {
        $this->lastStatusCode = $lastStatusCode;
    }
}
