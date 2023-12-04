import { isNil } from 'ramda';
import { useNavigate } from 'react-router';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

const Name = ({ row }: ComponentColumnProps): JSX.Element => {
  const navigate = useNavigate();

  const { name, role, id } = row;

  const isNestedRow = !isNil(role);

  if (isNestedRow) {
    return <Box />;
  }

  const linkToPlaylist = (): void => {
    navigate(`/home/dashboards/playlists/${id}`);
  };

  return <Box onClick={linkToPlaylist}>{name}</Box>;
};

export default Name;
