import { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageHeader.styles';

type PageHeaderActionsProps = {
  children?: ReactNode;
};

const PageHeaderActions = ({
  children
}: PageHeaderActionsProps): ReactElement => {
  const { classes } = useStyles();

  return <header className={classes.pageHeaderActions}>{children}</header>;
};

export { PageHeaderActions };
