import LinearScaleIcon from '@mui/icons-material/LinearScale';
import { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const ServiceGroupIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon Icon={LinearScaleIcon} dataTestId="ServiceGroupIcon" {...props} />
);
