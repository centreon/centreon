#!/bin/sh

# Only export configuration if dataset was loaded (CENTREON_DATASET=1)
# On fresh installations without dataset, there are no pollers to export
if [ "$CENTREON_DATASET" = "1" ]; then
  echo "Exporting poller configuration..."

  su www-data -s /bin/bash -c "php /usr/share/centreon/bin/centreon -u admin -p 'Centreon2025!' -a POLLERGENERATE -v 1" || {
    echo "WARNING: POLLERGENERATE failed - poller may not be configured yet"
    exit 0
  }

  su www-data -s /bin/bash -c "php /usr/share/centreon/bin/centreon -u admin -p 'Centreon2025!' -a CFGMOVE -v 1" || {
    echo "WARNING: CFGMOVE failed - poller may not be configured yet"
    exit 0
  }

  su www-data -s /bin/bash -c "php /usr/share/centreon/bin/centreon -u admin -p 'Centreon2025!' -a POLLERRESTART -v 1" || {
    echo "WARNING: POLLERRESTART failed - poller may not be configured yet"
    exit 0
  }

  echo "Poller configuration exported successfully"
else
  echo "Skipping poller export (CENTREON_DATASET != 1)"
  echo "To export pollers, configure them via the Centreon UI and run:"
  echo "  docker exec <container> su www-data -s /bin/bash -c 'php /usr/share/centreon/bin/centreon -u admin -p Centreon2025! -a POLLERGENERATE -v 1'"
fi

