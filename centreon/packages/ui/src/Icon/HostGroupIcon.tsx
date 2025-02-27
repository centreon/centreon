import BusinessIcon from '@mui/icons-material/Business';
import { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const HostGroupIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon Icon={BusinessIcon} dataTestId="HostGroupIcon" {...props} />
);
