<?php

namespace Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer;

use Google\Web_Stories_Dependencies\AmpProject\Amp;
use Google\Web_Stories_Dependencies\AmpProject\Attribute;
use Google\Web_Stories_Dependencies\AmpProject\Dom\Document;
use Google\Web_Stories_Dependencies\AmpProject\Dom\Element;
use Google\Web_Stories_Dependencies\AmpProject\Exception\FailedToParseUrl;
use Google\Web_Stories_Dependencies\AmpProject\Extension;
use Google\Web_Stories_Dependencies\AmpProject\Layout;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\PreloadHeroImageConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\HeroImage;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\ImageDimensions;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\TransformerConfiguration;
use Google\Web_Stories_Dependencies\AmpProject\RequestDestination;
use Google\Web_Stories_Dependencies\AmpProject\Tag;
use Google\Web_Stories_Dependencies\AmpProject\Url;
use DOMNode;
/**
 * PreloadHeroImage - this transformer optimizes image rendering times for hero images. For hero images it will:
 *
 * 1. Inject a preload hint (if possible)
 * 2. Generate an img tag enabling the browser to render the image without the AMP runtime being loaded.
 *
 * Hero images are either identified automatically or can be explicitly defined by adding an `data-hero` attribute to
 * the element.
 *
 * This transformer supports the following options:
 *
 * * `preloadHeroImage`: [true|false] - enables or disables hero image preloading. The default is `true`.
 *
 * This is ported from the NodeJS optimizer.
 *
 * @version 3429af9d91e2c9efe1af85757499e5a308755f5f
 * @link    https://github.com/ampproject/amp-toolbox/blob/3429af9d91e2c9efe1af85757499e5a308755f5f/packages/optimizer/lib/transformers/PreloadHeroImage.js
 *
 * @package ampproject/amp-toolbox
 */
final class PreloadHeroImage implements \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Transformer
{
    /**
     * Class(es) to apply to a serverside-rendered image element.
     *
     * @var string
     */
    const SSR_IMAGE_CLASS = 'i-amphtml-fill-content i-amphtml-replaced-content';
    /**
     * List of attributes to copy onto an SSR'ed image.
     *
     * @var string[]
     */
    const ATTRIBUTES_TO_COPY = [\Google\Web_Stories_Dependencies\AmpProject\Attribute::ALT, \Google\Web_Stories_Dependencies\AmpProject\Attribute::ATTRIBUTION, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REFERRERPOLICY, \Google\Web_Stories_Dependencies\AmpProject\Attribute::SRC, \Google\Web_Stories_Dependencies\AmpProject\Attribute::SRCSET, \Google\Web_Stories_Dependencies\AmpProject\Attribute::SIZES, \Google\Web_Stories_Dependencies\AmpProject\Attribute::TITLE];
    /**
     * List of attributes to inline onto an SSR'ed image.
     *
     * @var string[]
     */
    const ATTRIBUTES_TO_INLINE = [\Google\Web_Stories_Dependencies\AmpProject\Attribute::OBJECT_FIT, \Google\Web_Stories_Dependencies\AmpProject\Attribute::OBJECT_POSITION];
    /**
     * Maximum number of hero images defined via data-hero attribute.
     *
     * @var int
     */
    const DATA_HERO_MAX = 2;
    /**
     * List of AMP elements that are an embed that can have a placeholder.
     *
     * The array has values assigned so that we can do a fast hash lookup on the element name.
     *
     * @var bool[]
     */
    const AMP_EMBEDS = [\Google\Web_Stories_Dependencies\AmpProject\Extension::AD => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::ANIM => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::BRIGHTCOVE => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::DAILYMOTION => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::FACEBOOK => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::GFYCAT => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::IFRAME => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::IMGUR => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::INSTAGRAM => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::PINTEREST => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::REDDIT => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::TWITTER => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::VIDEO => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::VIDEO_IFRAME => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::VIMEO => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::WISTIA_PLAYER => \true, \Google\Web_Stories_Dependencies\AmpProject\Extension::YOUTUBE => \true];
    /**
     * XPath query to relatively fetch all noscript > img elements.
     *
     * @var string
     */
    const NOSCRIPT_IMG_XPATH_QUERY = './/noscript[ img ]';
    /**
     * Regular expression pattern to extract the URL from a CSS background-image property.
     *
     * @var string
     */
    const CSS_BACKGROUND_IMAGE_URL_REGEX_PATTERN = '/background-image\\s*:\\s*url\\(\\s*(?<url>[^)]*\\s*)/i';
    /**
     * Configuration store to use.
     *
     * @var TransformerConfiguration
     */
    private $configuration;
    /**
     * Reference node to attach preload links to.
     *
     * @var Element|null
     */
    private $preloadReferenceNode;
    /**
     * Inline style backup attribute that stores inline styles that are being moved to <style amp-custom>.
     *
     * An empty string signifies that no inline style backup is available.
     *
     * @var string
     */
    private $inlineStyleBackupAttribute;
    /**
     * Instantiate a PreloadHeroImage object.
     *
     * @param TransformerConfiguration $configuration Configuration store to use.
     */
    public function __construct(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\TransformerConfiguration $configuration)
    {
        $this->configuration = $configuration;
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
        if ($this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\PreloadHeroImageConfiguration::PRELOAD_HERO_IMAGE) === \false) {
            return;
        }
        $this->inlineStyleBackupAttribute = $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\PreloadHeroImageConfiguration::INLINE_STYLE_BACKUP_ATTRIBUTE);
        $heroImages = $this->findHeroImages($document);
        $heroImageCount = \count($heroImages);
        if ($heroImageCount > self::DATA_HERO_MAX) {
            $errors->add(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error\TooManyHeroImages::whenPastMaximum());
            $heroImageCount = self::DATA_HERO_MAX;
        }
        for ($index = 0; $index < $heroImageCount; $index++) {
            $this->removeLazyLoading($heroImages[$index]);
            $this->generatePreload($heroImages[$index], $document, $errors);
            $this->generateImg($heroImages[$index], $document);
        }
    }
    /**
     * Find the hero images to optimize.
     *
     * @param Document $document Document to look for hero images in.
     * @return HeroImage[] Array of hero images to optimize.
     */
    private function findHeroImages(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document)
    {
        $heroImages = [];
        $heroImageCandidates = [];
        $heroImageFallbacks = [];
        $previousHeroImageFallback = null;
        $seenParagraphCount = 0;
        $node = $document->body;
        while ($node !== null) {
            if (!$node instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element) {
                $node = $this->nextNode($node);
                continue;
            }
            if ($node->tagName === \Google\Web_Stories_Dependencies\AmpProject\Tag::P) {
                $seenParagraphCount++;
            }
            $heroImage = $this->detectImageWithAttribute($node, \Google\Web_Stories_Dependencies\AmpProject\Attribute::DATA_HERO);
            if ($heroImage) {
                $heroImages[] = $heroImage;
            } elseif ($seenParagraphCount < 2 && \count($heroImageCandidates) < self::DATA_HERO_MAX) {
                $heroImageCandidate = $this->detectImageWithAttribute($node, \Google\Web_Stories_Dependencies\AmpProject\Attribute::DATA_HERO_CANDIDATE);
                if ($heroImageCandidate) {
                    $heroImageCandidates[] = $heroImageCandidate;
                } elseif (\count($heroImageFallbacks) < self::DATA_HERO_MAX) {
                    $heroImageFallback = $this->detectPossibleHeroImageFallbacks($node);
                    // Ensure we don't flag the same image twice. This can happen for placeholder images, which are
                    // flagged on their own and as their parent's placeholder.
                    if ($heroImageFallback && (!$previousHeroImageFallback || $heroImageFallback->getAmpImg() !== $previousHeroImageFallback->getAmpImg())) {
                        $heroImageFallbacks[] = $heroImageFallback;
                        $previousHeroImageFallback = $heroImageFallback;
                    }
                }
            }
            if (\Google\Web_Stories_Dependencies\AmpProject\Amp::isTemplate($node)) {
                // Ignore images inside templates.
                $node = $this->skipNodeAndChildren($node);
            } else {
                $node = $this->nextNode($node);
            }
        }
        if (\count($heroImages) > 0) {
            return $heroImages;
        }
        while (\count($heroImages) < self::DATA_HERO_MAX && \count($heroImageCandidates) > 0) {
            $heroImages[] = \array_shift($heroImageCandidates);
        }
        if (\count($heroImages) < 1 && \count($heroImageFallbacks) > 0) {
            $heroImages[] = \array_shift($heroImageFallbacks);
        }
        return $heroImages;
    }
    /**
     * Detect a hero image with a specific attribute.
     *
     * This is used for detecting an image marked with data-hero or data-hero-candidate
     *
     * @param Element $element   Element to detect for.
     * @param string  $attribute Attribute to look for.
     * @return HeroImage|null Detected hero image, or null if none detected.
     * @throws FailedToParseUrl Exception when the URL or Base URL is malformed.
     */
    private function detectImageWithAttribute(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element, $attribute)
    {
        if (!$element->hasAttribute($attribute)) {
            return null;
        }
        $src = $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRC);
        if ($element->tagName === \Google\Web_Stories_Dependencies\AmpProject\Extension::IMG && (new \Google\Web_Stories_Dependencies\AmpProject\Url($src))->isValidNonDataUrl()) {
            return new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\HeroImage($src, $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::MEDIA), $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRCSET), $element);
        }
        if ($this->isAmpEmbed($element)) {
            $placeholderImage = $this->getPlaceholderImage($element);
            if (null !== $placeholderImage) {
                return $placeholderImage;
            }
        }
        $cssBackgroundImage = $this->getCssBackgroundImageUrl($element);
        if ((new \Google\Web_Stories_Dependencies\AmpProject\Url($cssBackgroundImage))->isValidNonDataUrl()) {
            return new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\HeroImage($cssBackgroundImage, $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::MEDIA), $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRCSET), $element);
        }
        return null;
    }
    /**
     * Detect a possible hero image fallback.
     *
     * The hero image here can come from one of <amp-img>, <amp-video>, <amp-iframe>, <amp-video-iframe>.
     *
     * @param Element $element Element to detect for.
     * @return HeroImage|null Detected hero image fallback, or null if none detected.
     */
    private function detectPossibleHeroImageFallbacks(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        if ($element->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::LAYOUT) && $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::LAYOUT) === \Google\Web_Stories_Dependencies\AmpProject\Layout::NODISPLAY) {
            return null;
        }
        if ($element->tagName === \Google\Web_Stories_Dependencies\AmpProject\Extension::IMG || $element->tagName === \Google\Web_Stories_Dependencies\AmpProject\Tag::IMG) {
            return $this->detectPossibleHeroImageFallbackForAmpImg($element);
        }
        if ($element->tagName === \Google\Web_Stories_Dependencies\AmpProject\Extension::VIDEO) {
            return $this->detectPossibleHeroImageFallbackForPosterImage($element);
        }
        if ($this->isAmpEmbed($element)) {
            return $this->detectPossibleHeroImageFallbackForPlaceholderImage($element);
        }
        return null;
    }
    /**
     * Detect a possible hero image fallback from an <amp-img> element.
     *
     * @param Element $element Element to detect for.
     * @return HeroImage|null Detected hero image fallback, or null if none detected.
     */
    private function detectPossibleHeroImageFallbackForAmpImg(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        $src = $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRC);
        if (empty($src)) {
            return null;
        }
        if (!(new \Google\Web_Stories_Dependencies\AmpProject\Url($src))->isValidNonDataUrl()) {
            return null;
        }
        if ((new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ImageDimensions($element))->isTiny()) {
            return null;
        }
        $srcset = $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRCSET);
        $media = $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::MEDIA);
        return new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\HeroImage($src, $media, $srcset, $element);
    }
    /**
     * Detect a possible hero image fallback from a video's poster (= placeholder) image.
     *
     * @param Element $element Element to detect for.
     * @return HeroImage|null Detected hero image fallback, or null if none detected.
     */
    private function detectPossibleHeroImageFallbackForPosterImage(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        $poster = $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::POSTER);
        if (!$poster) {
            return null;
        }
        if (!(new \Google\Web_Stories_Dependencies\AmpProject\Url($poster))->isValidNonDataUrl()) {
            return null;
        }
        if ((new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ImageDimensions($element))->isTiny()) {
            return null;
        }
        $media = $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::MEDIA);
        return new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\HeroImage($poster, $media, '');
    }
    /**
     * Detect a possible hero image fallback from a placeholder image.
     *
     * @param Element $element Element to detect for.
     * @return HeroImage|null Detected hero image fallback, or null if none detected.
     */
    private function detectPossibleHeroImageFallbackForPlaceholderImage(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        // The placeholder will be a child node of the element.
        if (!$element->hasChildNodes()) {
            return null;
        }
        // Don't bother if the element is too small.
        if ((new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ImageDimensions($element))->isTiny()) {
            return null;
        }
        return $this->getPlaceholderImage($element);
    }
    /**
     * Get the placeholder image for a given element.
     *
     * @param Element $element Element to check the placeholder image for.
     * @return HeroImage|null Placeholder image to use or null if none found.
     */
    private function getPlaceholderImage(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        foreach ($element->childNodes as $childNode) {
            if (!$childNode instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element || !$childNode->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::PLACEHOLDER)) {
                continue;
            }
            $placeholder = $childNode;
            while ($placeholder !== null) {
                if (!$placeholder instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element) {
                    $placeholder = $this->nextNode($placeholder);
                    continue;
                }
                if ($placeholder->tagName === \Google\Web_Stories_Dependencies\AmpProject\Extension::IMG || $placeholder->tagName === \Google\Web_Stories_Dependencies\AmpProject\Tag::IMG) {
                    // Found valid candidate for placeholder image.
                    break;
                }
                if (\Google\Web_Stories_Dependencies\AmpProject\Amp::isTemplate($placeholder)) {
                    // Ignore images inside templates.
                    $placeholder = $this->skipNodeAndChildren($placeholder);
                } else {
                    $placeholder = $this->nextNode($placeholder);
                }
            }
            if (!$placeholder instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element) {
                break;
            }
            $src = $placeholder->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRC);
            if (!(new \Google\Web_Stories_Dependencies\AmpProject\Url($src))->isValidNonDataUrl()) {
                break;
            }
            return new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\HeroImage($src, $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::MEDIA), $placeholder->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SRCSET), $placeholder);
        }
        return null;
    }
    /**
     * Remove the lazy loading from the hero image.
     *
     * @param HeroImage $heroImage Hero image to remove the lazy loading for.
     */
    private function removeLazyLoading(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\HeroImage $heroImage)
    {
        $img = $heroImage->getAmpImg();
        if ($img && $img->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::LOADING) === 'lazy' && !$img->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::DATA_AMP_STORY_PLAYER_POSTER_IMG)) {
            $img->removeAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::LOADING);
        }
    }
    /**
     * Generate the preload link for a given hero image.
     *
     * @param HeroImage       $heroImage Hero image to generate the preload link for.
     * @param Document        $document  Document to generate the preload link in.
     * @param ErrorCollection $errors    Collection of errors that are collected during transformation.
     */
    private function generatePreload(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\HeroImage $heroImage, \Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection $errors)
    {
        if (empty($heroImage->getMedia())) {
            // We can only safely preload a hero image if there's a media attribute
            // as we can't detect whether it's hidden on certain viewport sizes otherwise.
            return;
        }
        if ($heroImage->getSrcset() && !$this->supportsSrcset()) {
            $errors->add(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error\CannotPreloadImage::fromImageWithSrcsetAttribute($heroImage->getAmpImg()));
            return;
        }
        if ($this->hasExistingImagePreload($document, $heroImage->getSrc())) {
            return;
        }
        if ($this->preloadReferenceNode === null) {
            $this->preloadReferenceNode = $document->viewport;
        }
        $preload = $document->createElement(\Google\Web_Stories_Dependencies\AmpProject\Tag::LINK);
        $preload->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::REL, \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_PRELOAD);
        $preload->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF, $heroImage->getSrc());
        $preload->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::AS_, \Google\Web_Stories_Dependencies\AmpProject\RequestDestination::IMAGE);
        $preload->appendChild($document->createAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::DATA_HERO));
        if ($heroImage->getSrcset()) {
            $preload->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::IMAGESRCSET, $heroImage->getSrcset());
            $img = $heroImage->getAmpImg();
            if ($img && $img->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SIZES)) {
                $preload->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::IMAGESIZES, $img->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::SIZES));
            }
        }
        $preload->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::MEDIA, $heroImage->getMedia());
        if ($this->preloadReferenceNode) {
            $this->preloadReferenceNode->parentNode->insertBefore($preload, $this->preloadReferenceNode->nextSibling);
        } else {
            $document->head->appendChild($preload);
        }
        $this->preloadReferenceNode = $preload;
    }
    /**
     * Generate the SSR image element for the hero image.
     *
     * @param HeroImage $heroImage Hero image to generate the SSR image element for.
     * @param Document  $document  Document in which to generate the SSR image element in.
     */
    private function generateImg(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\HeroImage $heroImage, \Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document)
    {
        $element = $heroImage->getAmpImg();
        if (!$element || $element->tagName !== \Google\Web_Stories_Dependencies\AmpProject\Extension::IMG) {
            return;
        }
        $imgElement = $document->createElement(\Google\Web_Stories_Dependencies\AmpProject\Tag::IMG);
        $imgElement->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::CLASS_, self::SSR_IMAGE_CLASS);
        $imgElement->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::DECODING, 'async');
        // If the image was detected as hero image candidate (and thus lacks an explicit data-hero), mark it as a hero
        // and add loading=lazy to guard against making the page performance even worse by eagerly loading an image
        // outside the viewport.
        if (!$this->isMarkedAsHeroImage($element)) {
            $imgElement->setAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::LOADING, 'lazy');
        }
        if (!$element->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::DATA_HERO)) {
            $element->appendChild($document->createAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::DATA_HERO));
        }
        foreach (self::ATTRIBUTES_TO_COPY as $attribute) {
            if ($element->hasAttribute($attribute)) {
                $imgElement->setAttribute($attribute, $element->getAttribute($attribute));
            }
        }
        foreach (self::ATTRIBUTES_TO_INLINE as $attribute) {
            if ($element->hasAttribute($attribute)) {
                $value = $element->getAttribute($attribute);
                $style = empty($value) ? '' : "{$attribute}:{$element->getAttribute($attribute)}";
                $imgElement->addInlineStyle($style);
            }
        }
        $element->appendChild($document->createAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::I_AMPHTML_SSR));
        $element->appendChild($imgElement);
        // Remove any noscript>img when an amp-img is pre-rendered.
        $noscript = $document->xpath->query(self::NOSCRIPT_IMG_XPATH_QUERY, $element)->item(0);
        if ($noscript instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element) {
            $noscript->parentNode->removeChild($noscript);
        }
    }
    /**
     * Check whether an existing preload link exists for a given src.
     *
     * @param Document $document Document in which to check for an existing preload.
     * @param string   $src      Preload URL to look for.
     * @return bool Whether an existing preload already exists.
     */
    private function hasExistingImagePreload(\Google\Web_Stories_Dependencies\AmpProject\Dom\Document $document, $src)
    {
        foreach ($document->head->childNodes as $node) {
            if (!$node instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element) {
                continue;
            }
            if ($node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::REL) !== \Google\Web_Stories_Dependencies\AmpProject\Attribute::REL_PRELOAD) {
                continue;
            }
            if ($node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::AS_) !== \Google\Web_Stories_Dependencies\AmpProject\RequestDestination::IMAGE) {
                continue;
            }
            if ($node->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::HREF) === $src) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Depth-first walk through the DOM tree.
     *
     * @param DOMNode $node Node to start walking from.
     * @return DOMNode|null Next node, or null if none found.
     */
    private function nextNode(\DOMNode $node)
    {
        // Walk downwards if there are children.
        if ($node->firstChild) {
            return $node->firstChild;
        }
        // Return direct sibling or walk upwards until we find a node with a sibling.
        while ($node) {
            if ($node->nextSibling) {
                return $node->nextSibling;
            }
            $node = $node->parentNode;
        }
        // Out of nodes, so we're done.
        return null;
    }
    /**
     * Skip the subtree that is descending from the provided node.
     *
     * @param DOMNode $node Node to skip the subtree of.
     * @return DOMNode|null The appropriate "next" node that will skip the current subtree, null if none found.
     */
    private function skipNodeAndChildren(\DOMNode $node)
    {
        if ($node->nextSibling) {
            return $node->nextSibling;
        }
        return $this->skipNodeAndChildren($node->parentNode);
    }
    /**
     * Check whether a given element is an AMP embed.
     *
     * @param Element $element Element to check.
     * @return bool Whether the given element is an AMP embed.
     */
    private function isAmpEmbed(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        return \array_key_exists($element->tagName, self::AMP_EMBEDS);
    }
    /**
     * Get the URL of the CSS background-image property.
     *
     * This falls back to the data-amp-original-style attribute if the inline
     * style was already extracted by the CSS tree-shaking.
     *
     * @param Element $element
     * @return string URL of the background image, or an empty string if not found.
     */
    private function getCssBackgroundImageUrl(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        $matches = [];
        if (\preg_match(self::CSS_BACKGROUND_IMAGE_URL_REGEX_PATTERN, $element->getAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::STYLE), $matches)) {
            return \trim($matches['url'], '\'" ');
        }
        if (!empty($this->inlineStyleBackupAttribute) && \preg_match(self::CSS_BACKGROUND_IMAGE_URL_REGEX_PATTERN, $element->getAttribute($this->inlineStyleBackupAttribute), $matches)) {
            return \trim($matches['url'], '\'" ');
        }
        return '';
    }
    /**
     * Whether srcset preloading is supported.
     *
     * @return bool
     */
    private function supportsSrcset()
    {
        return $this->configuration->get(\Google\Web_Stories_Dependencies\AmpProject\Optimizer\Configuration\PreloadHeroImageConfiguration::PRELOAD_SRCSET);
    }
    /**
     * Check if an element or its ancestors is marked as a hero image.
     *
     * @param Element $element Element to check.
     * @return bool Whether the element or one of its ancestors is marked as a hero image.
     */
    private function isMarkedAsHeroImage(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        while ($element) {
            if (!$element instanceof \Google\Web_Stories_Dependencies\AmpProject\Dom\Element) {
                $element = $element->parentNode;
                continue;
            }
            if ($element->hasAttribute(\Google\Web_Stories_Dependencies\AmpProject\Attribute::DATA_HERO)) {
                return \true;
            }
            if ($element->tagName === \Google\Web_Stories_Dependencies\AmpProject\Tag::BODY || $element->tagName === \Google\Web_Stories_Dependencies\AmpProject\Tag::HTML) {
                return \false;
            }
            $element = $element->parentNode;
        }
        return \false;
    }
}
