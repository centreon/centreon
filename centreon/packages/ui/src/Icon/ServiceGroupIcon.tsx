import LinearScaleIcon from '@mui/icons-material/LinearScale';
import type { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const ServiceGroupIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon dataTestId="ServiceGroupIcon" Icon={LinearScaleIcon} {...props} />
);
