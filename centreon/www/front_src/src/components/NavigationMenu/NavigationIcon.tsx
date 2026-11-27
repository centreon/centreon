import { SvgIcon, type SvgIconProps } from '@mui/material';

import type { ReactElement } from 'react';

/**
 * Base of the navigation pictograms, drawn on a 28px grid. MUI fills the root
 * svg with the current color, which would fill the outlined pictograms: the
 * root fill is reset and each shape sets its own fill or stroke.
 */
const NavigationIcon = ({
  className = '',
  viewBox = '0 0 28 28',
  ...props
}: SvgIconProps): ReactElement => (
  <SvgIcon className={`fill-none ${className}`} viewBox={viewBox} {...props} />
);

export default NavigationIcon;
