#!/bin/bash

systemctl daemon-reload ||:
systemctl unmask dsmd.service ||:
systemctl preset dsmd.service ||:
systemctl enable dsmd.service ||:
systemctl restart dsmd.service ||:
