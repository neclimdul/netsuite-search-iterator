<?php

declare(strict_types=1);

namespace NecLimDul\NetSuiteSearchIterator\Tests\Exception;

use NecLimDul\NetSuiteSearchIterator\Exception\StatusFailure;
use NetSuite\Classes\Status;
use NetSuite\Classes\StatusDetail;
use NetSuite\Classes\StatusDetailCodeType;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \NecLimDul\NetSuiteSearchIterator\Exception\StatusFailure
 */
class StatusFailureTest extends TestCase {

    /**
     * @covers ::__construct
     */
    public function testConstruct(): void {
        $detail = new StatusDetail();
        $detail->message = 'asdf';
        $status = new Status();
        $status->statusDetail = [$detail];
        $e = new StatusFailure($status);
        $this->assertSame(0, $e->getCode());
        $this->assertSame(
            'Something went wrong with your request: ' . json_encode($status->statusDetail),
            $e->getMessage()
        );

        $previous = new \Exception();
        $e = new StatusFailure($status, 123, 'Something extra', $previous);
        $this->assertSame(123, $e->getCode());
        $this->assertSame(
            'Something extra' . PHP_EOL
            . 'Something went wrong with your request: '
            . json_encode($status->statusDetail),
            $e->getMessage()
        );
        $this->assertSame($previous, $e->getPrevious());
    }

    /**
     * @covers ::getStatus
     */
    public function testStatus(): void {
        $status = new Status();
        $e = new StatusFailure($status);
        $this->assertSame($status, $e->getStatus());
    }

    /**
     * @covers ::containsCode
     */
    public function testContainsCode(): void {
        $status = new Status();
        $detail = new StatusDetail();
        $detail->code = StatusDetailCodeType::INVALID_KEY_OR_REF;
        $e = new StatusFailure($status);
        $this->assertFalse($e->containsCode(StatusDetailCodeType::ABORT_SEARCH_EXCEEDED_MAX_TIME));

        $status->statusDetail[] = $detail;
        $this->assertFalse($e->containsCode(StatusDetailCodeType::ABORT_SEARCH_EXCEEDED_MAX_TIME));
        $this->assertSame($detail, $e->containsCode(StatusDetailCodeType::INVALID_KEY_OR_REF));
    }

    /**
     * @covers ::setMessage
     */
    public function testSetMessage(): void {
        $detail = new StatusDetail();
        $detail->message = 'asdf';
        $status = new Status();
        $status->statusDetail = [$detail];
        $e = new StatusFailure($status);
        $e->setMessage('Something extra');
        $this->assertSame(
            'Something extra' . PHP_EOL
            . 'Something went wrong with your request: '
            . json_encode($status->statusDetail),
            $e->getMessage()
        );
    }

}
