import * as React from 'react';

import { Typography } from '@material-ui/core';

interface Props {
  children: string;
}

const Option = ({ children }: Props): JSX.Element => {
  return <Typography variant="body1">{children}</Typography>;
};

export default Option;
