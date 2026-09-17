import { useTheme } from '@mui/material';

import Chip from '.';
import IconDowntime from './DowntimeIcon';

const DowntimeChip = (): JSX.Element => {
  const theme = useTheme();

  return (
    <Chip color={theme.palette.action.inDowntime} icon={<IconDowntime />} />
  );
};

export default DowntimeChip;
