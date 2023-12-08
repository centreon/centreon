import { isNil } from 'ramda';
import { generatePath, useNavigate } from 'react-router';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import routeMap from '../../../../../reactRoutes/routeMap';
import { DashboardLayout } from '../../../../models';

const Name = ({ row }: ComponentColumnProps): JSX.Element => {
  const navigate = useNavigate();

  const { name, role, id } = row;

  const isNestedRow = !isNil(role);

  if (isNestedRow) {
    return <Box />;
  }

  const linkToPlaylist = (): void => {
    navigate(
      generatePath(routeMap.dashboard, {
        dashboardId: id,
        layout: DashboardLayout.Playlist
      })
    );
  };

  return <Box onClick={linkToPlaylist}>{name}</Box>;
};

export default Name;
