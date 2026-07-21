import { Grain } from '@mui/icons-material';
import type { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const ServiceIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon dataTestId="ServiceIcon" Icon={Grain} {...props} />
);
