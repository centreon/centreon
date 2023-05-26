import { ReactElement, ReactNode } from 'react';

import { useStyles } from './AreaIndicator.styles';

type AreaIndicatorProps = {
  children?: ReactNode | Array<ReactNode>;
  depth?: number;
  height?: number | string;
  name: string;
  width?: number | string;
};

const AreaIndicator = ({
  children,
  name = 'area',
  width = '100%',
  height = '100%',
  depth = 0
}: AreaIndicatorProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <div
      className={classes.areaIndicator}
      data-depth={depth}
      style={{ height, width }}
    >
      {/* eslint-disable-next-line jsx-a11y/label-has-associated-control */}
      <label>{name}</label>
      {children}
    </div>
  );
};

export { AreaIndicator };
