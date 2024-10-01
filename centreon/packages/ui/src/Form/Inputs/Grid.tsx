import { makeStyles } from 'tss-react/mui';

import { InputPropsWithoutGroup } from './models';

import { getInput } from '.';

interface StylesProps {
  alignItems?: string;
  columns?: number;
  gridTemplateColumns?: string;
  isColumnDirection?: boolean;
}

const useStyles = makeStyles<StylesProps>()(
  (theme, { columns, gridTemplateColumns, alignItems, isColumnDirection }) => ({
    gridFields: {
      alignItems: alignItems || 'flex-start',
      columnGap: theme.spacing(4),
      display: isColumnDirection ? 'flex' : 'grid',
      gridTemplateColumns: isColumnDirection
        ? undefined
        : gridTemplateColumns || `repeat(${columns}, 1fr)`,
      rowGap: theme.spacing(2)
    }
  })
);

const Grid = ({ grid }: InputPropsWithoutGroup): JSX.Element => {
  const { classes, cx } = useStyles({
    alignItems: grid?.alignItems,
    columns: grid?.columns.length,
    gridTemplateColumns: grid?.gridTemplateColumns,
    isColumnDirection: grid?.isColumnDirection
  });

  const className = grid?.className || '';

  return (
    <div className={cx(classes.gridFields, className)}>
      {grid?.columns.map((field) => {
        const Input = getInput(field.type);

        return <Input key={field.fieldName} {...field} />;
      })}
    </div>
  );
};

export default Grid;
