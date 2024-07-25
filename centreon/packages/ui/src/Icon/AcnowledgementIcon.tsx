import { Person } from '@mui/icons-material';
import { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const AcknowledgementIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon Icon={Person} dataTestId="AcknowledgementIcon" {...props} />
);
