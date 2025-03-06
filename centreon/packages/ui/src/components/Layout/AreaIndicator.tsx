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
      {/* biome-ignore lint/a11y: */}
      <label>{name}</label>
      {children}
    </div>
  );
};

export { AreaIndicator };
