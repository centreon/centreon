import { Box, Typography } from '@mui/material';

import { type FormikValues, useFormikContext } from 'formik';
import { isNotEmpty, isNotNil } from 'ramda';
import type { CSSProperties } from 'react';

import { getInput } from '.';
import type { InputPropsWithoutGroup } from './models';

// The line is drawn left of every item, in the middle of the gap, and the grid
// clips what falls out of it: the items of the first column lose theirs, so it
// never shows in a single column nor left of the first one, whatever the number
// of columns, without knowing it. The clip keeps 4px around the grid so that
// nothing the items draw outside their box is cut.
const columnGap = 'calc(var(--spacing) * 8)';

const columnSeparatorGridStyle = {
  '--form-grid-column-gap': columnGap,
  columnGap: 'var(--form-grid-column-gap)',
  overflowClipMargin: '4px',
  overflowX: 'clip'
} as CSSProperties;

// The dashed line of an item: half a gap to its left, over the height of its
// content.
const ColumnSeparator = (): JSX.Element => (
  <span
    aria-hidden
    className="absolute inset-y-0 border-l border-dashed border-divider"
    data-column-separator
    style={{ left: 'calc(var(--form-grid-column-gap) / -2)' }}
  />
);

const Grid = ({
  grid,
  hideInput
}: InputPropsWithoutGroup): JSX.Element | null => {
  const { values } = useFormikContext<FormikValues>();

  if (hideInput?.(values) ?? false) {
    return null;
  }

  const className = grid?.className || '';
  const hasColumnSeparator = grid?.columnSeparator ?? false;

  return (
    <div
      className={`${className} grid gap-3`}
      style={{
        alignItems: grid?.alignItems || 'flex-start',
        gridTemplateColumns: className
          ? grid?.gridTemplateColumns || undefined
          : grid?.gridTemplateColumns ||
            `repeat(${grid?.columns.length || 1}, 1fr)`,
        ...(hasColumnSeparator && columnSeparatorGridStyle)
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

        const title = field.additionalLabel && (
          <Typography
            className={`mb-1 text-base font-bold text-text-primary ${field?.additionalLabelClassName || ''}`}
            variant="h6"
          >
            {field.additionalLabel}
          </Typography>
        );

        if (!hasColumnSeparator) {
          return (
            <Box key={key as string} sx={{ width: '100%' }}>
              {title}
              <Input {...field} />
            </Box>
          );
        }

        // The item stretches to the height of its row and its content fills
        // what its title leaves, so the line spans the whole row.
        return (
          <Box
            className="flex flex-col self-stretch"
            key={key as string}
            sx={{ width: '100%' }}
          >
            {title}
            <div className="relative flex-1">
              <ColumnSeparator />
              <Input {...field} />
            </div>
          </Box>
        );
      })}
    </div>
  );
};

export default Grid;
