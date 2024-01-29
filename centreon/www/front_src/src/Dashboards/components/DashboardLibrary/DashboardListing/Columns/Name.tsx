import { isNil } from 'ramda';
import { generatePath } from 'react-router';
import { Link } from 'react-router-dom';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import routeMap from '../../../../../reactRoutes/routeMap';
import { DashboardLayout } from '../../../../models';

import { useColumnStyles } from './useColumnStyles';

const Name = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles();

  const { name, role, id } = row;

  const isNestedRow = !isNil(role);

  if (isNestedRow) {
    return <Box />;
  }

  const linkToDashboard = generatePath(routeMap.dashboard, {
    dashboardId: id,
    layout: DashboardLayout.Library
  });

  return (
    <Link className={classes.name} to={linkToDashboard}>
      {name}
    </Link>
  );
};

export default Name;
