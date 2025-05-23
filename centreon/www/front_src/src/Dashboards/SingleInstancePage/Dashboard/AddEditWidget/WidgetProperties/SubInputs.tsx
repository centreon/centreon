import { useEffect, useMemo, useRef } from 'react';

import { useFormikContext } from 'formik';
import { equals, find, isEmpty, isNil, isNotNil, pluck, propEq } from 'ramda';

import { Box, Stack } from '@mui/material';

import { SubInput } from '../../../../../federatedModules/models';
import { Widget } from '../models';

import { useAtomValue } from 'jotai';
import { federatedWidgetsPropertiesAtom } from '../../../../../federatedModules/atoms';
import { getProperty } from './Inputs/utils';
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
    const federatedWidgetsProperties = useAtomValue(
      federatedWidgetsPropertiesAtom
    );
    
  
    const selectedWidget = find(
      propEq(values.moduleName, 'moduleName'),
      federatedWidgetsProperties || []
    );



    const getDefaultDisplayInput = ({method, property, value,displayValue})=>{

          if (equals(method, 'pluck')) {

          const valuesToCompare = pluck(property, value);
          
          return equals(valuesToCompare, displayValue)
        }

        return equals(displayValue, null) ? true  : equals(value, displayValue);
    }

    const getAdditionalInputField = (inputCondition) =>{
      
     const {singleResourceSelection, input} = inputCondition  || {}

        if(!equals(selectedWidget?.singleResourceSelection,singleResourceSelection) && singleResourceSelection) {
          const [data] = Array.isArray(value)?pluck(input, value):[]
          
           return data.length > 1 
          }

          return false
    } 

  
  const subInputsToDisplay =  subInputs?.filter(({ displayValue, customPropertyMatch }) => {

    if(!customPropertyMatch){

          return equals(displayValue, null) ? true  : equals(value, displayValue);
    }
   
     const {property, method, customTarget, inputTypeCondition} = customPropertyMatch

     if(!customTarget){

      const displayInput = getDefaultDisplayInput({method,displayValue,value,property})
      const additionalInputField = getAdditionalInputField(inputTypeCondition)

       return inputTypeCondition? additionalInputField && displayInput : displayInput
     }

     const {property:customTargetProperty} = customTarget;
     const externalTargetData = getProperty({obj: values,propertyName: customTargetProperty})

      const displayInput = getDefaultDisplayInput({method,displayValue,value:externalTargetData,property})
      const additionalInputField = getAdditionalInputField(inputTypeCondition)
       
      return inputTypeCondition? additionalInputField && displayInput : displayInput
      })

  


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
