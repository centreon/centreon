import { SvgIcon, type SvgIconProps } from '@mui/material';

import type { ReactElement } from 'react';

// Telescope, from the "Recherche Picto menu de nav" design.
const MonitoringIcon = (props: SvgIconProps): ReactElement => (
  <SvgIcon viewBox="0 0 24 24" {...props}>
    <g
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="1.8"
    >
      <g transform="rotate(-20 12 8)">
        <rect height="5" rx="2.5" width="14" x="5" y="5" />
        <rect height="2.6" rx="1" width="2.6" x="2" y="6.2" />
        <path d="M15 5v5" />
      </g>
      <path d="M11.5 11.5 6.5 21M11.5 11.5l5 9.5M11.5 11.5V21" />
    </g>
  </SvgIcon>
);

export default MonitoringIcon;
