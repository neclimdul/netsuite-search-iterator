<?php

namespace NecLimDul\NetSuiteSearchIterator\Tests;

use NecLimDul\NetSuiteSearchIterator\Exception\StatusFailure;
use NecLimDul\NetSuiteSearchIterator\SearchIterator;
use NetSuite\Classes\RecordList;
use NetSuite\Classes\SearchRecord;
use NetSuite\Classes\SearchResponse;
use NetSuite\Classes\SearchResult;
use NetSuite\Classes\Status;
use NetSuite\NetSuiteService;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \NecLimDul\NetSuiteSearchIterator\SearchIterator
 */
class SearchIteratorTest extends TestCase {

    use ProphecyTrait;

    /**
     * @var \NetSuite\NetSuiteService|\Prophecy\Prophecy\ObjectProphecy
     */
    protected $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = $this->prophesize(NetSuiteService::class);
    }

    /**
     * Test that nothing happens just constructing the iterator.
     *
     * @covers ::__construct
     */
    public function testConstruction() {
        $this->service->search(Argument::any())
            ->shouldNotBeCalled();
        $this->service->searchMoreWithId(Argument::any())
            ->shouldNotBeCalled();
        $search = new SearchRecord();
        new SearchIterator($this->service->reveal(), $search);
    }

    /**
     * Test that nothing happens just constructing the iterator.
     *
     * @covers \NecLimDul\NetSuiteSearchIterator\SearchIterator
     */
    public function testIteration() {
        $result1 = new SearchResponse();
        $result1->searchResult = new SearchResult();
        $result1->searchResult->status = new Status();
        $result1->searchResult->status->isSuccess = TRUE;
        $result1->searchResult->pageSize = 25;
        $result1->searchResult->totalPages = 3;
        $result1->searchResult->totalRecords = 75;
        $result1->searchResult->pageIndex = 1;
        $result1->searchResult->searchId = 'asdf123asdf';
        $result1->searchResult->recordList = new RecordList();
        $result1->searchResult->recordList->record = array_fill(0, 25, 'asdf');

        $result2 = new SearchResponse();
        $result2->searchResult = new SearchResult();
        $result2->searchResult->status = new Status();
        $result2->searchResult->status->isSuccess = TRUE;
        $result2->searchResult->pageSize = 25;
        $result2->searchResult->totalPages = 3;
        $result2->searchResult->totalRecords = 75;
        $result2->searchResult->pageIndex = 2;
        $result2->searchResult->searchId = 'asdf123asdf';
        $result2->searchResult->recordList = new RecordList();
        $result2->searchResult->recordList->record = array_fill(0, 25, 'asdf');

        $result3 = new SearchResponse();
        $result3->searchResult = new SearchResult();
        $result3->searchResult->status = new Status();
        $result3->searchResult->status->isSuccess = TRUE;
        $result3->searchResult->pageSize = 25;
        $result3->searchResult->totalPages = 3;
        $result3->searchResult->totalRecords = 75;
        $result3->searchResult->pageIndex = 3;
        $result3->searchResult->searchId = 'asdf123asdf';
        $result3->searchResult->recordList = new RecordList();
        $result3->searchResult->recordList->record = array_fill(0, 25, 'asdf');

        $this->service->setSearchPreferences(Argument::cetera())
            ->shouldBeCalled();
        $this->service->clearSearchPreferences()
            ->shouldBeCalled();
        $this->service->search(Argument::any())
            ->willReturn($result1)
            ->shouldBeCalledOnce();
        $this->service->searchMoreWithId(Argument::which('pageIndex', 2))
            ->willReturn($result2)
            ->shouldBeCalledOnce();
        $this->service->searchMoreWithId(Argument::which('pageIndex', 3))
            ->willReturn($result3)
            ->shouldBeCalledOnce();
        $search = new SearchRecord();
        $i = new SearchIterator($this->service->reveal(), $search);
        $count = 0;
        foreach ($i as $key => $item) {
            $count++;
            $this->assertSame('asdf', $item);
            $this->assertSame($key, $i->key());
        }
        $this->assertSame(75, $count);
        $this->assertSame(75, $i->count());
    }

    /**
     * Test that nothing happens just constructing the iterator.
     *
     * @covers \NecLimDul\NetSuiteSearchIterator\SearchIterator
     */
    public function testIterationEmpty() {
        $result1 = new SearchResponse();
        $result1->searchResult = new SearchResult();
        $result1->searchResult->status = new Status();
        $result1->searchResult->status->isSuccess = TRUE;
        $result1->searchResult->pageSize = 25;
        $result1->searchResult->totalPages = 0;
        $result1->searchResult->totalRecords = 0;
        $result1->searchResult->pageIndex = 1;
        $result1->searchResult->searchId = 'asdf123asdf';
        $result1->searchResult->recordList = new RecordList();
        $result1->searchResult->recordList->record = [];

        $this->service->setSearchPreferences(Argument::cetera())
            ->shouldBeCalledOnce();
        $this->service->clearSearchPreferences()
            ->shouldBeCalledOnce();
        $this->service->search(Argument::any())
            ->shouldBeCalledOnce()
            ->willReturn($result1);
        $search = new SearchRecord();
        $i = new SearchIterator($this->service->reveal(), $search);
        $count = 0;
        foreach ($i as $key => $item) {
            $count++;
            $this->assertSame('asdf', $item);
            $this->assertSame($key, $i->key());
        }
        $this->assertSame(0, $count);
        $this->assertSame(0, $i->count());
    }

    /**
     * Test exception when status fails.
     *
     * @covers ::processResults
     */
    public function testIteratorException() {
        $result1 = new SearchResponse();
        $result1->searchResult = new SearchResult();
        $result1->searchResult->status = new Status();
        $result1->searchResult->status->isSuccess = FALSE;
        $result1->searchResult->status->statusDetail = ['foo bar baz'];
        $result1->searchResult->pageSize = 25;
        $result1->searchResult->totalPages = 3;
        $result1->searchResult->totalRecords = 75;
        $result1->searchResult->pageIndex = 1;
        $result1->searchResult->searchId = 'asdf123asdf';
        $result1->searchResult->recordList = new RecordList();
        $result1->searchResult->recordList->record = array_fill(0, 25, 'asdf');

        $this->service->setSearchPreferences(Argument::cetera())
            ->shouldBeCalled();
        $this->service->clearSearchPreferences()
            ->shouldBeCalled();
        $this->service->search(Argument::any())
            ->willReturn($result1)
            ->shouldBeCalledOnce();

        $search = new SearchRecord();
        $i = new SearchIterator($this->service->reveal(), $search);
        $this->expectException(StatusFailure::class);
        $this->expectExceptionMessage(
            'Something went wrong with your request: ' .
            json_encode($result1->searchResult->status->statusDetail)
        );

        $i->next();
    }

    /**
     * Test exception when status fails.
     *
     * @covers ::processResults
     */
    public function testIteratorNullResult() {
        $result1 = new SearchResponse();
        $result1->searchResult = new SearchResult();
        $result1->searchResult->status = new Status();
        $result1->searchResult->status->isSuccess = TRUE;
        $result1->searchResult->pageSize = NULL;
        $result1->searchResult->totalPages = 1;
        $result1->searchResult->totalRecords = 0;
        $result1->searchResult->pageIndex = 1;
        $result1->searchResult->searchId = 'asdf123asdf';
        $result1->searchResult->recordList = new RecordList();
        $result1->searchResult->recordList->record = NULL;

        $this->service->setSearchPreferences(Argument::cetera())
            ->shouldBeCalled();
        $this->service->clearSearchPreferences()
            ->shouldBeCalled();
        $this->service->search(Argument::any())
            ->willReturn($result1)
            ->shouldBeCalledOnce();

        $search = new SearchRecord();
        $i = new SearchIterator($this->service->reveal(), $search);
        $this->assertEquals(0, iterator_count($i));
        $this->assertEquals(0, $i->count());
    }

}
