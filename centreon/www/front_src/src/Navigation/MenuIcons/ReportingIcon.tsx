import { SvgIcon, type SvgIconProps } from '@mui/material';

import type { ReactElement } from 'react';

// Rising chart in a circle, from the "Recherche Picto menu de nav" design.
const ReportingIcon = (props: SvgIconProps): ReactElement => (
  <SvgIcon viewBox="0 0 24 24" {...props}>
    <g
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="1.8"
    >
      <path d="M15.08 3.54A9 9 0 1 0 20.46 8.92" />
      <path d="m6 15.5 3.5-3.5 3 2.5 6.5-8.5" />
      <path d="M15.5 6H19v3.5" />
    </g>
  </SvgIcon>
);

export default ReportingIcon;
