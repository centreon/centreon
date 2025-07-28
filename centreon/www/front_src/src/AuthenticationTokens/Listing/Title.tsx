import { ReactNode } from 'react';

import Typography, { TypographyProps } from '@mui/material/Typography';

interface Props {
  msg: ReactNode;
  variant?: TypographyProps['variant'];
}

const Title = ({ msg, variant = 'h6' }: Props): JSX.Element => {
  return <Typography variant={variant}>{msg}</Typography>;
};

export default Title;
