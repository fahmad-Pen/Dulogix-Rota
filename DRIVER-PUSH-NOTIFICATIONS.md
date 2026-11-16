# Driver push notifications

Optional phone alerts; the existing Operations save, instruction read and red/green status are unchanged.

## Setup

1. Deploy updated Composer dependencies and run migration Version20261116160000.
2. Generate a VAPID key pair securely with Minishlink WebPush VAPID::createVapidKeys(). Never commit the private key.
3. Configure DULOGIX_VAPID_PUBLIC_KEY and DULOGIX_VAPID_PRIVATE_KEY on the server.
4. Schedule /usr/bin/php8.5 bin/console app:send-driver-push --env=prod --no-debug in the project directory every 30 seconds. A one-minute scheduler may delay delivery by 30 to 90 seconds.
5. Test real Android and iOS home-screen installation and notification permission.

## Behaviour

The worker checks published instructions against existing driver acknowledgements. It waits at least 30 seconds and sends one alert per unread cycle, regardless of subsequent changes. A new acknowledgement resets the cycle, including when further changes arrive before the next scan. No instruction data is cached. Push delivery does not affect normal Operations or driver confirmation workflows.

## Rollback

Stop the scheduled task and remove the VAPID configuration to disable alerts. Keep the existing PWA and acknowledgement system.
