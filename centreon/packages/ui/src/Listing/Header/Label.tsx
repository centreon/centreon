import * as React from 'react';

import { makeStyles } from 'tss-react/mui';

import { Typography, TypographyProps } from '@mui/material';

const useStyles = makeStyles()(() => ({
  root: {
    fontWeight: 'bold'
  }
}));

interface Props {
  children: React.ReactNode;
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
