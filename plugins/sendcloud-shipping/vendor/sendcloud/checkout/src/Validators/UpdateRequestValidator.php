<?php

namespace SendCloud\Checkout\Validators;

use SendCloud\Checkout\Contracts\Services\CurrencyService;
use SendCloud\Checkout\Contracts\Validators\RequestValidator;
use SendCloud\Checkout\DTO\Validator;
use SendCloud\Checkout\Exceptions\ValidationException;
use SendCloud\Checkout\HTTP\Request;
use SendCloud\Checkout\SchemaProviders\CheckoutConfigurationSchemaProvider;

/**
 * Class UpdateRequestValidator
 *
 * @package SendCloud\Checkout\Validators
 */
class UpdateRequestValidator implements RequestValidator
{
    /**
     * @var string Plugin version.
     */
    private $pluginVersion;
    /**
     * @var CurrencyService
     */
    private $currencyService;

    /**
     * @param string $pluginVersion
     */
    public function __construct($pluginVersion, CurrencyService $currencyService)
    {
        $this->pluginVersion = $pluginVersion;
        $this->currencyService = $currencyService;
    }

    /**
     * Validates array.
     *
     * @param Request $request
     *
     * @return void
     * @throws ValidationException
     */
    public function validate(Request $request)
    {
        $body = $request->getBody();
        if (empty($body)) {
            throw new ValidationException(array(
                array(
                    'path' => array('body'), 'message' => 'Request payload is empty.'
                )
            ));
        }

        if (!array_key_exists('checkout_configuration', $body)) {
            throw new ValidationException(array(
                array(
                    'path' => array('checkout_configuration'),
                    'message' => "Field is required but missing."
                )
            ));
        }

        if (!array_key_exists('minimal_plugin_version', $body['checkout_configuration'])) {
            throw new ValidationException(array(
                array(
                    'path' => array('checkout_configuration', 'minimal_plugin_version'),
                    'message' => "Field is required but missing."
                )
            ));
        }

        if (!$this->checkIfVersionFormatIsValid($body['checkout_configuration']['minimal_plugin_version'])) {
            throw new ValidationException(array(
                array(
                    'path' => array('checkout_configuration', 'minimal_plugin_version'),
                    'message' => "Format is not valid."
                )
            ));
        }

        if (version_compare($body['checkout_configuration']['minimal_plugin_version'], $this->pluginVersion, '>')) {
            throw new ValidationException(array(
                array(
                    'path' => array('checkout_configuration', 'minimal_plugin_version'),
                    'message' => "Plugin version mismatch detected. Requested to publish a checkout configuration for plugin version {$body['checkout_configuration']['minimal_plugin_version']}, but the plugin version in use is {$this->pluginVersion}."
                )
            ));
        }

        Validator::validate(CheckoutConfigurationSchemaProvider::getSchema(), $request->getBody(), $this->currencyService->getDefaultCurrencyCode());
    }

    /**
     * Check if the version format is valid
     *
     * @param $version
     *
     * @return bool
     */
    private function checkIfVersionFormatIsValid($version)
    {
        $split = explode(".", $version);
        foreach ($split as $s) {
            if (!is_numeric($s)) {
                return false;
            }
        }

        return true;
    }
}