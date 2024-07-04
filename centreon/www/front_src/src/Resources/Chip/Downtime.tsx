import { useTheme } from '@mui/material';

import { DowntimeIcon } from '@centreon/ui';

import Chip from '.';

const DowntimeChip = (): JSX.Element => {
  const theme = useTheme();

  return (
    <Chip
      color={theme.palette.action.inDowntime}
      icon={<DowntimeIcon fontSize="small" />}
    />
  );
};

export default DowntimeChip;
