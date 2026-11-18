#!/bin/sh
# gls: replaces the image's 05-engine-config.sh, which forces
# rpc_listen_address=0.0.0.0 (unauthenticated engine gRPC on the network).
# No gorgone needs it here: keep what the web exported (127.0.0.1 for vm pollers).
:
