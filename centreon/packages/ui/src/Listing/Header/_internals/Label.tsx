import { makeStyles } from 'tss-react/mui';

import { Typography, TypographyProps } from '@mui/material';
import { ReactNode } from 'react';

const useStyles = makeStyles()(() => ({
  root: {
    fontWeight: 'bold'
  }
}));

interface Props {
  children: ReactNode;
}

const HeaderLabel = ({
  children,
  className
}: Props & Pick<TypographyProps, 'className'>): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Typography
      className={className}
      classes={{
        root: classes.root
      }}
      variant="body2"
    >
      {children}
    </Typography>
  );
};

export default HeaderLabel;
