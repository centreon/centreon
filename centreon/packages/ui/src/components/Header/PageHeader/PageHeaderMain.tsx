import { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageHeader.styles';

type PageHeaderMainProps = {
  children?: ReactNode;
};

const PageHeaderMain = ({ children }: PageHeaderMainProps): ReactElement => {
  const { classes } = useStyles();

  return <div className={classes.pageHeaderMain}>{children}</div>;
};

export { PageHeaderMain };
