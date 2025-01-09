<?php

namespace Vormkracht10\Mails\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Vormkracht10\Mails\Contracts\MailDriverContract;
use Vormkracht10\Mails\Enums\EventType;

class ResendDriver extends MailDriver implements MailDriverContract
{
    public function registerWebhooks($components): void
    {
        // TODO: verify if we can hack the user endpoint to create a webhook
        return;

        $trackingConfig = (array) config('mails.logging.tracking');

        $triggers = [
            'Open' => [
                'Enabled' => (bool) $trackingConfig['opens'],
                'PostFirstOpenOnly' => false,
            ],
            'Click' => [
                'Enabled' => (bool) $trackingConfig['clicks'],
            ],
            'Delivery' => [
                'Enabled' => (bool) $trackingConfig['deliveries'],
            ],
            'Bounce' => [
                'Enabled' => (bool) $trackingConfig['bounces'],
                'IncludeContent' => (bool) $trackingConfig['bounces'],
            ],
            'SpamComplaint' => [
                'Enabled' => (bool) $trackingConfig['complaints'],
                'IncludeContent' => (bool) $trackingConfig['complaints'],
            ],
            'SubscriptionChange' => [
                'Enabled' => (bool) $trackingConfig['unsubscribes'],
            ],
        ];

        $webhookUrl = URL::signedRoute('mails.webhook', ['provider' => 'postmark']);

        $token = (string) config('services.postmark.token');

        $headers = [
            'Accept' => 'application/json',
            'X-Postmark-Server-Token' => $token,
        ];

        $broadcastStream = collect(Http::withHeaders($headers)->get('https://api.postmarkapp.com/message-streams')['MessageStreams'] ?? []);

        if ($broadcastStream->where('ID', 'broadcast')->count() === 0) {
            Http::withHeaders($headers)->post('https://api.postmarkapp.com/message-streams', [
                'ID' => 'broadcast',
                'Name' => 'Broadcasts',
                'Description' => 'Default Broadcast Stream',
            ]);
        } else {
            $components->info('Broadcast stream already exists');
        }

        $outboundWebhooks = collect(Http::withHeaders($headers)->get('https://api.postmarkapp.com/webhooks?MessageStream=outbound')['Webhooks'] ?? []);

        if ($outboundWebhooks->where('Url', $webhookUrl)->count() === 0) {
            $response = Http::withHeaders($headers)->post('https://api.postmarkapp.com/webhooks?MessageStream=outbound', [
                'Url' => $webhookUrl,
                'Triggers' => $triggers,
            ]);

            if ($response->ok()) {
                $components->info('Created Postmark webhook for outbound stream');
            } else {
                $components->error('Failed to create Postmark webhook for outbound stream');
            }
        } else {
            $components->info('Outbound webhook already exists');
        }

        $broadcastWebhooks = collect(Http::withHeaders($headers)->get('https://api.postmarkapp.com/webhooks?MessageStream=broadcast')['Webhooks'] ?? []);

        if ($broadcastWebhooks->where('Url', $webhookUrl)->count() === 0) {
            $response = Http::withHeaders($headers)->post('https://api.postmarkapp.com/webhooks?MessageStream=broadcast', [
                'Url' => $webhookUrl,
                'MessageStream' => 'broadcast',
                'Triggers' => $triggers,
            ]);

            if ($response->ok()) {
                $components->info('Created Postmark webhook for broadcast stream');
            } else {
                $components->error('Failed to create Postmark webhook for broadcast stream');
            }
        } else {
            $components->info('Broadcast webhook already exists');
        }
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        return true;
    }

    public function getUuidFromPayload(array $payload): ?string
    {
        return $payload['data']['email_id'];
    }

    protected function getTimestampFromPayload(array $payload): string
    {
        return $payload['data']['created_at'] ?? now();
    }

    public function eventMapping(): array
    {

        return [
            EventType::CLICKED->value => ['type' => 'email.clicked'],
            EventType::COMPLAINED->value => ['type' => 'email.complained'],
            EventType::DELIVERED->value => ['type' => 'email.delivered'],
            EventType::HARD_BOUNCED->value => ['type' => 'email.bounced'],
            EventType::OPENED->value => ['type' => 'email.opened'],
            EventType::SOFT_BOUNCED->value => ['type' => 'email.delivery_delayed'],
            EventType::UNSUBSCRIBED->value => ['type' => 'SubscriptionChange'],
        ];
    }

    public function dataMapping(): array
    {
        return [
            'ip_address' => 'data.click.ipAddress',
            'link' => 'data.click.link',
            'user_agent' => 'data.click.userAgent',
        ];
    }
}