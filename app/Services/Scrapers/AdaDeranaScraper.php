<?php

namespace App\Services\Scrapers;

use App\Exceptions\ScrapingException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;

/**
 * Scraper for the Ada Derana English news site (https://adaderana.lk).
 *
 * Discovery is driven by the Google News sitemap (NewsSource::archive_url),
 * which lists the ~120 most recent articles together with their publication
 * dates. Articles already seen before last_scraped_at are skipped so
 * subsequent runs only return new articles.
 *
 * If the sitemap cannot be fetched or parsed, the scraper falls back to
 * paginating the /categories/latest HTML listing.
 *
 * Only CUID-format article IDs (e.g. cmsrrdxm8000a356q3041osft) are
 * accepted. Deep listing pages serve legacy numeric IDs which belong to
 * the old platform and are deliberately ignored.
 *
 * Article pages are fully server-rendered: the JSON-LD NewsArticle block
 * provides the headline, publication date, and image, while the article
 * body lives inside the .prose container.
 */
class AdaDeranaScraper extends BaseArticleScraper
{
    /**
     * Path to the HTML listing used when the sitemap is unavailable.
     */
    private const LISTING_PATH = '/categories/latest';

    /**
     * Number of listing pages scanned in fallback mode.
     */
    private const MAX_LISTING_PAGES = 5;

    /**
     * Number of articles requested per listing page.
     */
    private const LISTING_PAGE_SIZE = 20;

    /**
     * Matches CUID article IDs (e.g. cmsrrdxm8000a356q3041osft).
     * Legacy numeric IDs are excluded on purpose.
     */
    private const ARTICLE_ID_PATTERN = '/^c[a-z0-9]{20,30}$/';

    /**
     * Discover article URLs from the news sitemap, falling back to the
     * HTML listing when the sitemap is unavailable.
     *
     * @return array<int, string>
     *
     * @throws ScrapingException when both discovery sources fail.
     */
    public function scrapeArchive(): array
    {
        try {
            return $this->scrapeSitemap();
        } catch (ScrapingException) {
            return $this->scrapeListing();
        }
    }

    /**
     * Scrape a single article page and return its normalized content.
     *
     * @return array{title: string, body: string, image_url: string|null, published_at: string|null}
     *
     * @throws ScrapingException when the page has no JSON-LD, no title, or no body.
     */
    public function scrapeArticle(string $url): array
    {
        $dom = $this->fetchDom($url);

        $jsonLd = $this->extractNewsArticleJsonLd($dom);

        if ($jsonLd === null) {
            throw ScrapingException::forSource($this->source, "article page has no NewsArticle JSON-LD: {$url}");
        }

        $this->validateCanonicalUrl($dom, $url);

        return [
            'title' => $this->extractTitle($dom, $jsonLd, $url),
            'body' => $this->extractBody($dom, $url),
            'image_url' => $this->extractImage($dom, $jsonLd),
            'published_at' => $this->extractPublishedAt($jsonLd),
        ];
    }

    /**
     * Fetch and parse the news sitemap, returning CUID article URLs that
     * are newer than the source's last_scraped_at.
     *
     * @return array<int, string>
     *
     * @throws ScrapingException on HTTP failure or unparseable XML.
     */
    private function scrapeSitemap(): array
    {
        $response = $this->http->timeout(15)->get($this->source->archive_url);

        if ($response->failed()) {
            throw ScrapingException::forHttpFailure($this->source->archive_url, $response->status());
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($response->body());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw ScrapingException::forSource($this->source, 'sitemap XML could not be parsed');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('news', 'http://www.google.com/schemas/sitemap-news/0.9');

        $urls = [];

        $urlNodes = $xpath->query('//sm:url');

        if ($urlNodes === false) {
            return [];
        }

        foreach ($urlNodes as $urlNode) {
            /** @var DOMElement $urlNode */
            $loc = trim($xpath->evaluate('string(sm:loc)', $urlNode));
            $publicationDate = trim($xpath->evaluate('string(news:news/news:publication_date)', $urlNode));

            if ($this->isArticleUrl($loc) && $this->isNewerThanLastScrape($publicationDate)) {
                $urls[] = $loc;
            }
        }

        return $urls;
    }

    /**
     * Discover article URLs by paginating the HTML listing.
     *
     * Pagination stops early when a page yields no unseen CUID article URL.
     *
     * @return array<int, string>
     *
     * @throws ScrapingException on HTTP failure.
     */
    private function scrapeListing(): array
    {
        $urls = [];
        $seen = [];

        for ($page = 0; $page < self::MAX_LISTING_PAGES; $page++) {
            $listingUrl = $this->source->base_url
                .self::LISTING_PATH
                .'?page='.$page
                .'&pageSize='.self::LISTING_PAGE_SIZE;

            $xpath = new DOMXPath($this->fetchDom($listingUrl));

            $newOnPage = 0;

            $links = $xpath->query('//a[@href]');

            if ($links !== false) {
                /** @var DOMElement $link */
                foreach ($links as $link) {
                    $href = $link->getAttribute('href');

                    if (! preg_match('#^/news/([a-z0-9]+)#', $href, $matches)) {
                        continue;
                    }

                    if (preg_match(self::ARTICLE_ID_PATTERN, $matches[1]) !== 1) {
                        continue;
                    }

                    $articleUrl = $this->source->base_url.$href;

                    if (isset($seen[$articleUrl])) {
                        continue;
                    }

                    $seen[$articleUrl] = true;
                    $urls[] = $articleUrl;
                    $newOnPage++;
                }
            }

            if ($newOnPage === 0) {
                break;
            }
        }

        return $urls;
    }

    /**
     * Determine whether the URL points to a CUID-format article.
     */
    private function isArticleUrl(string $url): bool
    {
        if (! preg_match('#/news/([a-z0-9]+)#', $url, $matches)) {
            return false;
        }

        return preg_match(self::ARTICLE_ID_PATTERN, $matches[1]) === 1;
    }

    /**
     * Determine whether a publication date is newer than the last scrape.
     *
     * Entries without a parseable date are included conservatively.
     */
    private function isNewerThanLastScrape(string $publicationDate): bool
    {
        if ($this->source->last_scraped_at === null || $publicationDate === '') {
            return true;
        }

        try {
            return Carbon::parse($publicationDate)->gt($this->source->last_scraped_at);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Find and decode the NewsArticle JSON-LD block on the page.
     *
     * @return array<string, mixed>|null
     */
    private function extractNewsArticleJsonLd(DOMDocument $dom): ?array
    {
        $xpath = new DOMXPath($dom);

        $scripts = $xpath->query('//script[@type="application/ld+json"]');

        if ($scripts === false) {
            return null;
        }

        foreach ($scripts as $script) {
            /** @var DOMElement $script */
            $decoded = json_decode($script->textContent, associative: true);

            if (is_array($decoded) && ($decoded['@type'] ?? null) === 'NewsArticle') {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Extract the article title from JSON-LD, falling back to og:title.
     *
     * @param  array<string, mixed>  $jsonLd
     *
     * @throws ScrapingException when no title can be found.
     */
    private function extractTitle(DOMDocument $dom, array $jsonLd, string $url): string
    {
        $title = $jsonLd['headline'] ?? null;

        if (is_string($title) && trim($title) !== '') {
            return html_entity_decode(trim($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $ogTitle = $this->metaContent($dom, 'og:title');

        if ($ogTitle !== null) {
            return $ogTitle;
        }

        throw ScrapingException::forSource($this->source, "article page has no title: {$url}");
    }

    /**
     * Extract the article body from the .prose container paragraphs.
     *
     * Paragraphs are trimmed, empty/non-breaking-space paragraphs are
     * dropped, and the remainder is joined with double newlines. Ad
     * paragraphs outside the prose container are excluded naturally.
     *
     * @throws ScrapingException when the body is empty.
     */
    private function extractBody(DOMDocument $dom, string $url): string
    {
        $xpath = new DOMXPath($dom);

        $paragraphs = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " prose ")]//p');

        $parts = [];

        if ($paragraphs !== false) {
            foreach ($paragraphs as $paragraph) {
                /** @var DOMElement $paragraph */
                $text = trim($paragraph->textContent, " \t\n\r\0\x0B\xC2\xA0");

                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        $body = implode("\n\n", $parts);

        if ($body === '') {
            throw ScrapingException::forSource($this->source, "article page has no body: {$url}");
        }

        return $body;
    }

    /**
     * Extract the published date from JSON-LD, preferring datePublished.
     *
     * @param  array<string, mixed>  $jsonLd
     */
    private function extractPublishedAt(array $jsonLd): ?string
    {
        $publishedAt = $jsonLd['datePublished'] ?? $jsonLd['dateModified'] ?? null;

        if (! is_string($publishedAt) || trim($publishedAt) === '') {
            return null;
        }

        try {
            Carbon::parse($publishedAt);
        } catch (\Throwable) {
            return null;
        }

        return trim($publishedAt);
    }

    /**
     * Extract the hero image from JSON-LD, falling back to og:image.
     *
     * @param  array<string, mixed>  $jsonLd
     */
    private function extractImage(DOMDocument $dom, array $jsonLd): ?string
    {
        $image = $jsonLd['image'] ?? null;

        if (is_array($image)) {
            $image = $image[0] ?? null;
        }

        if (is_string($image) && trim($image) !== '') {
            return trim($image);
        }

        return $this->metaContent($dom, 'og:image');
    }

    /**
     * Read a meta property value, entity-decoded.
     */
    private function metaContent(DOMDocument $dom, string $property): ?string
    {
        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query('//meta[@property="'.$property.'"]/@content');

        if ($nodes === false) {
            return null;
        }

        $node = $nodes->item(0);

        if ($node === null) {
            return null;
        }

        $value = html_entity_decode(trim($node->nodeValue ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $value === '' ? null : $value;
    }

    /**
     * Validate that the canonical link matches the requested article.
     *
     * The canonical URL is not part of the scraper contract yet — it is
     * used only as an integrity check so a redirect or wrong page cannot
     * silently produce content for a different article.
     *
     * @throws ScrapingException when the canonical link points elsewhere.
     */
    private function validateCanonicalUrl(DOMDocument $dom, string $url): void
    {
        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query('//link[@rel="canonical"]/@href');

        if ($nodes === false) {
            return;
        }

        $node = $nodes->item(0);

        if ($node === null) {
            return;
        }

        $canonical = trim($node->nodeValue ?? '');

        if ($canonical === '' || ! preg_match('#/news/([a-z0-9]+)#', $canonical, $canonicalMatches)) {
            return;
        }

        preg_match('#/news/([a-z0-9]+)#', $url, $requestedMatches);

        if (! isset($requestedMatches[1]) || $canonicalMatches[1] !== $requestedMatches[1]) {
            throw ScrapingException::forSource(
                $this->source,
                "article canonical URL mismatch: requested {$url}, canonical {$canonical}"
            );
        }
    }
}
