import SettingsInputSvideoIcon from '@mui/icons-material/SettingsInputSvideo';
import type { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const MetaServiceIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon
    dataTestId="MetaServiceIcon"
    Icon={SettingsInputSvideoIcon}
    {...props}
  />
);
