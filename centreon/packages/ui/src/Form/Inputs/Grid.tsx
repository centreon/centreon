import { makeStyles } from 'tss-react/mui';

import { InputPropsWithoutGroup } from './models';

import { getInput } from '.';

interface StylesProps {
  alignItems?: string;
  columns?: number;
  gridTemplateColumns?: string;
}

const useStyles = makeStyles<StylesProps>()(
  (theme, { columns, gridTemplateColumns, alignItems }) => ({
    gridFields: {
      alignItems: alignItems || 'flex-start',
      columnGap: theme.spacing(4),
      display: 'grid',
      gridTemplateColumns: gridTemplateColumns || `repeat(${columns}, 1fr)`,
      rowGap: theme.spacing(2)
    }
  })
);

const Grid = ({ grid }: InputPropsWithoutGroup): JSX.Element => {
  const { classes } = useStyles({
    alignItems: grid?.alignItems,
    columns: grid?.columns.length,
    gridTemplateColumns: grid?.gridTemplateColumns
  });

  return (
    <div className={classes.gridFields}>
      {grid?.columns.map((field) => {
        const Input = getInput(field.type);

        return <Input key={field.fieldName} {...field} />;
      })}
    </div>
  );
};

export default Grid;
