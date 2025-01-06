import { ReactNode } from 'react';

import { useIconArrowStyles } from './check.styles';

interface Props {
  icon: ReactNode;
  open: boolean;
}

const IconArrow = ({ open, icon }: Props): JSX.Element => {
  const { classes, cx } = useIconArrowStyles();

  return (
    <div className={cx(classes.container, { [classes.reverseIcon]: open })}>
      {icon}
    </div>
  );
};

export default IconArrow;
