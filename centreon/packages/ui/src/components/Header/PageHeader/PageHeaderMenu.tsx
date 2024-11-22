import { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageHeader.styles';

type PageHeaderMenuProps = {
  children?: ReactNode;
};

const PageHeaderMenu = ({ children }: PageHeaderMenuProps): ReactElement => {
  const { classes } = useStyles();

  return <nav className={classes.pageHeaderMenu}>{children}</nav>;
};

export { PageHeaderMenu };
