import { SvgIcon, type SvgIconProps } from '@mui/material';

import type { ReactElement } from 'react';

// Rising chart in a circle, from the navigation menu pictograms.
// The viewBox frames the drawing so that its largest side fills 20/24 of
// the icon, the size shared by all the menu icons.
const ReportingIcon = (props: SvgIconProps): ReactElement => (
  <SvgIcon viewBox="-1.54 -1.96 27.42 27.42" {...props}>
    <g
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="1.2"
    >
      <path
        d="M2.63 14.44A9.75 9.75 0 0 1 18.52 4.50M21.42 9.23A9.75 9.75 0 0 1 4.99 18.52"
        strokeWidth="1.9"
      />
      <path d="M2.75 16.50 6.42 11.00M8.63 10.35 13.56 12.48M15.60 12.02 20.68 6.16" />
      <circle cx="2.25" cy="17.25" r="0.9" />
      <circle cx="7.25" cy="9.75" r="1.5" />
      <circle cx="14.75" cy="13" r="1.3" />
      <circle cx="21.6" cy="5.1" r="1.4" />
    </g>
  </SvgIcon>
);

export default ReportingIcon;
