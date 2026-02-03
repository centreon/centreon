import { Dns } from '@mui/icons-material';
import type { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const HostIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon dataTestId="HostIcon" Icon={Dns} {...props} />
);
