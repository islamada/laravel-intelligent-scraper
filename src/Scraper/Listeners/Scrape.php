<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Softonic\LaravelIntelligentScraper\Scraper\Application\XpathFinder;
use Softonic\LaravelIntelligentScraper\Scraper\Events\InvalidConfiguration;
use Softonic\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Softonic\LaravelIntelligentScraper\Scraper\Exceptions\MissingXpathValueException;
use Softonic\LaravelIntelligentScraper\Scraper\Repositories\Configuration;

class Scrape implements ShouldQueue
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var XpathFinder
     */
    private $xpathFinder;

    public function __construct(
        Configuration $configuration,
        XpathFinder $xpathFinder,
        LoggerInterface $logger
    ) {
        $this->configuration = $configuration;
        $this->xpathFinder   = $xpathFinder;
        $this->logger        = $logger;
    }

    public function handle(ScrapeRequest $scrapeRequest)
    {
        try {
            $config = $this->loadConfiguration($scrapeRequest);
            $this->extractData($scrapeRequest, $config);
        } catch (MissingXpathValueException $e) {
            $this->logger->notice(
                "Invalid Configuration for '$scrapeRequest->url' and type '$scrapeRequest->type', error: {$e->getMessage()}."
            );

            event(new InvalidConfiguration($scrapeRequest));
        }
    }

    private function loadConfiguration(ScrapeRequest $scrapeRequest): Collection
    {
        $this->logger->info("Loading scrapping configuration for type '$scrapeRequest->type'");

        $config = $this->configuration->findByType($scrapeRequest->type);
        if ($config->isEmpty()) {
            throw new MissingXpathValueException('Missing initial configuration');
        }

        return $config;
    }

    private function extractData(ScrapeRequest $scrapeRequest, Collection $config): void
    {
        $this->logger->info("Extracting data from $scrapeRequest->url for type '$scrapeRequest->type'");

        ['data' => $data, 'variant' => $variant] = $this->xpathFinder->extract($scrapeRequest->url, $config);
        event(new Scraped($scrapeRequest, $data, $variant));
    }
}
