import { pick } from 'ramda';

import type { ComponentColumnProps } from '@centreon/ui';

import ChecksIcon from '../../ChecksIcon';

import IconColumn from './IconColumn';

const ChecksColumn = ({ row }: ComponentColumnProps): JSX.Element | null => {
  const icon = (
    <ChecksIcon
      {...pick(
        ['has_active_checks_enabled', 'has_passive_checks_enabled'],
        row
      )}
    />
  );

  return <IconColumn>{icon}</IconColumn>;
};

export default ChecksColumn;
