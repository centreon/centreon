import type { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageHeader.styles';

type PageHeaderMainProps = {
  children?: ReactNode;
};

const PageHeaderMain = ({ children }: PageHeaderMainProps): ReactElement => {
  const { classes, cx } = useStyles();

  return (
    <div className={cx(classes.pageHeaderMain, 'min-w-0')}>{children}</div>
  );
};

export { PageHeaderMain };
