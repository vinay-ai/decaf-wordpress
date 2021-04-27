<?php

namespace Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer;

use Google\Web_Stories_Dependencies\AmpProject\Amp;
use Google\Web_Stories_Dependencies\AmpProject\Attribute;
use Google\Web_Stories_Dependencies\AmpProject\Dom\Document;
use Google\Web_Stories_Dependencies\AmpProject\Dom\Element;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error\CannotAdaptDocumentForSelfHosting;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Exception\InvalidConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\TransformerConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\RuntimeVersion;
use Google\Web_Stories_Dependencies\AmpProject\Tag;
use Google\Web_Stories_Dependencies\AmpProject\Url;
use DOMNode;
use Exception;
/**
 * RewriteAmpUrls - rewrites AMP runtime URLs.
 *
 * This transformer supports five parameters:
 *
 * * `ampRuntimeVersion`: specifies a
 *   [specific version](https://github.com/ampproject/amp-toolbox/tree/main/runtime-version)
 *   version of the AMP runtime. For example: `ampRuntimeVersion: "001515617716922"` will result in AMP runtime URLs
 *   being re-written from `https://cdn.ampproject.org/v0.js` to `https://cdn.ampproject.org/rtv/001515617716922/v0.js`.
 *
 * * `ampUrlPrefix`: specifies an URL prefix for AMP runtime URLs. For example: `ampUrlPrefix: "/amp"` will result in
 *   AMP runtime URLs being re-written from `https://cdn.ampproject.org/v0.js` to `/amp/v0.js`. This option is
 *   experimental and not recommended.
 *
 * * `geoApiUrl`: specifies amp-geo API URL to use as a fallback when `amp-geo-0.1.js` is served unpatched, i.e. when
 *   `{{AMP_ISO_COUNTRY_HOTPATCH}}` is not replaced dynamically.
 *
 * * `lts`: Use long-term stable URLs. This option is not compatible with `rtv`, `ampRuntimeVersion` or `ampUrlPrefix`;
 *   an error will be thrown if these options are included together. Similarly, the `geoApiUrl` option is ineffective
 *   with the `lts` flag, but will simply be ignored rather than throwing an error.
 *
 * * `rtv`: Append the runtime version to the rewritten URLs. This option is not compatible with `lts`.
 *
 * * `esmModulesEnabled`: Use ES modules for loading the AMP runtime and components. Defaults to true.
 *
 * All parameters are optional. If no option is provided, runtime URLs won't be re-written. You can combine
 * `ampRuntimeVersion` and  `ampUrlPrefix` to rewrite AMP runtime URLs to versioned URLs on a different origin.
 *
 * This transformer also adds a preload header for the AMP runtime (v0.js) to trigger HTTP/2 push for CDNs (see
 * https://www.w3.org/TR/preload/#server-push-(http/2)).
 *
 * This is ported from the NodeJS optimizer while verifying against the Go version.
 *
 * NodeJS:
 * @version 7fbf187b3c7f07100e8911a52582b640b23490e5
 * @link https://github.com/ampproject/amp-toolbox/blob/7fbf187b3c7f07100e8911a52582b640b23490e5/packages/optimizer/lib/transformers/RewriteAmpUrls.js
 *
 * @package ampproject/amp-toolbox
 */
final class RewriteAmpUrls implements \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer
{
    /**
     * Configuration to use.
     *
     * @var TransformerConfiguration
     */
    private $configuration;
    /**
     * RewriteAmpUrls constructor.
     *
     * @param TransformerConfiguration $configuration Configuration to use.
     */
    public function __construct(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\TransformerConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }
    /**
     * Apply transformations to the provided DOM document.
     *
     * @param Document        $document DOM document to apply the
     *                                  transformations to.
     * @param ErrorCollection $errors   Collection of errors that are collected
     *                                  during transformation.
     * @return void
     */
    public function transform(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection $errors)
    {
        $host = $this->calculateHost();
        $referenceNode = $document->viewport;
        $preloadNodes = \array_filter($this->collectPreloadNodes($document, $host));
        foreach ($preloadNodes as $preloadNode) {
            $document->head->insertBefore($preloadNode, $referenceNode instanceof \DOMNode ? $referenceNode->nextSibling : null);
            $referenceNode = $preloadNode;
        }
        $this->adaptForSelfHosting($document, $host, $errors);
    }
    /**
     * Collect all the preload nodes to be added to the <head>.
     *
     * @param Document $document Document to collect preload nodes for.
     * @param string   $host     Host URL to use.
     * @return Element[] Preload nodes.
     */
    private function collectPreloadNodes(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, $host)
    {
        $usesEsm = $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::ESM_MODULES_ENABLED);
        $preloadNodes = [];
        $node = $document->head->firstChild;
        while ($node) {
            if (!$node instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element) {
                $node = $node->nextSibling;
                continue;
            }
            $src = $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRC);
            $href = $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF);
            if ($node->tagName === \Google\Web_Stories_Dependencies\AmpProject\Tag::SCRIPT && $this->usesAmpCacheUrl($src)) {
                $newUrl = $this->replaceUrl($src, $host);
                $node->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRC, $this->replaceUrl($src, $host));
                if ($usesEsm) {
                    $preloadNodes[] = $this->addEsm($document, $node);
                } else {
                    $preloadNodes[] = $this->createPreload($document, $newUrl, \Google\Web_Stories_Dependencies\AmpProject\Tag::SCRIPT);
                }
            } elseif ($node->tagName === \Google\Web_Stories_Dependencies\AmpProject\Tag::LINK && $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::REL) === \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_STYLESHEET && $this->usesAmpCacheUrl($href)) {
                $newUrl = $this->replaceUrl($href, $host);
                $node->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF, $newUrl);
                $preloadNodes[] = $this->createPreload($document, $newUrl, \Google\Web_Stories_Dependencies\AmpProject\Tag::STYLE);
            } elseif ($node->tagName === \Google\Web_Stories_Dependencies\AmpProject\Tag::LINK && $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::REL) === \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_PRELOAD && $this->usesAmpCacheUrl($href)) {
                if ($usesEsm && $this->shouldPreload($href)) {
                    // Only preload .mjs runtime in ESM mode.
                    $node->parentNode->removeChild($node);
                } else {
                    $node->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF, $this->replaceUrl($href, $host));
                }
            }
            $node = $node->nextSibling;
        }
        return $preloadNodes;
    }
    /**
     * Check if a given URL uses the AMP Cache.
     *
     * @param string $url Url to check.
     * @return bool Whether the provided URL uses the AMP Cache.
     */
    private function usesAmpCacheUrl($url)
    {
        if (!$url) {
            return \false;
        }
        return \strpos($url, \Google\Web_Stories_Dependencies\AmpProject\Amp::CACHE_HOST) === 0;
    }
    /**
     * Replace URL root with provided host.
     *
     * @param string $url  Url to replace.
     * @param string $host Host to use.
     * @return string Adapted URL.
     */
    private function replaceUrl($url, $host)
    {
        return \str_replace(\Google\Web_Stories_Dependencies\AmpProject\Amp::CACHE_HOST, $host, $url);
    }
    /**
     * Replace <script> elements with their ES module counterparts.
     *
     * @param Document $document   Document to add the ES module scripts to.
     * @param Element  $scriptNode Script element to replace.
     * @return Element|null Preload element that was added, or null if none was required or an error occurred.
     */
    private function addEsm(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, \Google\Web_Stories_Dependencies\AmpProject\Dom\Element $scriptNode)
    {
        $preloadNode = null;
        $scriptUrl = $scriptNode->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRC);
        $esmScriptUrl = \preg_replace('/\\.js$/', '.mjs', $scriptUrl);
        if ($this->shouldPreload($scriptUrl)) {
            $preloadNode = $document->createElement(\Google\Web_Stories_Dependencies\AmpProject\Tag::LINK);
            $preloadNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::AS_, \Google\Web_Stories_Dependencies\AmpProject\Tag::SCRIPT);
            $preloadNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CROSSORIGIN, \Google\Web_Stories_Dependencies\AmpProject\Attribute::CROSSORIGIN_ANONYMOUS);
            $preloadNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF, $esmScriptUrl);
            $preloadNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::REL, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_MODULEPRELOAD);
        }
        $nomoduleNode = $document->createElement(\Google\Web_Stories_Dependencies\AmpProject\Tag::SCRIPT);
        $nomoduleNode->addBooleanAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::ASYNC);
        $nomoduleNode->addBooleanAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::NOMODULE);
        $nomoduleNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRC, $scriptUrl);
        $nomoduleNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CROSSORIGIN, \Google\Web_Stories_Dependencies\AmpProject\Attribute::CROSSORIGIN_ANONYMOUS);
        $scriptNode->copyAttributes([\Google\Web_Stories_Dependencies\AmpProject\Attribute::CUSTOM_ELEMENT, \Google\Web_Stories_Dependencies\AmpProject\Attribute::CUSTOM_TEMPLATE], $nomoduleNode);
        $scriptNode->parentNode->insertBefore($nomoduleNode, $scriptNode);
        $scriptNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::TYPE, \Google\Web_Stories_Dependencies\AmpProject\Attribute::TYPE_MODULE);
        // Without crossorigin=anonymous browser loads the script twice because
        // of preload.
        $scriptNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CROSSORIGIN, \Google\Web_Stories_Dependencies\AmpProject\Attribute::CROSSORIGIN_ANONYMOUS);
        $scriptNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRC, $esmScriptUrl);
        return $preloadNode instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element ? $preloadNode : null;
    }
    /**
     * Create a preload element to add to the head.
     *
     * @param Document $document Document to create the element in.
     * @param string   $href     Href to use for the preload.
     * @param string   $type     Type to use for the preload.
     * @return Element|null Preload element, or null if not created.
     */
    private function createPreload(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, $href, $type)
    {
        if (!$this->shouldPreload($href)) {
            return null;
        }
        $preloadNode = $document->createElement(\Google\Web_Stories_Dependencies\AmpProject\Tag::LINK);
        $preloadNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::REL, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_PRELOAD);
        $preloadNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF, $href);
        $preloadNode->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::AS_, $type);
        return $preloadNode;
    }
    /**
     * Add meta tags as needed to adapt for self-hosting the AMP runtime.
     *
     * @param Document        $document Document to add the meta tags to.
     * @param string          $host     Host URL to use.
     * @param ErrorCollection $errors   Error collection to add potential errors to.
     */
    private function adaptForSelfHosting(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, $host, $errors)
    {
        // runtime-host and amp-geo-api meta tags should appear before the first script.
        if (!$this->usesAmpCacheUrl($host) && !$this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::LTS)) {
            try {
                $url = new \Google\Web_Stories_Dependencies\AmpProject\Url($host);
                if (!empty($url->scheme) && !empty($url->host)) {
                    $origin = "{$url->scheme}://{$url->host}";
                    $this->addMeta($document, 'runtime-host', $origin);
                } else {
                    $errors->add(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error\CannotAdaptDocumentForSelfHosting::forNonAbsoluteUrl($host));
                }
            } catch (\Exception $exception) {
                $errors->add(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error\CannotAdaptDocumentForSelfHosting::fromException($exception));
            }
        }
        if (!empty($this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::GEO_API_URL)) && !$this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::LTS)) {
            $this->addMeta($document, 'amp-geo-api', $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::GEO_API_URL));
        }
    }
    /**
     * Check whether a given URL should be preloaded.
     *
     * @param string $url Url to check.
     * @return bool Whether the provided URL should be preloaded.
     */
    private function shouldPreload($url)
    {
        return \substr_compare($url, 'v0.js', -5) === 0 || \substr_compare($url, 'v0.css', -6) === 0;
    }
    /**
     * Calculate the host string to use.
     *
     * @return string Host to use.
     */
    private function calculateHost()
    {
        $lts = $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::LTS);
        $rtv = $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::RTV);
        if ($lts && $rtv) {
            throw \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Exception\InvalidConfiguration::forMutuallyExclusiveFlags(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::LTS, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::RTV);
        }
        $ampUrlPrefix = $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::AMP_URL_PREFIX);
        $ampRuntimeVersion = $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\RewriteAmpUrlsConfiguration::AMP_RUNTIME_VERSION);
        $ampUrlPrefix = \rtrim($ampUrlPrefix, '/');
        if ($ampRuntimeVersion && $rtv) {
            $ampUrlPrefix = \Google\Web_Stories_Dependencies\AmpProject\RuntimeVersion::appendRuntimeVersion($ampUrlPrefix, $ampRuntimeVersion);
        } elseif ($lts) {
            $ampUrlPrefix .= '/lts';
        }
        return $ampUrlPrefix;
    }
    /**
     * Add meta element to the document head.
     *
     * @param Document $document Document to add the meta to.
     * @param string   $name     Name of the meta element.
     * @param string   $content  Value of the meta element.
     */
    private function addMeta(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, $name, $content)
    {
        $meta = $document->createElement(\Google\Web_Stories_Dependencies\AmpProject\Tag::META);
        $meta->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::NAME, $name);
        $meta->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CONTENT, $content);
        $firstScript = $document->xpath->query('./script', $document->head)->item(0);
        $document->head->insertBefore($meta, $firstScript);
    }
}
