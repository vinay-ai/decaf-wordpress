<?php

namespace Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer;

use Google\Web_Stories_Dependencies\AmpProject\Amp;
use Google\Web_Stories_Dependencies\AmpProject\Attribute;
use Google\Web_Stories_Dependencies\AmpProject\Dom\Document;
use Google\Web_Stories_Dependencies\AmpProject\Dom\Element;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\AmpRuntimeCssConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\TransformerConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\RemoteGetRequest;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer;
use Google\Web_Stories_Dependencies\AmpProject\RuntimeVersion;
use Google\Web_Stories_Dependencies\AmpProject\Tag;
use Exception;
/**
 * Transformer adding https://cdn.ampproject.org/v0.css if server-side-rendering is applied (known by the presence of
 * <style amp-runtime> tag). AMP runtime css (v0.css) will always be inlined as it'll get automatically updated to the
 * latest version once the AMP runtime has loaded.
 *
 * This is ported from the NodeJS optimizer while verifying against the Go version.
 *
 * NodeJS:
 * @version 6f465eb24b05acf74d39541151c17b8d8d97450d
 * @link    https://github.com/ampproject/amp-toolbox/blob/6f465eb24b05acf74d39541151c17b8d8d97450d/packages/optimizer/lib/transformers/AmpBoilerplateTransformer.js
 *
 * Go:
 * @version c9993b8ac4d17d1f05d3a1289956dadf3f9c370a
 * @link    https://github.com/ampproject/amppackager/blob/c9993b8ac4d17d1f05d3a1289956dadf3f9c370a/transformer/transformers/ampruntimecss.go
 *
 * @package ampproject/amp-toolbox
 */
final class AmpRuntimeCss implements \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer
{
    /**
     * XPath query to fetch the <style amp-runtime> element.
     *
     * @var string
     */
    const AMP_RUNTIME_STYLE_XPATH = './/style[ @amp-runtime ]';
    /**
     * Name of the boilerplate style file.
     *
     * @var string
     */
    const V0_CSS = 'v0.css';
    /**
     * URL of the boilerplate style file.
     *
     * @var string
     */
    const V0_CSS_URL = \Google\Web_Stories_Dependencies\AmpProject\Amp::CACHE_HOST . '/' . self::V0_CSS;
    /**
     * Configuration store to use.
     *
     * @var TransformerConfiguration
     */
    private $configuration;
    /**
     * Transport to use for remote requests.
     *
     * @var RemoteGetRequest
     */
    private $remoteRequest;
    /**
     * Instantiate an AmpRuntimeCss object.
     *
     * @param TransformerConfiguration $configuration Configuration store to use.
     * @param RemoteGetRequest         $remoteRequest Transport to use for remote requests.
     */
    public function __construct(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\TransformerConfiguration $configuration, \Google\Web_Stories_Dependencies\AmpProject\RemoteGetRequest $remoteRequest)
    {
        $this->configuration = $configuration;
        $this->remoteRequest = $remoteRequest;
    }
    /**
     * Apply transformations to the provided DOM document.
     *
     * @param Document        $document DOM document to apply the transformations to.
     * @param ErrorCollection $errors   Collection of errors that are collected during transformation.
     * @return void
     */
    public function transform(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection $errors)
    {
        $ampRuntimeStyle = $this->findAmpRuntimeStyle($document, $errors);
        if (!$ampRuntimeStyle) {
            return;
        }
        $this->addStaticCss($document, $ampRuntimeStyle, $errors);
    }
    /**
     * Find the <style amp-runtime> element.
     *
     * @param Document        $document Document to find the element in.
     * @param ErrorCollection $errors   Collection of errors that are collected during transformation.
     * @return Element|false DOM element for the <style amp-runtime> tag, or false if not found.
     */
    private function findAmpRuntimeStyle(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection $errors)
    {
        $ampRuntimeStyle = $document->xpath->query(self::AMP_RUNTIME_STYLE_XPATH, $document->head)->item(0);
        if (!$ampRuntimeStyle instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element) {
            $version = $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\AmpRuntimeCssConfiguration::VERSION);
            $errors->add(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error\CannotInlineRuntimeCss::fromMissingAmpRuntimeStyle($version));
            return \false;
        }
        return $ampRuntimeStyle;
    }
    /**
     * Add the static boilerplate CSS to the <style amp-runtime> element.
     *
     * @param Document        $document        Document to add the static CSS to.
     * @param Element         $ampRuntimeStyle DOM element for the <style amp-runtime> tag to add the static CSS to.
     * @param ErrorCollection $errors          Error collection to add errors to.
     */
    private function addStaticCss(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, \Google\Web_Stories_Dependencies\AmpProject\Dom\Element $ampRuntimeStyle, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection $errors)
    {
        $version = $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\AmpRuntimeCssConfiguration::VERSION);
        // We can always inline v0.css as the AMP runtime will take care of keeping v0.css in sync.
        try {
            $this->inlineCss($ampRuntimeStyle, $version);
        } catch (\Exception $exception) {
            $errors->add(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error\CannotInlineRuntimeCss::fromException($exception, $ampRuntimeStyle, $version));
            $this->linkCss($document, $ampRuntimeStyle);
            $ampRuntimeStyle->parentNode->removeChild($ampRuntimeStyle);
        }
    }
    /**
     * Insert the boilerplate style as inline CSS.
     *
     * @param Element $ampRuntimeStyle DOM element for the <style amp-runtime> tag to inline the CSS into.
     * @param string  $version         Version of the boilerplate style to use.
     */
    private function inlineCss(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $ampRuntimeStyle, $version)
    {
        // Use version passed in via params if available, otherwise fetch the current prod version.
        if (!empty($version)) {
            $v0CssUrl = \Google\Web_Stories_Dependencies\AmpProject\RuntimeVersion::appendRuntimeVersion(\Google\Web_Stories_Dependencies\AmpProject\Amp::CACHE_HOST, $version) . '/' . self::V0_CSS;
        } else {
            $v0CssUrl = self::V0_CSS_URL;
            $options = [\Google\Web_Stories_Dependencies\AmpProject\RuntimeVersion::OPTION_CANARY => $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\AmpRuntimeCssConfiguration::CANARY)];
            $version = (new \Google\Web_Stories_Dependencies\AmpProject\RuntimeVersion($this->remoteRequest))->currentVersion($options);
        }
        $ampRuntimeStyle->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::I_AMPHTML_VERSION, $version);
        $styles = $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\AmpRuntimeCssConfiguration::STYLES);
        if (empty($styles)) {
            $response = $this->remoteRequest->get($v0CssUrl);
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                return;
            }
            $styles = $response->getBody();
        }
        $ampRuntimeStyle->textContent = $styles;
    }
    /**
     * Insert the boilerplate style as inline CSS.
     *
     * @param Document $document        Document to link the CSS in.
     * @param Element  $ampRuntimeStyle DOM element for the <style amp-runtime> tag to inline the CSS into.
     */
    private function linkCss(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, \Google\Web_Stories_Dependencies\AmpProject\Dom\Element $ampRuntimeStyle)
    {
        $cssStyleNode = $document->createElement(\Google\Web_Stories_Dependencies\AmpProject\Tag::LINK);
        $cssStyleNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::REL, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_STYLESHEET);
        $cssStyleNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF, self::V0_CSS_URL);
        $ampRuntimeStyle->parentNode->insertBefore($cssStyleNode, $ampRuntimeStyle);
    }
}
