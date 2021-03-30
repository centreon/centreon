import * as React from 'react';

import { makeStyles, Typography, TypographyProps } from '@material-ui/core';

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
      variant="body2"
      className={className}
      classes={{
        root: classes.root,
      }}
    >
      {children}
    </Typography>
  );
};

export default HeaderLabel;
