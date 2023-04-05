import { ReactNode } from 'react';

import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()({
  container: {
    display: 'flex'
  },
  reverseIcon: {
    transform: 'rotate(180deg)'
  }
});

interface Props {
  icon: ReactNode;
  open: boolean;
}

const IconArrow = ({ open, icon }: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  return (
    <div className={cx(classes.container, { [classes.reverseIcon]: open })}>
      {icon}
    </div>
  );
};

export default IconArrow;
