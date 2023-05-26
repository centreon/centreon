import { ReactNode } from 'react';

import { useStyles } from './PageLayout.styles';

type PageLayoutHeaderProps = {
  children: Array<ReactNode> | ReactNode;
};

export const PageLayoutHeader = ({
  children
}: PageLayoutHeaderProps): JSX.Element => {
  const { classes } = useStyles();

  return <header className={classes.pageLayoutHeader}>{children}</header>;
};
