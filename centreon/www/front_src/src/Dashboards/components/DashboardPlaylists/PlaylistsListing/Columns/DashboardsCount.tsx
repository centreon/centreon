import { isNil } from 'ramda';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

const DashboardsCount = ({ row }: ComponentColumnProps): JSX.Element => {
  const { role, dashboards } = row;

  const isNestedRow = !isNil(role);

  if (isNestedRow) {
    return <Box />;
  }

  const dashboardsCount = dashboards?.length || 0;

  return <Box>{dashboardsCount}</Box>;
};

export default DashboardsCount;
