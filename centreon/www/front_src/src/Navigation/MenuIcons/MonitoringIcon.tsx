import { SvgIcon, type SvgIconProps } from '@mui/material';

import type { ReactElement } from 'react';

// Telescope, from the navigation menu pictograms.
// The viewBox frames the drawing so that its largest side fills 20/24 of
// the icon, the size shared by all the menu icons.
const MonitoringIcon = (props: SvgIconProps): ReactElement => (
  <SvgIcon viewBox="-2.66 -2.41 28.64 28.64" {...props}>
    <g
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="1.3"
    >
      <g transform="rotate(-12 1.75 9)">
        <ellipse cx="1.75" cy="9" rx="0.9" ry="1.6" />
        <rect height="4" rx="0.6" width="1.9" x="3.7" y="7" />
        <rect height="5.6" rx="0.8" width="3.6" x="7.1" y="6.2" />
        <path d="M8.1 6.5q-0.7 2.5 0 5" />
        <path d="M20.2 4.4 12.6 5.3q-1.2 3.7 0 7.4l7.6 0.9" />
        <ellipse cx="20.2" cy="9" rx="2.6" ry="4.6" />
      </g>
      <path d="M9.2 13.8q3.3 2.2 6.8 0" />
      <path d="m9 16.8-2.2 4.9M12.8 17.3v5.9M16.2 16.8l3 4.8" />
    </g>
  </SvgIcon>
);

export default MonitoringIcon;
