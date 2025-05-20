import { useEffect, useMemo, useRef } from 'react';

import { useFormikContext } from 'formik';
import { equals, isEmpty, isNil, isNotNil, pluck } from 'ramda';

import { Box, Stack } from '@mui/material';

import { SubInput } from '../../../../../federatedModules/models';
import { Widget } from '../models';

import { DefaultComponent, propertiesInputType } from './useWidgetInputs';

interface SubInputsProps {
  children: JSX.Element;
  subInputs?: Array<SubInput>;
  value?: unknown;
  subInputsDelimiter?: string;
}

const SubInputs = ({
  subInputs,
  value,
  children,
  subInputsDelimiter
}: SubInputsProps): JSX.Element => {
  const previousSubInputsToDisplayRef = useRef<Array<SubInput> | undefined>();
  const { setFieldValue, values } = useFormikContext<Widget>();



  // a verifierrrrrrrrrrrrrr
  const subInputsToDisplay = useMemo(
    () =>
      subInputs?.filter(({ displayValue, customPropertyMatch }) => {
        if (equals(customPropertyMatch?.method, 'pluck')) {

          console.log('i should be heeeeeeeeeeeeeeeeeeeeeeeere')
          const valuesToCompare = pluck(customPropertyMatch?.property, value);

          console.log({valuesToCompare,displayValue})

          return equals(valuesToCompare, displayValue);
        }

        return equals(displayValue, null) ? true : equals(value, displayValue);
      }),
    [subInputs, value]
  );

  console.log({subInputs, subInputsToDisplay})

  const hasSubInputs = useMemo(
    () => !isEmpty(subInputsToDisplay) && !isNil(subInputsToDisplay),
    [subInputsToDisplay]
  );

  const hasRowDirection = useMemo(
    () => subInputsToDisplay?.some(({ direction }) => equals(direction, 'row')),
    [subInputsToDisplay]
  );

  useEffect(() => {
    if (!equals(previousSubInputsToDisplayRef.current, subInputsToDisplay)) {
      subInputsToDisplay?.forEach(({ input, name }) => {
        if (isNotNil(values.options[name])) {
          return;
        }

        setFieldValue(`options.${name}`, input.defaultValue, false);
      });
      previousSubInputsToDisplayRef.current = subInputsToDisplay;
    }
  }, [previousSubInputsToDisplayRef.current, subInputsToDisplay]);

  return (
    <Stack
      alignItems={hasRowDirection ? 'flex-end' : undefined}
      direction={hasRowDirection ? 'row' : 'column'}
      gap={hasSubInputs ? 1.5 : 0}
      sx={{ pr: 1, justifyContent: 'space-between', flexWrap: 'wrap' }}
    >
      <Box sx={{ pr: 2 }}>{children}</Box>
      {hasSubInputs && (
        <Stack
          alignItems={hasRowDirection ? 'center' : undefined}
          direction={hasRowDirection ? 'row' : 'column'}
          gap={1.5}
        >
          {subInputsToDisplay?.map(({ input, name }, index) => {
            const isLast = equals(index, subInputsToDisplay.length - 1);
            const Component =
              propertiesInputType[input.type] || DefaultComponent;

            return (
              <>
                <Component
                  key={input.label}
                  propertyName={name}
                  {...input}
                  isInGroup
                />
                {!isLast && subInputsDelimiter}
              </>
            );
          })}
        </Stack>
      )}
    </Stack>
  );
};

export default SubInputs;
