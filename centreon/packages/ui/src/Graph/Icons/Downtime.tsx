import { SvgIcon, SvgIconProps } from '@mui/material';

import { ReactComponent as IconDowntime } from '../../@assets/icons/downtime.icon.svg';

const Downtime = (props: SvgIconProps): JSX.Element => (
  <SvgIcon component={IconDowntime} {...props} />
);

export default Downtime;
