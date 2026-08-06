<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap;

use FoxCMS\Api\Core\Request;
use Throwable;

final class HardwareInventoryRegistrar
{
    public function __construct(
        private readonly BootstrapSettings $settings,
        private readonly Request $request,
    ) {
    }

    public function register(string $requestId): void
    {
        if (!$this->settings->hardwareInventoryEnabled()) {
            header('X-FoxesCraft-Hardware-Inventory: disabled');
            return;
        }

        $report = (new HardwareReportFactory($this->request))->fromHttpBody(
            $this->settings->hardwareInventoryMaxPayloadBytes(),
        );
        try {
            $inserted = (new HardwareInventoryRepository($this->settings->database()))->insertIfMissing($report);
            header('X-FoxesCraft-Hardware-Inventory: ' . ($inserted ? 'inserted' : 'existing'));
        } catch (Throwable $error) {
            header('X-FoxesCraft-Hardware-Inventory: unavailable');
            error_log(sprintf(
                '[FoxesCraft hardware inventory] request=%s system_hwid_prefix=%s exception=%s message=%s',
                $requestId,
                substr($report->systemHwid(), 0, 12),
                $error::class,
                $error->getMessage(),
            ));
        }
    }
}
