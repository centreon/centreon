import { SvgIcon, type SvgIconProps } from '@mui/material';

import type { ReactElement } from 'react';

// The viewBox frames the drawing so that its largest side fills 20/24 of
// the icon, like the menu icons.
const PanelIcon = (props: SvgIconProps): ReactElement => (
  <SvgIcon viewBox="0.32 0.12 23.76 23.76" {...props}>
    <rect
      fill="none"
      height="18"
      rx="1.6"
      stroke="currentColor"
      strokeWidth="1.8"
      width="18"
      x="3.2"
      y="3"
    />
    <path d="M9.3 3v18" fill="none" stroke="currentColor" strokeWidth="1.8" />
    <path d="M12.6 6.9v9.6l4.6-4.8z" />
  </SvgIcon>
);

export default PanelIcon;
