import { type ComponentColumnProps, ServiceIcon } from '@centreon/ui';

import type { JSX } from 'react';

import type { Icon } from '../models';
import ResourceName from './ResourceName';

const Name = ({
  row,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element => (
  <ResourceName
    DefaultIcon={ServiceIcon}
    icon={row.icon as Icon | null | undefined}
    name={row.name as string}
    renderEllipsisTypography={renderEllipsisTypography}
  />
);

export default Name;
