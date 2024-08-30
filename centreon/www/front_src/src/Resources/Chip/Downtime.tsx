import { useTheme } from '@mui/material';

import Downtime from '../icons/Downtime';

import Chip from '.';

const DowntimeChip = (): JSX.Element => {
  const theme = useTheme();

  return (
    <Chip
      color={theme.palette.action.inDowntime}
      icon={<Downtime fontSize="small" />}
    />
  );
};

export default DowntimeChip;
