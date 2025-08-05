import { InputPropsWithoutGroup } from './models';

import { Box, Typography } from '@mui/material';
import { FormikValues, useFormikContext } from 'formik';
import { isNotEmpty, isNotNil } from 'ramda';
import { getInput } from '.';

const Grid = ({
  grid,
  hideInput
}: InputPropsWithoutGroup): JSX.Element | null => {
  const { values } = useFormikContext<FormikValues>();

  if (hideInput?.(values) ?? false) {
    return null;
  }

  const className = grid?.className || '';

  return (
    <div
      className={`${className} grid gap-3`}
      style={{
        gridTemplateColumns:
          className ? grid?.gridTemplateColumns || undefined : grid?.gridTemplateColumns ||
            `repeat(${grid?.columns.length || 1}, 1fr)`,
        alignItems: grid?.alignItems || 'flex-start'
      }}
    >
      {grid?.columns.map((field) => {
        const Input = getInput(field.type);

        const key =
          isNotNil(field.label) || isNotEmpty(field.label)
            ? field.label
            : field.additionalLabel;

        if (field.hideInput?.(values) ?? false) {
          return null;
        }

        return (
          <Box sx={{ width: '100%' }} key={key}>
            {field.additionalLabel && (
              <Typography
                sx={{ marginBottom: 0.5, color: 'primary.main' }}
                className={field?.additionalLabelClassName}
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
