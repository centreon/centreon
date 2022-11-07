<<<<<<< HEAD
import { SvgIcon, SvgIconProps } from '@mui/material';
=======
import React from 'react';

import { SvgIcon, SvgIconProps } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import { ReactComponent as IconDowntime } from './downtime.icon.svg';

const Downtime = (props: SvgIconProps): JSX.Element => (
  <SvgIcon component={IconDowntime} {...props} />
);

export default Downtime;
