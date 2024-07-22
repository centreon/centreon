import { Grain } from '@mui/icons-material';
import { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const ServiceIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon Icon={Grain} dataTestId="ServiceIcon" {...props} />
);
