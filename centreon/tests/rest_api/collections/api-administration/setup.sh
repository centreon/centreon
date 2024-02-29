#!/bin/bash

mysql -hdb -uroot -pcentreon -e "UPDATE options SET options.value = 1 WHERE options.key = 'inheritance_mode'"
