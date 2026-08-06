<?php

declare(strict_types=1);

namespace FoxCMS\Engine\User;

use \CsrfToken;
use \HttpException;
use \RewardClaimService;

final class UserRewardController
{
    public function __construct(
        private readonly \db $db,
        private readonly \Logger $logger,
        private readonly \HttpRequest $request,
        private readonly \UserSession $session,
        private readonly AuthenticatedUserGuard $guard,
        private readonly UserActionResponder $responder,
    ) {
    }

    public function getRewardOffer(): never
    {
        $userUuid = $this->guard->requireRewardAccess();

        $placement = trim($this->request->string('placement'));
        $service = new RewardClaimService($this->db, $this->logger);
        try {
            $offer = $service->publicOffer($placement, $userUuid);
        } catch (HttpException $error) {
            $this->responder->send([
                'message' => $error->getMessage(),
                'type' => 'error',
            ], $error->status());
        }

        $this->responder->send(['offer' => $offer]);
    }

    public function claimReward(): never
    {
        $userUuid = $this->guard->requireRewardAccess();
        CsrfToken::requireValid($this->request->csrfToken());

        $claimCode = trim($this->request->string('claimCode'));
        $offerPlacement = trim($this->request->string('offerPlacement'));
        $provided = (int)($claimCode !== '') + (int)($offerPlacement !== '');
        if ($provided !== 1) {
            $this->responder->send([
                'message' => 'Передайте криптографический код или расположение предложения с выпущенным placement-ключом.',
                'type' => 'error',
            ], 400);
        }

        $service = new RewardClaimService($this->db, $this->logger);
        try {
            $result = $claimCode !== ''
                ? $service->claim($claimCode, $userUuid)
                : $service->claimPublicOffer($offerPlacement, $userUuid);
        } catch (HttpException $error) {
            $this->responder->send([
                'message' => $error->getMessage(),
                'type' => 'error',
            ], $error->status());
        }

        $this->session->set('badges', $result['badgesJson'], true);
        if (isset($result['balanceJson'])) {
            $this->session->set('balance', $result['balanceJson'], true);
        }

        $reward = is_array($result['reward'] ?? null) ? $result['reward'] : [];
        $rewardTitle = trim((string)($reward['title'] ?? $reward['rewardName'] ?? 'Награда'));
        $alreadyClaimed = ($result['alreadyClaimed'] ?? false) === true;
        if ($alreadyClaimed) {
            $message = 'Награда «' . $rewardTitle . '» уже была получена этим профилем.';
        } else {
            $parts = [];
            $badge = is_array($result['badge'] ?? null) ? $result['badge'] : null;
            if (($result['badgeApplied'] ?? false) === true && $badge !== null) {
                $parts[] = 'добавлен бейдж «' . trim((string)($badge['badgeName'] ?? '')) . '»';
            }
            $currency = is_array($result['currency'] ?? null) ? $result['currency'] : null;
            if (($result['currencyApplied'] ?? false) === true && $currency !== null) {
                $parts[] = 'начислено ' . (int)($currency['amount'] ?? 0)
                    . ' ' . trim((string)($currency['currencyName'] ?? ''));
            }
            $message = 'Награда «' . $rewardTitle . '» получена.';
            if ($parts !== []) {
                $message .= ' ' . ucfirst(implode('; ', $parts)) . '.';
            }
        }

        $badges = json_decode((string)$result['badgesJson'], true);
        $this->responder->send([
            'message' => $message,
            'type' => $alreadyClaimed ? 'warning' : 'success',
            'alreadyClaimed' => $alreadyClaimed,
            'badgeApplied' => ($result['badgeApplied'] ?? false) === true,
            'currencyApplied' => ($result['currencyApplied'] ?? false) === true,
            'reward' => $reward,
            'badge' => $result['badge'] ?? null,
            'currency' => $result['currency'] ?? null,
            'offer' => $result['offer'] ?? null,
            'badges' => is_array($badges) ? $badges : [],
            'balance' => $result['balance'] ?? null,
        ]);
    }
}
