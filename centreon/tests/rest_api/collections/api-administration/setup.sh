#!/bin/bash

mysql -hdb -uroot -pcentreon centreon -e "UPDATE options SET options.value = 1 WHERE options.key = 'inheritance_mode'"
