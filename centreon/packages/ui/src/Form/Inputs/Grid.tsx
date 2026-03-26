import { Box, Typography } from '@mui/material';

import { type FormikValues, useFormikContext } from 'formik';
import { isNotEmpty, isNotNil } from 'ramda';

import { getInput } from '.';
import type { InputPropsWithoutGroup } from './models';

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
        alignItems: grid?.alignItems || 'flex-start',
        gridTemplateColumns: className
          ? grid?.gridTemplateColumns || undefined
          : grid?.gridTemplateColumns ||
            `repeat(${grid?.columns.length || 1}, 1fr)`
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
          <Box key={key} sx={{ width: '100%' }}>
            {field.additionalLabel && (
              <Typography
                className={field?.additionalLabelClassName}
                sx={{ color: 'primary.main', marginBottom: 0.5 }}
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
