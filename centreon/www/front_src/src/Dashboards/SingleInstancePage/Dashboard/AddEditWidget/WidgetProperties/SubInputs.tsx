import { useMemo, useRef } from 'react';

import { useFormikContext } from 'formik';
import { equals, isEmpty, isNil, isNotNil } from 'ramda';

import { Box, Stack } from '@mui/material';

import { SubInput } from '../../../../../federatedModules/models';
import { Widget } from '../models';

import { DefaultComponent, propertiesInputType } from './useWidgetInputs';

interface SubInputsProps {
  children: JSX.Element;
  subInputs?: Array<SubInput>;
  value?: unknown;
}

const SubInputs = ({
  subInputs,
  value,
  children
}: SubInputsProps): JSX.Element => {
  const previousSubInputsToDisplayRef = useRef<Array<SubInput> | undefined>();
  const { setFieldValue, values } = useFormikContext<Widget>();

  const subInputsToDisplay = useMemo(
    () => subInputs?.filter(({ displayValue }) => equals(value, displayValue)),
    [subInputs, value]
  );

  const hasSubInputs = useMemo(
    () => !isEmpty(subInputsToDisplay) && !isNil(subInputsToDisplay),
    [subInputsToDisplay]
  );

  const hasRowDirection = useMemo(
    () => subInputsToDisplay?.some(({ direction }) => equals(direction, 'row')),
    [subInputsToDisplay]
  );

  if (!equals(previousSubInputsToDisplayRef.current, subInputsToDisplay)) {
    subInputsToDisplay?.forEach(({ input, name }) => {
      if (isNotNil(values.options[name])) {
        return;
      }

      setFieldValue(`options.${name}`, input.defaultValue, false);
    });
    previousSubInputsToDisplayRef.current = subInputsToDisplay;
  }

  return (
    <Stack
      alignItems={hasRowDirection ? 'flex-end' : undefined}
      direction={hasRowDirection ? 'row' : 'column'}
      gap={hasSubInputs ? 1.5 : 0}
    >
      <Box sx={{ pr: 6 }}>{children}</Box>
      {hasSubInputs && (
        <Stack
          alignItems={hasRowDirection ? 'center' : undefined}
          direction={hasRowDirection ? 'row' : 'column'}
          gap={1.5}
        >
          {subInputsToDisplay?.map(({ input, name }) => {
            const Component =
              propertiesInputType[input.type] || DefaultComponent;

            return (
              <Component
                key={input.label}
                propertyName={name}
                {...input}
                isInGroup
              />
            );
          })}
        </Stack>
      )}
    </Stack>
  );
};

export default SubInputs;
