#!/bin/sh

docker run -it -v $PWD:/tmp -w /tmp cypress/included:13.17.0 --component --browser electron $@
