import { ReactNode } from 'react';

import Typography, { TypographyTypeMap } from '@mui/material/Typography';

interface Props {
  msg: ReactNode;
  variant?: TypographyTypeMap['props']['variant'];
}

const Title = ({ msg, variant = 'h6' }: Props): JSX.Element => {
  return <Typography variant={variant}>{msg}</Typography>;
};

export default Title;
