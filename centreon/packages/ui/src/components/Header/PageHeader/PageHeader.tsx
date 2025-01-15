import { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageHeader.styles';

type PageHeaderProps = {
  children?: ReactNode;
};

const PageHeader = ({ children }: PageHeaderProps): ReactElement => {
  const { classes } = useStyles();

  return <section className={classes.pageHeader}>{children}</section>;
};

export { PageHeader };
