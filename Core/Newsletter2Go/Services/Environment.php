<?php
namespace Newsletter2Go\Services;

class Environment
{
    const DEMO = "demo";

    const TEST = "test";
    
    const DEVELOPMENT = "development";
    
    const PRODUCTION = "production";

    const STAGING = "staging";

    /**
     * Checks if the current environment is the given environment
     * 
     * @param string $environmentName
     * @return bool
     */
    public function isEnv($environmentName)
    {
        return isset($_ENV['API_ENV']) && $_ENV['API_ENV'] === $environmentName;
    }

    /**
     * Checks if existis an environment variable with the given name otherwise
     * returns the defaul value
     * 
     * @param string $environmentVariable
     * @param string $environmentVariable
     */
    public function getEnv($environmentVariable, $defaultValue = null)
    {
        if (null === $environmentName || !isset($_ENV[$environmentVariable])) {
            return $defaultValue;
        }

        return $_ENV[$environmentVariable];
    }
}
