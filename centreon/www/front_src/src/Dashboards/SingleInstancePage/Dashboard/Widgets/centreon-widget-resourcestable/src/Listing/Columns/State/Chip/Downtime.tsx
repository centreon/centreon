import { useTheme } from '@mui/material';

import IconDowntime from './DowntimeIcon';

import Chip from '.';

const DowntimeChip = (): JSX.Element => {
  const theme = useTheme();

  return (
    <Chip color={theme.palette.action.inDowntime} icon={<IconDowntime />} />
  );
};

export default DowntimeChip;
