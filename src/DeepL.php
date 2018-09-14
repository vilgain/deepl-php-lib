<?php

namespace BabyMarkt\DeepL;

/**
 * DeepL API client library
 *
 * @package BabyMarkt\DeepL
 */
class DeepL
{
    /**
     * API v1 URL
     */
    const API_URL                  = 'https://api.deepl.com/v1/translate';

    /**
     * API v1  URL: Parameter auth_key
     */
    const API_URL_AUTH_KEY         = 'auth_key=%s';

    /**
     * API v1 URL: Parameter text
     */
    const API_URL_TEXT             = 'text=%s';

    /**
     * API v1 URL: Parameter source_lang
     */
    const API_URL_SOURCE_LANG      = 'source_lang=%s';

    /**
     * API v1 URL: Parameter target_lang
     */
    const API_URL_DESTINATION_LANG = 'target_lang=%s';

    /**
     * API v1 URL: Parameter tag_handling
     */
    const API_URL_TAG_HANDLING = 'tag_handling=%s';

    /**
     * DeepL HTTP error codes
     *
     * @var array
     */
    protected $errorCodes = array(
        400 => 'Wrong request, please check error message and your parameters.',
        403 => 'Authorization failed. Please supply a valid auth_key parameter.',
        413 => 'Request Entity Too Large. The request size exceeds the current limit.',
        429 => 'Too many requests. Please wait and send your request once again.',
        456 => 'Quota exceeded. The character limit has been reached.'
    );

    /**
     * Supported translation source languages
     *
     * @var array
     */
    protected $sourceLanguages = array(
        'EN',
        'DE',
        'FR',
        'ES',
        'IT',
        'NL',
        'PL'
    );

    /**
     * Supported translation destination languages
     *
     * @var array
     */
    protected $destinationLanguages = array(
        'EN',
        'DE',
        'FR',
        'ES',
        'IT',
        'NL',
        'PL'
    );

    /**
     * DeepL API Auth Key (DeepL Pro access required)
     *
     * @var string
     */
    protected $authKey;

    /**
     * cURL resource
     *
     * @var resource
     */
    protected $curl;

    /**
     * DeepL constructor
     *
     * @param $authKey string
     */
    public function __construct($authKey)
    {
        $this->authKey = $authKey;
        $this->curl    = curl_init();

        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
    }

    /**
     * DeepL destructor
     */
    public function __destruct()
    {
        if ($this->curl && is_resource($this->curl)) {
            curl_close($this->curl);
        }
    }

    /**
     * Translate the text string or array from source to destination language
     *
     * @param $text                string|string[]
     * @param $sourceLanguage      string
     * @param $destinationLanguage string
     * @param $tagHandling         array
     *
     * @return string|string[]
     *
     * @throws DeepLException
     */
    public function translate($text, $sourceLanguage = 'de', $destinationLanguage = 'en', array $tagHandling = array())
    {
        // make sure we only accept supported languages
        $this->checkLanguages($sourceLanguage, $destinationLanguage);

        // build the DeepL API request url
        $url  = $this->buildUrl($sourceLanguage, $destinationLanguage, $tagHandling);
        $body = $this->buildBody($text);

        // request the DeepL API
        $translationsArray = $this->request($url, $body);
        $translationsCount = count($translationsArray['translations']);

        if ($translationsCount == 0) {
            throw new DeepLException('No translations found.');
        }
        else if ($translationsCount == 1) {
            return $translationsArray['translations'][0]['text'];
        }
        else {
            return $translationsArray['translations'];
        }
    }

    /**
     * Check if the given languages are supported
     *
     * @param $sourceLanguage      string
     * @param $destinationLanguage string
     *
     * @return boolean
     *
     * @throws DeepLException
     */
    protected function checkLanguages($sourceLanguage, $destinationLanguage)
    {
        $sourceLanguage = strtoupper($sourceLanguage);

        if (!in_array($sourceLanguage, $this->sourceLanguages)) {
            throw new DeepLException(sprintf('The language "%s" is not supported as source language.', $sourceLanguage));
        }

        $destinationLanguage = strtoupper($destinationLanguage);

        if (!in_array($destinationLanguage, $this->destinationLanguages)) {
            throw new DeepLException(sprintf('The language "%s" is not supported as destination language.', $sourceLanguage));
        }

        return true;
    }

    /**
     * Build the URL for the DeepL API request
     *
     * @param $text                string
     * @param $sourceLanguage      string
     * @param $destinationLanguage string
     *
     * @return string
     */
    protected function buildUrl($sourceLanguage, $destinationLanguage, $tagHandling)
    {
        $url = DeepL::API_URL . '?' . sprintf(DeepL::API_URL_AUTH_KEY, $this->authKey);

        $url .= '&' . sprintf(DeepL::API_URL_SOURCE_LANG, strtolower($sourceLanguage));
        $url .= '&' . sprintf(DeepL::API_URL_DESTINATION_LANG, strtolower($destinationLanguage));
        if (!empty($tagHandling)) {
            $url .= '&' . sprintf(DeepL::API_URL_TAG_HANDLING, implode(',', $tagHandling));
        }

        return $url;
    }

    /**
     * Build the body for the DeepL API request
     *
     * @param $text
     *
     * @return string
     */
    protected function buildBody($text)
    {
        $body  = '';
        $first = true;

        if (!is_array($text)) {
            $text = (array)$text;
        }

        foreach ($text as $textElement) {

            $body .= ($first ? '' : '&') . sprintf(DeepL::API_URL_TEXT, rawurlencode($textElement));

            if ($first) {
                $first = false;
            }
        }

        return $body;
    }

    /**
     * Make a request to the given URL
     *
     * @param $url string
     *
     * @return array
     *
     * @throws DeepLException
     */
    protected function request($url, $body)
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        $response = curl_exec($this->curl);

        if (!curl_errno($this->curl)) {
            $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

            if ($httpCode != 200 && array_key_exists($httpCode, $this->errorCodes)) {
                throw new DeepLException($this->errorCodes[$httpCode], $httpCode);
            }
        }
        else {
            throw new DeepLException('There was a cURL Request Error.');
        }

        $translationsArray = json_decode($response, true);

        if (!$translationsArray) {
            throw new DeepLException('The Response seems to not be valid JSON.');
        }

        return $translationsArray;
    }
}