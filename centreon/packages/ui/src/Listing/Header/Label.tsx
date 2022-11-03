import * as React from 'react';

import { Typography, TypographyProps } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

const useStyles = makeStyles(() => ({
  root: {
    fontWeight: 'bold',
  },
}));

interface Props {
  children: React.ReactNode;
}

const HeaderLabel = ({
  children,
  className,
}: Props & Pick<TypographyProps, 'className'>): JSX.Element => {
  const classes = useStyles();

  return (
    <Typography
      className={className}
      classes={{
        root: classes.root,
      }}
      variant="body2"
    >
      {children}
    </Typography>
  );
};

export default HeaderLabel;
