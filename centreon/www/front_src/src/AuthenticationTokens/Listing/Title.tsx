import Typography, { TypographyProps } from '@mui/material/Typography';

import { ReactNode } from 'react';

interface Props {
  msg: ReactNode;
  variant?: TypographyProps['variant'];
}

const Title = ({ msg, variant = 'h6' }: Props): JSX.Element => {
  return <Typography variant={variant}>{msg}</Typography>;
};

export default Title;
