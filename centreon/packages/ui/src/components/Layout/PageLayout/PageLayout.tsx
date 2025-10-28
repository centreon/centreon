import { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageLayout.styles';

type PageLayoutProps = {
  children: Array<ReactNode> | ReactNode;
  variant?: 'default' | 'fixed-header';
  className?: string;
};

export const PageLayout = ({
  children,
  variant = 'default',
  className
}: PageLayoutProps): ReactElement => {
  const { classes, cx } = useStyles();

  return (
    <section
      className={cx(classes.pageLayout, className)}
      data-variant={variant}
      id="page"
    >
      {children}
    </section>
  );
};
