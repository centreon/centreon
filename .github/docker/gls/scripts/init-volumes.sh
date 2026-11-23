#!/bin/sh
# Central only, until phase 2e: prepares the engine/broker config volumes
# shared with fpm (www-data, gid 33) for the bootstrap local copy. Leaves them
# empty, so the engine waits for its first delivery. Every other volume is
# seeded from its image directory (contracts § 8.A).
set -eu
chown 901:33 /mnt/engine-etc
chown 902:33 /mnt/broker-etc
chmod 2775 /mnt/engine-etc /mnt/broker-etc
ls -lan /mnt/engine-etc /mnt/broker-etc
