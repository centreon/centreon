import BusinessIcon from '@mui/icons-material/Business';
import type { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const HostGroupIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon dataTestId="HostGroupIcon" Icon={BusinessIcon} {...props} />
);
