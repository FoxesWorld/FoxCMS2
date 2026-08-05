<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Application;

final class MaintenanceGate
{
    public function enforce(array $settings, ApplicationContext $context): void
    {
        if (\MaintenanceModePolicy::allows($settings, $context->session)) {
            return;
        }

        \RequestTelemetry::deviation(
            'maintenance.access_blocked',
            'maintenance_mode_active',
            'Request was blocked by maintenance mode.',
            'notice',
            ['maintenanceMode' => false],
            ['maintenanceMode' => true],
            [
                'component' => 'maintenance',
                'actorGroup' => $context->session->group(),
            ],
        );

        if ($context->request->isPost() || $context->request->expectsJson()) {
            \JsonResponse::send([
                'type' => 'warning',
                'code' => 'maintenance_mode',
                'message' => (string)($settings['title'] ?? 'Сайт находится на техническом обслуживании.'),
            ], 503, ['Retry-After' => '300']);
        }

        (new \MaintenanceRenderer($context->config, $settings, $context->session))->render();
    }
}
