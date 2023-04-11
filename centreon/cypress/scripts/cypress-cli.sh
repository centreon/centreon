#!/bin/sh

docker run --shm-size 4gb -v "$PWD:/tmp" -w "/tmp" cypress/included:12.9.0 --component --browser=chrome $@