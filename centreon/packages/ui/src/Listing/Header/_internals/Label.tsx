import { Typography, TypographyProps } from '@mui/material';
import { ReactNode } from 'react';

interface Props {
  children: ReactNode;
}

const HeaderLabel = ({
  children,
  className
}: Props & Pick<TypographyProps, 'className'>): JSX.Element => {
  return (
    <Typography className={`font-bold ${className}`} variant="body2">
      {children}
    </Typography>
  );
};

export default HeaderLabel;
