import { pick } from 'ramda';

import type { ComponentColumnProps } from '@centreon/ui';

import ChecksIcon from '../../ChecksIcon';

import IconColumn from './IconColumn';

const ChecksColumn = ({ row }: ComponentColumnProps): JSX.Element | null => {
  const icon = (
    <ChecksIcon {...pick(['active_checks', 'passive_checks'], row)} />
  );

  return <IconColumn>{icon}</IconColumn>;
};

export default ChecksColumn;
