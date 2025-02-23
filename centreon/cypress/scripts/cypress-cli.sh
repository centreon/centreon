#!/bin/sh

docker run -it -v $PWD:/tmp -w /tmp --name=cypress cypress/included:13.17.0 --component --browser electron $@
