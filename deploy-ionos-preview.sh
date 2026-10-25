#!/usr/bin/env bash
set -euo pipefail
cd /home/farid/projects/Dulogix
export RSYNC_RSH='ssh -i /home/farid/projects/Dulogix/.secrets/dulogix_ionos_deploy -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=/home/farid/.ssh/known_hosts_ionos_dulogix'
rsync -rtvz --no-perms --no-owner --no-group --exclude='.env.local' --exclude='.env.*.local' bin config migrations public src templates vendor composer.json composer.lock symfony.lock .env su746574@access-5021721738.webspace-host.com:/home/www/public/Dulogix-app/
