import AccountTreeIcon from '@mui/icons-material/AccountTree';
import type { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const BusinessActivityIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon
    dataTestId="BusinessActivityIcon"
    Icon={AccountTreeIcon}
    {...props}
  />
);
