import { makeStyles } from 'tss-react/mui';

import { InputPropsWithoutGroup } from './models';

import { Box, Typography } from '@mui/material';
import { FormikValues, useFormikContext } from 'formik';
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

const Grid = ({
  grid,
  hideInput
}: InputPropsWithoutGroup): JSX.Element | null => {
  const { classes, cx } = useStyles({
    alignItems: grid?.alignItems,
    columns: grid?.columns.length,
    gridTemplateColumns: grid?.gridTemplateColumns
  });

  const { values } = useFormikContext<FormikValues>();

  if (hideInput?.(values) ?? false) {
    return null;
  }

  const className = grid?.className || '';

  return (
    <div className={cx(classes.gridFields, className)}>
      {grid?.columns.map((field) => {
        const Input = getInput(field.type);

        if (field.hideInput?.(values) ?? false) {
          return null;
        }

        return (
          <Box sx={{ width: '100%' }} key={field.fieldName}>
            {field.additionalLabel && (
              <Typography
                sx={{ marginBottom: 0.5, color: 'primary.main' }}
                className={cx(field?.additionalLabelClassName)}
                variant="h6"
              >
                {field.additionalLabel}
              </Typography>
            )}
            <Input {...field} />
          </Box>
        );
      })}
    </div>
  );
};

export default Grid;
