<?php

namespace Google\Web_Stories_Dependencies\AmpProject\Optimizer;

use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\AmpRuntimeCssConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\PreloadHeroImageConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\TransformedIdentifierConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Exception\InvalidConfigurationValue;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Exception\UnknownConfigurationClass;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Exception\UnknownConfigurationKey;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer\AmpRuntimeCss;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer\PreloadHeroImage;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer\RewriteAmpUrls;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer\TransformedIdentifier;
/**
 * Configuration object that validates and stores configuration settings.
 *
 * @package ampproject/amp-toolbox
 */
class DefaultConfiguration implements \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration
{
    /**
     * Associative array of already validated configuration settings.
     *
     * @var array
     */
    protected $configuration;
    /**
     * Associative array mapping the transformer classes to their configuration classes.
     *
     * This can be extended by third-parties via:
     *
     * @see registerConfigurationClass()
     *
     * @var array
     */
    protected $transformerConfigurationClasses = [\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer\AmpRuntimeCss::class => \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\AmpRuntimeCssConfiguration::class, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer\PreloadHeroImage::class => \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\PreloadHeroImageConfiguration::class, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer\RewriteAmpUrls::class => \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::class, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer\TransformedIdentifier::class => \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\TransformedIdentifierConfiguration::class];
    /**
     * Instantiate a Configuration object.
     *
     * @param array $configurationData Optional. Associative array of configuration data to use. This will be merged
     *                                 with the default configuration and take precedence.
     */
    public function __construct($configurationData = [])
    {
        $this->configuration = \array_merge(static::DEFAULTS, $this->validateConfigurationKeys($configurationData));
    }
    /**
     * Register a new configuration class to use for a given transformer.
     *
     * @param string $transformerClass   FQCN of the transformer to register a configuration class for.
     * @param string $configurationClass FQCN of the configuration to use.
     */
    public function registerConfigurationClass($transformerClass, $configurationClass)
    {
        $this->transformerConfigurationClasses[$transformerClass] = $configurationClass;
    }
    /**
     * Validate an array of configuration settings.
     *
     * @param array $configurationData Associative array of configuration data to validate.
     * @return array Associative array of validated configuration data.
     */
    protected function validateConfigurationKeys($configurationData)
    {
        foreach ($configurationData as $key => $value) {
            $configurationData[$key] = $this->validate($key, $value);
        }
        return $configurationData;
    }
    /**
     * Validate an individual configuration setting.
     *
     * @param string $key   Key of the configuration setting.
     * @param mixed  $value Value of the configuration setting.
     * @return mixed Validated value for the provided configuration setting.
     * @throws InvalidConfigurationValue If the configuration value could not be validated.
     */
    protected function validate($key, $value)
    {
        switch ($key) {
            case \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration::KEY_TRANSFORMERS:
                if (!\is_array($value)) {
                    throw \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Exception\InvalidConfigurationValue::forInvalidValueType(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration::KEY_TRANSFORMERS, 'array', \gettype($value));
                }
                foreach ($value as $index => $entry) {
                    if (!\is_string($entry)) {
                        throw \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Exception\InvalidConfigurationValue::forInvalidSubValueType(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration::KEY_TRANSFORMERS, $index, 'string', \gettype($entry));
                    }
                }
        }
        return $value;
    }
    /**
     * Check whether the configuration has a given setting.
     *
     * @param string $key Configuration key to look for.
     * @return bool Whether the requested configuration key was found or not.
     */
    public function has($key)
    {
        return \array_key_exists($key, $this->configuration);
    }
    /**
     * Get the value for a given key from the configuration.
     *
     * @param string $key Configuration key to get the value for.
     * @return mixed Configuration value for the requested key.
     * @throws UnknownConfigurationKey If the key was not found.
     */
    public function get($key)
    {
        if (!\array_key_exists($key, $this->configuration)) {
            throw \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Exception\UnknownConfigurationKey::fromKey($key);
        }
        return $this->configuration[$key];
    }
    /**
     * Get the transformer-specific configuration for the requested transformer.
     *
     * @param string $transformer FQCN of the transformer to get the configuration for.
     * @return TransformerConfiguration Transformer-specific configuration.
     */
    public function getTransformerConfiguration($transformer)
    {
        if (!\array_key_exists($transformer, $this->transformerConfigurationClasses)) {
            throw \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Exception\UnknownConfigurationClass::fromTransformerClass($transformer);
        }
        $configuration = $this->has($transformer) ? $this->get($transformer) : [];
        $configurationClass = $this->transformerConfigurationClasses[$transformer];
        return new $configurationClass($configuration);
    }
}
