import { Dns } from '@mui/icons-material';
import { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const HostIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon Icon={Dns} dataTestId="HostIcon" {...props} />
);
