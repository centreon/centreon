import type { SvgIconProps } from '@mui/material';

import type { ReactElement } from 'react';

import NavigationIcon from './NavigationIcon';

// Expand pictogram attached to MON-201237, mirrored to collapse.
const PanelIcon = (props: SvgIconProps): ReactElement => (
  <NavigationIcon viewBox="0 0 24 24" {...props}>
    <path
      d="M12.5556 7.55556V16.4444L17 12L12.5556 7.55556ZM4.22222 22C3.61111 22 3.08796 21.7824 2.65278 21.3472C2.21759 20.912 2 20.3889 2 19.7778V4.22222C2 3.61111 2.21759 3.08796 2.65278 2.65278C3.08796 2.21759 3.61111 2 4.22222 2H19.7778C20.3889 2 20.912 2.21759 21.3472 2.65278C21.7824 3.08796 22 3.61111 22 4.22222V19.7778C22 20.3889 21.7824 20.912 21.3472 21.3472C20.912 21.7824 20.3889 22 19.7778 22H4.22222ZM8 20V4H4V20H8ZM10 20H20V4H10V20Z"
      fill="currentColor"
    />
  </NavigationIcon>
);

export default PanelIcon;
