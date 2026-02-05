#!/bin/sh

docker run -it --name cypress -v $PWD/../..:/tmp -w /tmp/packages/ui --name=cypress cypress/included:14.1.0 --component --browser electron $@
docker rm cypress
