<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\Request;

final class HardwareReportFactory
{
    private ?HardwareReportRequestReader $requestReader;

    public function __construct(
        ?Request $request = null,
        private readonly HardwareReportValidator $validator = new HardwareReportValidator(),
    ) {
        $this->requestReader = $request instanceof Request
            ? new HardwareReportRequestReader($request)
            : null;
    }

    public function fromHttpBody(int $maxBytes): HardwareReport
    {
        if (!$this->requestReader instanceof HardwareReportRequestReader) {
            throw new \LogicException('HTTP request is required to read a hardware report body.');
        }
        return $this->validator->validate($this->requestReader->read($maxBytes));
    }

    /** @param array<string, mixed> $input */
    public function fromArray(array $input): HardwareReport
    {
        return $this->validator->validate($input);
    }
}
