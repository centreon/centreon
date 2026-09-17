import { Typography } from '@mui/material';

import { ReactNode } from 'react';

interface Props {
  children: ReactNode;
}

const Title = ({ children }: Props): JSX.Element => (
  <Typography color="primary" variant="h6">
    {children}
  </Typography>
);

export default Title;
