#!/bin/bash
set -e
mkdir -p /home/farid/.ssh
chmod 700 /home/farid/.ssh
if [ ! -e /home/farid/.ssh/dulogix_github_ed25519 ]; then
  ssh-keygen -q -t ed25519 -f /home/farid/.ssh/dulogix_github_ed25519 -N '' -C dulogix-github-asus
fi
chmod 600 /home/farid/.ssh/dulogix_github_ed25519
chmod 644 /home/farid/.ssh/dulogix_github_ed25519.pub
cat /home/farid/.ssh/dulogix_github_ed25519.pub
