<?php

declare(strict_types=1);

namespace NecLimDul\NetSuiteSearchIterator;

use NecLimDul\NetSuiteSearchIterator\Exception\StatusFailure;
use NetSuite\Classes\SearchMoreWithIdRequest;
use NetSuite\Classes\SearchRecord;
use NetSuite\Classes\SearchRequest;
use NetSuite\Classes\SearchResult;
use NetSuite\NetSuiteService;

/**
 * NetSuite search iterator.
 *
 * @template T of \NetSuite\Classes\Record
 * @template-implements \Iterator<int, T>
 *
 * This is an iterator that allows seamless iteration over all results of a
 * search. This is done by handling "search more" requests seamlessly as needed
 * behind the scenes.
 *
 * Because this iterator is doing SOAP requests as needed, use of the iterator
 * needs to be wrapped in error handling.
 *
 * It doesn't seem possible to sort the results in your query to netsuite, so
 * you may have to process all the paged results from start to end in one go and
 * apply your own sorting.
 *
 * @see https://stackoverflow.com/q/30975586
 *
 *
 * Example:
 * ```
 * $iterator = new SearchIterator($service, $search);
 * try {
 *   foreach ($iterator as $record) {
 *     // process results
 *   }
 * }
 * catch (\SoapFault $e) {
 *   // Handle exception
 * }
 * catch (\NecLimDul\NetSuiteSearchIterator\Exception\StatusFailure $e) {
 *   // Handle exception
 * }
 * ```
 *
 * For loops are more than enough to interact with this iterator, but it can
 * also be useful connected with a generator and the \nikic\iter library.
 *
 * Example:
 * ```
 * fetchCustomer($date) {
 *   $search = new CustomerSearchBasic();
 *   $search->lastModifiedDate = new SearchDateField();
 *   $search->lastModifiedDate->operator = SearchDateFieldOperator::after;
 *   $search->lastModifiedDate->searchValue = date(DATE_ISO8601, $date);
 *   $iterator = new SearchIterator($service, $search);
 *   foreach ($iterator as $record) {
 *     yield convertCustomer($record);
 *   }
 * }
 * // This is a pretty weird filter not to include in your search but easy to read.
 * $mylist = \iter\filter(fn($customer) => $customer->isActive, fetchCustomer('2020-09-09'));
 * foreach ($mylist as $customer) {
 *   print($customer->companyName);
 * }
 * ```
 */
class SearchIterator implements \Iterator, \Countable {

    /**
     * @var \NetSuite\NetSuiteService
     */
    protected NetSuiteService $netSuiteService;

    /**
     * @var \NetSuite\Classes\SearchRecord
     */
    protected SearchRecord $search;

    /**
     * Storage for search results.
     *
     * @var \ArrayObject<int, T>
     */
    protected \ArrayObject $results;

    /**
     * An internal iterator to the array storage for easier implementation.
     *
     * @var \ArrayIterator<int, T>
     */
    protected \ArrayIterator $resultsIterator;

    /**
     * The search identifier for paging through the search results.
     *
     * @var string|null
     */
    protected ?string $searchId = NULL;

    /**
     * The last page the search has fetched sequentially.
     *
     * @var int
     */
    protected int $page = 0;

    /**
     * The last page of the search results.
     *
     * @var int
     */
    protected int $maxPage = 0;

    /**
     * The total number of results in the results set.
     *
     * @var int
     */
    protected int $totalRecords = 0;

    /**
     * @var class-string<T>|null
     */
    protected ?string $type;

    /**
     * The size of the page used to page.
     *
     * This should be used consistently across all page requests or the page
     * numbering won't work correctly.
     *
     * @var int
     */
    private int $pageSize;

    /**
     * SearchIterator constructor.
     *
     * @param \NetSuite\NetSuiteService $netSuiteService
     * @param \NetSuite\Classes\SearchRecord $search
     * @param class-string<T>|int $type
     * @param int $pageSize
     */
    public function __construct(
        NetSuiteService $netSuiteService,
        SearchRecord $search,
        $type = NULL,
        int $pageSize = 50
    ) {
        $this->netSuiteService = $netSuiteService;
        $this->search = $search;
        /** @psalm-suppress MixedPropertyTypeCoercion */
        $this->results = new \ArrayObject();
        /** @psalm-suppress MixedPropertyTypeCoercion */
        $this->resultsIterator = $this->results->getIterator();

        if (is_int($type)) {
            trigger_error('Passing page size as third argument is deprecated.', E_USER_DEPRECATED);
            $this->pageSize = $type;
        }
        else {
            $this->type = $type;
            $this->pageSize = $pageSize;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function current(): mixed {
        $this->init();
        return $this->resultsIterator->current();
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void {
        $this->init();
        $this->resultsIterator->next();
        // If we run into the end, request more.
        if (!$this->resultsIterator->valid()) {
            $this->searchMore();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function key(): mixed {
        $this->init();
        return $this->resultsIterator->key();
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool {
        $this->init();
        return $this->resultsIterator->valid();
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void {
        $this->init();
        $this->resultsIterator->rewind();
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int {
        $this->init();
        return $this->totalRecords;
    }

    /**
     * Make sure the internal properties are initialized from the first request.
     *
     * @throws \SoapFault
     */
    private function init(): void {
        if (!isset($this->searchId)) {
            $this->initialSearch();
        }
    }

    /**
     * Perform the initial search.
     *
     * @throws \SoapFault
     * @throws \NecLimDul\NetSuiteSearchIterator\Exception\StatusFailure
     */
    private function initialSearch(): void {
        $search_request = new SearchRequest();
        $search_request->searchRecord = $this->search;
        $this->netSuiteService->setSearchPreferences(TRUE, $this->pageSize);
        try {
            $result = $this->netSuiteService->search($search_request);
        } finally {
            $this->netSuiteService->clearSearchPreferences();
        }
        $this->processResults($result->searchResult);
    }

    /**
     * Fetch more results from the search.
     *
     * @throws \SoapFault
     * @throws \NecLimDul\NetSuiteSearchIterator\Exception\StatusFailure
     */
    private function searchMore(): void {
        if ($this->page < $this->maxPage) {
            $request = new SearchMoreWithIdRequest();
            $request->pageIndex = $this->page + 1;
            $request->searchId = (string) $this->searchId;
            $this->netSuiteService->setSearchPreferences(TRUE, $this->pageSize);
            try {
                $result = $this->netSuiteService->searchMoreWithId($request);
            } finally {
                $this->netSuiteService->clearSearchPreferences();
            }
            $this->processResults($result->searchResult);
        }
    }

    /**
     * Process search results and handle failures.
     *
     * @param \NetSuite\Classes\SearchResult $result
     *   A search result object.
     *
     * @throws \NecLimDul\NetSuiteSearchIterator\Exception\StatusFailure
     */
    private function processResults(SearchResult $result): void {
        if ($result->status->isSuccess) {
            $this->updateState($result);
            if ($result->recordList->record) {
                // NetSuite's results are polymorphic so cast result.
                /** @var T $item */
                foreach ($result->recordList->record as $item) {
                    $this->results->append($item);
                }
            }
        }
        else {
            throw new StatusFailure($result->status);
        }
    }

    /**
     * Update internal state tracking the search position.
     *
     * @param \NetSuite\Classes\SearchResult $result
     *   A search result object.
     */
    private function updateState(SearchResult $result): void {
        assert(!!$result->searchId, 'Missing searchId. This could lead to infinite loops.');
        /**
         * NetSuite documentation is a bag of lies sometimes. Hint the real
         * type so static analysis can resolve things.
         *
         * @var int|null $tmp
         */
        $tmp = $result->pageIndex;
        $this->page = $tmp ?? 0;
        $this->searchId = $result->searchId;
        $this->maxPage = $result->totalPages;
        $this->totalRecords = $result->totalRecords;
    }

}
