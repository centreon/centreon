import { ReactNode } from 'react';

import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()({
  container: {
    display: 'flex'
  }
});

interface Props {
  iconDown: ReactNode;
  iconUp: ReactNode;
  open: boolean;
}

const IconArrow = ({ open, iconUp, iconDown }: Props): JSX.Element => {
  const { classes } = useStyles();

  return <div className={classes.container}>{open ? iconUp : iconDown}</div>;
};

export default IconArrow;
