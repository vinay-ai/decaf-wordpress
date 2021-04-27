<?php

namespace Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer;

use Google\Web_Stories_Dependencies\AmpProject\Amp;
use Google\Web_Stories_Dependencies\AmpProject\Attribute;
use Google\Web_Stories_Dependencies\AmpProject\Dom\Document;
use Google\Web_Stories_Dependencies\AmpProject\Dom\Element;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer;
use Google\Web_Stories_Dependencies\AmpProject\Tag;
use DOMNode;
use DOMNodeList;
/**
 * Transformer applying the head reordering transformations to the HTML input.
 *
 * ReorderHead reorders the children of <head>. Specifically, it
 * orders the <head> like so:
 * (0) <meta charset> tag
 * (1) <style amp-runtime> (inserted by AmpRuntimeCss transformer)
 * (2) remaining <meta> tags (those other than <meta charset>)
 * (3) AMP runtime .js <script> tag
 * (4) AMP viewer runtime .js <script>
 * (5) <script> tags that are render delaying
 * (6) <script> tags for remaining extensions
 * (7) <link> tag for favicons
 * (8) <link> tag for resource hints
 * (9) <link rel=stylesheet> tags before <style amp-custom>
 * (10) <style amp-custom>
 * (11) any other tags allowed in <head>
 * (12) AMP boilerplate (first style amp-boilerplate, then noscript)
 *
 * This is ported from the NodeJS optimizer while verifying against the Go version.
 *
 * NodeJS:
 * @version c92d6023ea4c9edadff593742a992da2b400a75d
 * @link    https://github.com/ampproject/amp-toolbox/blob/c92d6023ea4c9edadff593742a992da2b400a75d/packages/optimizer/lib/transformers/ReorderHeadTransformer.js
 *
 * Go:
 * @version ea0959046c179953de43077eafaeb720f9b20bdf
 * @link    https://github.com/ampproject/amppackager/blob/ea0959046c179953de43077eafaeb720f9b20bdf/transformer/transformers/reorderhead.go
 *
 * @package ampproject/amp-toolbox
 */
final class ReorderHead implements \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer
{
    /**
     * Regular expression pattern to match resource hints pointing to an AMP resource.
     */
    const AMP_RESOURCE_HINT_SRC_PATTERN = '#(^|[\\b/])cdn\\.ampproject\\.org($|[\\b/])#i';
    /*
     * Different categories of <head> tags to track and reorder.
     */
    private $ampResourceHints = [];
    private $linkIcons = [];
    private $linkStyleAmpRuntime = null;
    private $linkStylesheetsBeforeAmpCustom = [];
    private $metaCharset = null;
    private $metaViewport = null;
    private $metaOther = [];
    private $noscript = null;
    private $others = [];
    private $resourceHintLinks = [];
    private $scriptAmpRuntime = [];
    private $scriptAmpViewer = [];
    private $scriptNonRenderDelayingExtensions = [];
    private $scriptRenderDelayingExtensions = [];
    private $styleAmpBoilerplate = null;
    private $styleAmpCustom = null;
    private $styleAmpRuntime = null;
    /**
     * Apply transformations to the provided DOM document.
     *
     * @param Document        $document DOM document to apply the transformations to.
     * @param ErrorCollection $errors   Collection of errors that are collected during transformation.
     * @return void
     */
    public function transform(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection $errors)
    {
        $nodes = $document->head->childNodes;
        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            return;
        }
        while ($document->head->hasChildNodes()) {
            $node = $document->head->removeChild($document->head->firstChild);
            $this->registerNode($node);
        }
        $this->appendToHead($document);
    }
    /**
     * Register a given node in the appropriate category.
     *
     * @param DOMNode $node Node to register.
     */
    private function registerNode(\DOMNode $node)
    {
        if (!$node instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element) {
            if ($node->nodeType === \XML_TEXT_NODE) {
                $nodeContent = \trim($node->textContent);
                if (empty($nodeContent)) {
                    return;
                }
            }
            $this->others[] = $node;
            return;
        }
        switch ($node->tagName) {
            case \Google\Web_Stories_Dependencies\AmpProject\Tag::META:
                $this->registerMeta($node);
                break;
            case \Google\Web_Stories_Dependencies\AmpProject\Tag::SCRIPT:
                $this->registerScript($node);
                break;
            case \Google\Web_Stories_Dependencies\AmpProject\Tag::STYLE:
                $this->registerStyle($node);
                break;
            case \Google\Web_Stories_Dependencies\AmpProject\Tag::LINK:
                $this->registerLink($node);
                break;
            case \Google\Web_Stories_Dependencies\AmpProject\Tag::NOSCRIPT:
                $this->noscript = $node;
                // @todo Make this an array.
                break;
            default:
                $this->others[] = $node;
        }
    }
    /**
     * Register a <meta> node.
     *
     * @param Element $node Node to register.
     */
    private function registerMeta(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $node)
    {
        if ($node->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CHARSET)) {
            $this->metaCharset = $node;
            return;
        }
        if ($node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::NAME) === \Google\Web_Stories_Dependencies\AmpProject\Attribute::VIEWPORT) {
            $this->metaViewport = $node;
            return;
        }
        $this->metaOther[] = $node;
    }
    /**
     * Register a <script> node.
     *
     * @param Element $node Node to register.
     */
    private function registerScript(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $node)
    {
        $nomodule = (int) $node->hasAttribute('nomodule');
        if (\Google\Web_Stories_Dependencies\AmpProject\Amp::isRuntimeScript($node)) {
            $this->scriptAmpRuntime[$nomodule] = $node;
            return;
        }
        if (\Google\Web_Stories_Dependencies\AmpProject\Amp::isViewerScript($node)) {
            $this->scriptAmpViewer[$nomodule] = $node;
            return;
        }
        if ($node->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CUSTOM_ELEMENT)) {
            $name = $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CUSTOM_ELEMENT);
            if (\Google\Web_Stories_Dependencies\AmpProject\Amp::isRenderDelayingExtension($node)) {
                $this->scriptRenderDelayingExtensions[$name][$nomodule] = $node;
                return;
            }
            $this->scriptNonRenderDelayingExtensions[$name][$nomodule] = $node;
            return;
        }
        if ($node->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CUSTOM_TEMPLATE)) {
            $name = $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CUSTOM_TEMPLATE);
            $this->scriptNonRenderDelayingExtensions[$name][$nomodule] = $node;
            return;
        }
        if ($node->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HOST_SERVICE)) {
            $name = $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HOST_SERVICE);
            $this->scriptNonRenderDelayingExtensions[$name][$nomodule] = $node;
            return;
        }
        $this->others[] = $node;
    }
    /**
     * Register a <style> node.
     *
     * @param Element $node Node to register.
     */
    private function registerStyle(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $node)
    {
        if ($node->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::AMP_RUNTIME)) {
            $this->styleAmpRuntime = $node;
            return;
        }
        if ($node->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::AMP_CUSTOM)) {
            $this->styleAmpCustom = $node;
            return;
        }
        if ($node->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::AMP_BOILERPLATE) || $node->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::AMP4ADS_BOILERPLATE)) {
            $this->styleAmpBoilerplate = $node;
            return;
        }
        $this->others[] = $node;
    }
    /**
     * Register a <link> node.
     *
     * @param Element $node Node to register.
     */
    private function registerLink(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $node)
    {
        $rel = $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::REL);
        if ($this->containsWord($rel, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_STYLESHEET)) {
            $href = $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF);
            if ($href && \substr($href, -7) === '/v0.css') {
                $this->linkStyleAmpRuntime = $node;
                return;
            }
            if (!$this->styleAmpCustom) {
                // We haven't seen amp-custom yet.
                $this->linkStylesheetsBeforeAmpCustom[] = $node;
                return;
            }
        }
        if ($this->containsWord($rel, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_ICON)) {
            $this->linkIcons[] = $node;
            return;
        }
        if ($this->containsWord($rel, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_PRELOAD) || $this->containsWord($rel, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_PREFETCH) || $this->containsWord($rel, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_DNS_PREFETCH) || $this->containsWord($rel, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_PRECONNECT) || $this->containsWord($rel, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_MODULEPRELOAD)) {
            if ($this->isHintForAmp($node)) {
                $this->ampResourceHints[] = $node;
            } else {
                $this->resourceHintLinks[] = $node;
            }
            return;
        }
        $this->others[] = $node;
    }
    /**
     * Append all registered nodes to the <head> node.
     *
     * @param Document $document Document to append the nodes to.
     */
    private function appendToHead(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document)
    {
        $categories = ['metaCharset', 'metaViewport', 'ampResourceHints', 'linkStyleAmpRuntime', 'styleAmpRuntime', 'metaOther', 'resourceHintLinks', 'scriptAmpRuntime', 'scriptAmpViewer', 'scriptRenderDelayingExtensions', 'scriptNonRenderDelayingExtensions', 'linkIcons', 'linkStylesheetsBeforeAmpCustom', 'styleAmpCustom', 'others', 'styleAmpBoilerplate', 'noscript'];
        foreach ($categories as $category) {
            if ($this->{$category} === null) {
                continue;
            }
            if ($this->{$category} instanceof \DOMNode) {
                $node = $document->importNode($this->{$category});
                $document->head->appendChild($node);
            } elseif (\is_array($this->{$category})) {
                $this->recursiveKeySort($this->{$category});
                \array_walk_recursive($this->{$category}, static function (\DOMNode $node) use($document) {
                    $node = $document->importNode($node);
                    $document->head->appendChild($node);
                });
            }
        }
    }
    /**
     * Sort array keys recursively.
     *
     * @param array|mixed $item Item.
     */
    private function recursiveKeySort(&$item)
    {
        if (\is_array($item)) {
            \ksort($item);
            \array_walk($item, [$this, 'recursiveKeySort']);
        }
    }
    /**
     * Check if a given string contains another string, respecting word boundaries..
     *
     * @param string $haystack Haystack string to look in.
     * @param string $needle   Needle string to search for.
     * @return bool Whether the needle was found in the haystack.
     */
    private function containsWord($haystack, $needle)
    {
        if (empty($haystack) || empty($needle)) {
            return \false;
        }
        return \preg_match('/(^|\\s)' . \preg_quote($needle, '/') . '(\\s|$)/i', $haystack);
    }
    /**
     * Check whether a given resource hint link element is pointing to an AMP resource.
     *
     * @param Element $node Link element to check.
     * @return bool Whether the link element is pointing to an AMP resource.
     */
    private function isHintForAmp(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $node)
    {
        $href = $node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF);
        if (empty($href)) {
            return \false;
        }
        return (bool) \preg_match(self::AMP_RESOURCE_HINT_SRC_PATTERN, $href);
    }
}
