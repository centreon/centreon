import { ReactElement } from 'react';

import GridItem from './Item/GridItem';
import { useStyles } from './Grid.styles';

interface Props {
  actions?: (row) => JSX.Element;
  checkable?: boolean;
  onItemClick?: (row) => void;
  rows: Array<unknown>;
}

const Grid = ({
  rows = [],
  actions,
  onItemClick,
  checkable
}: Props): ReactElement => {
  const { classes } = useStyles();

  return (
    <div className={classes.grid} data-variant="grid">
      {rows.map((row) => (
        <GridItem
          actions={actions?.(row)}
          checkable={checkable}
          description={row?.description ?? undefined}
          key={row?.id}
          title={row?.name}
          onClick={() => onItemClick?.(row)}
        />
      ))}
    </div>
  );
};

export default Grid;
