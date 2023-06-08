#!/bin/sh

rm -rf /tmp/gorgone/*

# Run gorgone in background.
systemctl start gorgoned
