<?php
namespace Newsletter2Go\Services;

class Cryptography
{
    private $environment;

    /**
     * @param Environment $environment
     */
    public function __construct($environment)
    {
        $this->environment = $environment;
    }

    /**
     * @param int $length
     */
    public function generateRandomString($length = 40)
    {
        if ($this->environment->isEnv(Environment::DEMO)) {
            return $this->environment->getEnv('DEMO_API_KEY', 'N/A');
        }

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}
