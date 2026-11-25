import { SvgIcon, type SvgIconProps } from '@mui/material';

import type { ReactElement } from 'react';

// Four tiles, from the navigation menu pictograms.
// The viewBox frames the drawing so that its largest side fills 20/24 of
// the icon, the size shared by all the menu icons.
const DashboardsIcon = (props: SvgIconProps): ReactElement => (
  <SvgIcon viewBox="-2.4 -2.4 28.8 28.8" {...props}>
    <g
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="1.8"
    >
      <rect height="12.2" rx="1.4" width="9.2" x="0.9" y="0.9" />
      <rect height="5.7" rx="1.4" width="8.7" x="14.4" y="0.9" />
      <rect height="6.2" rx="1.4" width="9.2" x="0.9" y="16.9" />
      <rect height="12.2" rx="1.4" width="8.7" x="14.4" y="10.9" />
    </g>
  </SvgIcon>
);

export default DashboardsIcon;
