import { SvgIcon, SvgIconProps } from '@mui/material';

import IconDowntime from './downtime.icon.svg?component';

const Downtime = (props: SvgIconProps): JSX.Element => (
  <SvgIcon component={IconDowntime} {...props} />
);

export default Downtime;
