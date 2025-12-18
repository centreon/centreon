import type { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageLayout.styles';

type PageLayoutBodyProps = {
  children: Array<ReactNode> | ReactNode;
  hasBackground?: boolean;
  className?: string;
};

export const PageLayoutBody = ({
  children,
  hasBackground = false,
  className
}: PageLayoutBodyProps): ReactElement => {
  const { classes, cx } = useStyles();

  return (
    <section
      className={cx(classes.pageLayoutBody, className)}
      data-has-actions={!!children?.length}
      data-has-background={hasBackground}
      id="page-body"
    >
      {children}
    </section>
  );
};
