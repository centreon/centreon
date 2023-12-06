#!/bin/bash

systemctl daemon-reload ||:
systemctl unmask dsmd.systemd ||:
systemctl preset dsmd.systemd ||:
systemctl enable dsmd.systemd ||:
systemctl restart dsmd.systemd ||:
