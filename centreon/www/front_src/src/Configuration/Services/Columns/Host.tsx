import { Link } from '@mui/material';

import { type ComponentColumnProps, HostIcon } from '@centreon/ui';

import type { JSX } from 'react';
import { Link as RouterLink } from 'react-router';

import type { ServiceHost } from '../models';
import { useHostLink } from '../useHostLink';
import ResourceName from './ResourceName';

const Host = ({
  row,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element => {
  const host = row.host as ServiceHost;
  const { href, to } = useHostLink(host.id);

  const routerProps = to ? { component: RouterLink, to } : { href };

  return (
    <Link
      // Pendo keys off this, so it must not follow the labels.
      data-testid={`service-host-link_${row.id}`}
      {...routerProps}
      // Only the link navigates: the rest of the cell opens the service.
      onClick={(event): void => event.stopPropagation()}
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
