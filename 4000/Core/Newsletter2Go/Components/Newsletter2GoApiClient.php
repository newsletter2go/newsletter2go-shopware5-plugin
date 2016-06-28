<?php

namespace Newsletter2Go\Components;

class Newsletter2GoApiClient
{

    private $host = 'https://app.newsletter2go.com';

    private $key = '936f38795cf8df6ce1e25ce887e8676a';

    private $apiKey;

    /**
     *
     * @param string $apiKey
     */
    public function __construct($apiKey = '')
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Creates new account
     *
     * @param string $lang
     * @param array $params
     * @return array
     */
    public function createAccount($lang, $params)
    {
        $params['key'] = $this->key;

        return $this->executeRequest("/$lang/api/affiliate/createaccount", $params);
    }

    /**
     * Creates new account
     *
     * @param string $lang
     * @param array $params
     * @return array
     */
    public function createIntegration($lang, $params)
    {
        return $this->executeRequest("/$lang/api/create/integration", $params);
    }

    /**
     * Returns ApiKey
     *
     * @return string
     */
    function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Sets ApiKey
     *
     * @param string $apiKey
     */
    function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     *
     * @param string $action
     * @param array $params
     * @return array
     */
    private function executeRequest($action, $params = array())
    {
        // Initialize session.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->host . $action);

        // Set so curl_exec returns the result instead of outputting it.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $encodedParams = array();
        foreach ($params as $key => $value) {
            $encodedParams[] = urlencode($key) . '=' . urlencode($value);
        }

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $encodedParams));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Get the response and close the channel.
        $json = curl_exec($ch);
        curl_close($ch);

        return json_decode($json, true);
    }

}
