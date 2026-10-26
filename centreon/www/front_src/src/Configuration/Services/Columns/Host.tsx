import { Link } from '@mui/material';

import { type ComponentColumnProps, HostIcon } from '@centreon/ui';

import type { JSX } from 'react';

import type { ServiceHost } from '../models';
import { getHostConfigurationUrl } from '../utils';
import ResourceName from './ResourceName';

const Host = ({
  row,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element => {
  const host = row.host as ServiceHost;

  return (
    <Link
      // Pendo keys off this, so it must not follow the labels.
      data-testid={`service-host-link_${row.id}`}
      href={getHostConfigurationUrl(host.id)}
      underline="hover"
    >
      <ResourceName
        DefaultIcon={HostIcon}
        icon={host.icon}
        name={host.name}
        renderEllipsisTypography={renderEllipsisTypography}
      />
    </Link>
  );
};

export default Host;
