<?php

declare(strict_types=1);

namespace NecLimDul\NetSuiteSearchIterator\Exception;

use NetSuite\Classes\Status;
use Throwable;

/**
 * Status failure exception.
 *
 * This exception is thrown when processing a request that "succeeds" but the
 * results report a failure. The code will be the failure reason as enumerated
 * by \NetSuite\Classes\StatusDetailCodeType.
 */
class StatusFailure extends \Exception {

    /**
     * @var \NetSuite\Classes\Status
     */
    private Status $status;

    public function __construct(Status $status, int $code = 0, string $message = '', Throwable $previous = NULL) {
        parent::__construct(
            '',
            $code,
            $previous
        );
        $this->status = $status;
        $this->setMessage($message);
    }

    /**
     * Get the status object.
     *
     * @return \NetSuite\Classes\Status
     */
    public function getStatus(): Status {
        return $this->status;
    }

    /**
     * Add a descriptive message for this failure.
     *
     * @param string $message
     */
    public function setMessage($message): void {
        if ($message) {
            $message .= PHP_EOL;
        }
        $message .= 'Something went wrong with your request: ' . json_encode($this->status->statusDetail);
        $this->message = $message;
    }

    /**
     * Check if the status details contain an error code.
     *
     * @param string $code
     *   The StatusDetailCode being searched for.
     *
     * @return \NetSuite\Classes\StatusDetail|false
     *   The first matched status detail or false.
     *
     * @see \NetSuite\Classes\StatusDetailCodeType for common values.
     */
    public function containsCode(string $code) {
        if ($this->status->statusDetail) {
            foreach ($this->status->statusDetail as $detail) {
                if ($detail->code == $code) {
                    return $detail;
                }
            }
        }
        return FALSE;
    }

}
