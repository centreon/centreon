import { SvgIconProps } from '@mui/material';
import { ReactElement } from 'react';
import BaseIcon from './BaseIcon';

const icon = (
  <g transform="translate(2 2)">
    <path
      d="M19.468,8.107,17.2,6.8l2.264-1.307a.8.8,0,0,0-.8-1.386L16.4,5.414V2.8a.8.8,0,1,0-1.6,0V5.414L12.54,4.107a.8.8,0,1,0-.8,1.386L14,6.8,11.74,8.107a.8.8,0,1,0,.8,1.386L14.8,8.186V10.8a.8.8,0,1,0,1.6,0V8.186l2.264,1.307a.789.789,0,0,0,.4.107.8.8,0,0,0,.4-1.493"
      transform="translate(-1.868)"
    />
    <path
      d="M5.2,20.4a3.2,3.2,0,1,1,3.2-3.2,3.2,3.2,0,0,1-3.2,3.2m0-4.8a1.6,1.6,0,1,0,1.6,1.6,1.6,1.6,0,0,0-1.6-1.6"
      transform="translate(0 -2.4)"
    />
  </g>
);

export const RegexIcon = (props: SvgIconProps): ReactElement => (
  <BaseIcon {...props} Icon={icon} dataTestId="RegexIcon" />
);
