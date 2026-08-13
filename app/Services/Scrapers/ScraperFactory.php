<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperInterface;
use App\Exceptions\ScrapingException;
use App\Models\NewsSource;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the correct ScraperInterface implementation for a given NewsSource.
 *
 * The scraper class is stored on the NewsSource model as scraper_class.
 * The Container is used for resolution so concrete scrapers can receive
 * their dependencies (NewsSource, Http client) via constructor injection.
 */
class ScraperFactory
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Resolve the scraper for the given news source.
     *
     * @throws ScrapingException if the class doesn't implement ScraperInterface.
     */
    public function make(NewsSource $source): ScraperInterface
    {
        $scraperClass = $source->scraper_class;

        /** @var object $scraper */
        $scraper = $this->container->makeWith($scraperClass, [
            'source' => $source,
        ]);

        if (! $scraper instanceof ScraperInterface) {
            throw ScrapingException::forInvalidScraperClass($scraperClass);
        }

        return $scraper;
    }
}
