import type { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

const icon = (
  <g>
    <path d="M6.94873 13.4287V19.1429L11.8975 22L16.8463 19.1429V13.4287L11.8975 10.5716L6.94873 13.4287Z" stroke="currentColor" strokeWidth="2" fill="none" />
    <path d="M2 4.85709V10.5713L6.9488 13.4284L11.8976 10.5713V4.85709L6.9488 2L2 4.85709Z" stroke="currentColor" strokeWidth="2" fill="none" />
    <path d="M11.8975 4.85709V10.5713L16.8463 13.4284L21.7951 10.5713V4.85709L16.8463 2L11.8975 4.85709Z" stroke="currentColor" strokeWidth="2" fill="none" />
  </g>
)



export const BusinessActivityIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon
    dataTestId="BusinessActivityIcon"
    Icon={icon}
    {...props}
  />
);
