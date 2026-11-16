# Driver push notifications

Push notifications are optional and do not alter the existing Operations publication, instruction acknowledgement or red/green read indicators.

## How it works

The driver opens the Daily Shift Check page and selects **Enable phone notifications**. This stores a browser push subscription for that authenticated driver. After an Operations publication, driver swap or daily van/comments save commits, the application checks the current published instructions against the driver's last acknowledgement. If unread, a single generic push is sent per driver and shift date. Further changes do not send more alerts until the driver confirms the current instructions. Clicking the push opens the existing driver page, and does not count as acknowledgement.

Push is dispatched from the Symfony kernel termination event after the HTTP response, not from a scheduler. Failures are logged and do not block Operations saves. No cron task, hosting environment variables, or separate IONOS settings are required. VAPID keys are generated on first driver config request and persisted in the application database. **Back up the database** to retain subscriptions across deployments.

## Deployment

Install the committed Composer dependencies and apply Doctrine migration Version20261116160000. This is a normal application database migration, not an IONOS configuration task. Test with a real driver account and compatible browser. iOS requires the PWA to be installed to the home screen before push permission can be granted. Phone/browser notification settings and network conditions may delay or suppress delivery.

## Safety

No browser content is cached by the service worker. Notifications are generic and never include route details or private manager comments. The existing read confirmation remains authoritative; a delivered push is not proof of reading. If push delivery fails, drivers still see and confirm instructions as before.
