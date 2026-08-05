<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

final class HardwareReportFactory
{
    public function __construct(
        private readonly HardwareReportRequestReader $requestReader = new HardwareReportRequestReader(),
        private readonly HardwareReportValidator $validator = new HardwareReportValidator(),
    ) {
    }

    public function fromHttpBody(int $maxBytes): HardwareReport
    {
        return $this->validator->validate($this->requestReader->read($maxBytes));
    }

    /** @param array<string, mixed> $input */
    public function fromArray(array $input): HardwareReport
    {
        return $this->validator->validate($input);
    }
}
