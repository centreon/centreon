import { ReactNode } from 'react';

import { Typography } from '@mui/material';

interface Props {
  children: ReactNode;
}

const Subtitle = ({ children }: Props): JSX.Element => (
  <Typography variant="subtitle1">
    <strong>{children}</strong>
  </Typography>
);

export default Subtitle;
