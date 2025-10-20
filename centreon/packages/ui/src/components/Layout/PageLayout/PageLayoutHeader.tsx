import { ReactNode } from 'react';

import { useStyles } from './PageLayout.styles';

type PageLayoutHeaderProps = {
  children: Array<ReactNode> | ReactNode;
  className?: string;
};

export const PageLayoutHeader = ({
  children,
  className
}: PageLayoutHeaderProps): JSX.Element => {
  const { classes, cx } = useStyles();

  return (
    <header className={cx(classes.pageLayoutHeader, className)} id="header">
      {children}
    </header>
  );
};
