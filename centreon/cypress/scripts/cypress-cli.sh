#!/bin/sh

docker run --shm-size 4gb -v "$PWD:/tmp" -w "/tmp" cypress/included:13.6.2 --component --browser=chrome $@