import { Theme } from '@mui/material';
import { CreateCSSProperties, makeStyles } from '@mui/styles';

import { InputPropsWithoutGroup } from './models';

import { getInput } from '.';

const useStyles = makeStyles<Theme, { columns; gridTemplateColumns }>(
  (theme) => ({
    gridFields: ({ columns, gridTemplateColumns }): CreateCSSProperties => ({
      alignItems: 'flex-start',
      columnGap: theme.spacing(2),
      display: 'grid',
      gridTemplateColumns: gridTemplateColumns || `repeat(${columns}, 1fr)`,
    }),
  }),
);

const Grid = ({ grid }: InputPropsWithoutGroup): JSX.Element => {
  const classes = useStyles({
    columns: grid?.columns.length,
    gridTemplateColumns: grid?.gridTemplateColumns,
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
