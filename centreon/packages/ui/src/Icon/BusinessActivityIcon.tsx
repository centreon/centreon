import AccountTreeIcon from '@mui/icons-material/AccountTree';
import { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const BusinessActivityIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon
    Icon={AccountTreeIcon}
    dataTestId="BusinessActivityIcon"
    {...props}
  />
);
