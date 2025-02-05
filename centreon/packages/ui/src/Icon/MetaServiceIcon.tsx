import SettingsInputSvideoIcon from '@mui/icons-material/SettingsInputSvideo';
import { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

export const MetaServiceIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon
    Icon={SettingsInputSvideoIcon}
    dataTestId="MetaServiceIcon"
    {...props}
  />
);
