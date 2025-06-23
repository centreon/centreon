#!/bin/sh

docker run -it -v $PWD:/tmp -w /tmp --name=cypress cypress/included:14.1.0 --component --browser electron $@
