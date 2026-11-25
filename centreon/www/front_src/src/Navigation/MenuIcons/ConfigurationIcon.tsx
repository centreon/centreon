import { SvgIcon, type SvgIconProps } from '@mui/material';

import type { ReactElement } from 'react';

// Eight-tooth gear, from the navigation menu pictograms.
// The viewBox frames the drawing so that its largest side fills 20/24 of
// the icon, the size shared by all the menu icons.
const ConfigurationIcon = (props: SvgIconProps): ReactElement => (
  <SvgIcon viewBox="-0.72 -0.72 25.44 25.44" {...props}>
    <g
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="1.8"
    >
      <path d="M9.89 5.11L10.15 2.48A9.7 9.7 0 0 1 13.85 2.48L14.11 5.11A7.2 7.2 0 0 1 15.38 5.64L15.38 5.64L17.42 3.96A9.7 9.7 0 0 1 20.04 6.58L18.36 8.62A7.2 7.2 0 0 1 18.89 9.89L18.89 9.89L21.52 10.15A9.7 9.7 0 0 1 21.52 13.85L18.89 14.11A7.2 7.2 0 0 1 18.36 15.38L18.36 15.38L20.04 17.42A9.7 9.7 0 0 1 17.42 20.04L15.38 18.36A7.2 7.2 0 0 1 14.11 18.89L14.11 18.89L13.85 21.52A9.7 9.7 0 0 1 10.15 21.52L9.89 18.89A7.2 7.2 0 0 1 8.62 18.36L8.62 18.36L6.58 20.04A9.7 9.7 0 0 1 3.96 17.42L5.64 15.38A7.2 7.2 0 0 1 5.11 14.11L5.11 14.11L2.48 13.85A9.7 9.7 0 0 1 2.48 10.15L5.11 9.89A7.2 7.2 0 0 1 5.64 8.62L5.64 8.62L3.96 6.58A9.7 9.7 0 0 1 6.58 3.96L8.62 5.64A7.2 7.2 0 0 1 9.89 5.11Z" />
      <circle cx="12" cy="12" r="3.5" />
    </g>
  </SvgIcon>
);

export default ConfigurationIcon;
