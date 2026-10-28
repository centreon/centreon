import { Divider, Typography } from '@mui/material';

import { type FormikValues, useFormikContext } from 'formik';
import {
  always,
  any,
  ascend,
  cond,
  equals,
  filter,
  find,
  groupBy,
  isEmpty,
  isNil,
  isNotEmpty,
  isNotNil,
  keys,
  last,
  not,
  pluck,
  prop,
  propEq,
  reduce,
  sort,
  T,
  toPairs
} from 'ramda';
import { Fragment, useMemo } from 'react';

import { GroupDirection } from '..';
import CollapsibleGroup from '../CollapsibleGroup';
import Autocomplete from './Autocomplete';
import Checkbox from './Checkbox';
import CheckboxGroup from './CheckboxGroup';
import ConnectedAutocomplete from './ConnectedAutocomplete';
import Custom from './Custom';
import ExclusiveCheckboxGroup from './ExclusiveCheckboxGroup';
import FieldsTable from './FieldsTable/FieldsTable';
import File from './File';
import Grid from './Grid';
import List from './List/List';
import LoadingSkeleton from './LoadingSkeleton';
import {
  type Group,
  type InputProps,
  type InputPropsWithoutGroup,
  InputType
} from './models';
import RadioInput from './Radio';
import SegmentedButtons from './SegmentedButtons';
import { SubgroupDivider } from './SubGroupDivider';
import SwitchInput from './Switch';
import TextInput from './Text';

export const getInput = cond<
  Array<InputType>,
  (props: InputPropsWithoutGroup) => JSX.Element | null
>([
  [equals(InputType.Switch) as (b: InputType) => boolean, always(SwitchInput)],
  [equals(InputType.Radio) as (b: InputType) => boolean, always(RadioInput)],
  [
    equals(InputType.SingleAutocomplete) as (b: InputType) => boolean,
    always(Autocomplete)
  ],
  [
    equals(InputType.MultiAutocomplete) as (b: InputType) => boolean,
    always(Autocomplete)
  ],
  [
    equals(InputType.MultiConnectedAutocomplete) as (b: InputType) => boolean,
    always(ConnectedAutocomplete)
  ],
  [
    equals(InputType.SingleConnectedAutocomplete) as (b: InputType) => boolean,
    always(ConnectedAutocomplete)
  ],
  [
    equals(InputType.FieldsTable) as (b: InputType) => boolean,
    always(FieldsTable)
  ],
  [equals(InputType.Grid) as (b: InputType) => boolean, always(Grid)],
  [equals(InputType.Custom) as (b: InputType) => boolean, always(Custom)],
  [equals(InputType.Checkbox) as (b: InputType) => boolean, always(Checkbox)],
  [
    equals(InputType.CheckboxGroup) as (b: InputType) => boolean,
    always(CheckboxGroup)
  ],
  [
    equals(InputType.ExclusiveCheckboxGroup) as (b: InputType) => boolean,
    always(ExclusiveCheckboxGroup)
  ],
  [
    equals(InputType.SegmentedButtons) as (b: InputType) => boolean,
    always(SegmentedButtons)
  ],
  [equals(InputType.List) as (b: InputType) => boolean, always(List)],
  [equals(InputType.File) as (b: InputType) => boolean, always(File)],
  [equals(InputType.Text) as (b: InputType) => boolean, always(TextInput)],
  [
    equals(InputType.Divider) as (b: InputType) => boolean,
    always(SubgroupDivider)
  ],
  [T, always(TextInput)]
]);

interface Props {
  areGroupsOpen?: boolean;
  groupDirection?: GroupDirection;
  groups?: Array<Group>;
  groupsClassName?: string;
  inputsClassName?: string;
  inputs: Array<InputProps>;
  isCollapsible: boolean;
  isLoading?: boolean;
}

const Inputs = ({
  inputs,
  groups = [],
  isLoading = false,
  isCollapsible,
  groupDirection,
  groupsClassName,
  areGroupsOpen,
  inputsClassName
}: Props): JSX.Element => {
  const isHorizontal = equals(groupDirection, GroupDirection.Horizontal);
  const formikContext = useFormikContext<FormikValues>();

  const groupsName = pluck('name', groups);

  const visibleInputs = filter(
    ({ hideInput }) =>
      formikContext ? !hideInput?.(formikContext?.values) || false : true,
    inputs
  );

  const inputsByGroup = useMemo(
    () =>
      groupBy(
        ({ group }) => find(equals(group), groupsName) as string,
        visibleInputs
      ),
    [visibleInputs, groupsName]
  ) as Record<string, Array<InputProps>>;

  const sortedGroupNames = useMemo(() => {
    const sortedGroups = sort(ascend(prop('order')), groups);

    const usedGroups = filter(
      ({ name }) => any(equals(name), keys(inputsByGroup)),
      sortedGroups
    );

    return pluck('name', usedGroups);
  }, [inputsByGroup, groups]);

  const sortedInputsByGroup = useMemo(
    () =>
      reduce<string, Record<string, Array<InputProps>>>(
        (acc, value) => ({
          ...acc,
          [value]: sort(
            (a, b) => (b?.required ? 1 : 0) - (a?.required ? 1 : 0),
            inputsByGroup[value]
          )
        }),
        {},
        sortedGroupNames
      ),
    [inputsByGroup, sortedGroupNames]
  );

  const lastGroup = useMemo(() => last(sortedGroupNames), [sortedGroupNames]);

  const normalizedInputsByGroup = (
    isEmpty(sortedInputsByGroup)
      ? [[null, visibleInputs]]
      : toPairs(sortedInputsByGroup)
  ) as Array<[string | null, Array<InputProps>]>;

  return (
    <div className={`flex ${isHorizontal ? 'flex-row' : 'flex-col'}`}>
      {normalizedInputsByGroup.map(([groupName, groupedInputs], index) => {
        const hasGroupTitle = not(isNil(groupName));

        const groupProps = hasGroupTitle
          ? find(propEq(groupName, 'name'), groups)
          : ({} as Group);

        const hasGroupDivider = !groups[index]?.isDividerHidden;
        const isFirstElement = areGroupsOpen || equals(index, 0);

        return (
          <Fragment key={groupName}>
            <div>
              <CollapsibleGroup
                className={groupsClassName}
                defaultIsOpen={isFirstElement}
                group={groupProps}
                hasGroupTitle={hasGroupTitle}
                isCollapsible={isCollapsible}
              >
                <div
                  className={`flex flex-col my-4 gap-y-4 ${inputsClassName || ''}`}
                >
                  {groupedInputs.map((inputProps) => {
                    const key =
                      isNotNil(inputProps.label) || isNotEmpty(inputProps.label)
                        ? inputProps.label
                        : inputProps.additionalLabel;

                    if (isLoading) {
                      return (
                        <LoadingSkeleton
                          input={inputProps}
                          key={key as string}
                        />
                      );
                    }

                    const Input = getInput(inputProps.type);

                    return (
                      <div className="w-full" key={key as string}>
                        {inputProps.additionalLabel && (
                          <Typography
                            className={`mb-1 text-base font-bold text-text-primary ${inputProps?.additionalLabelClassName || ''}`}
                            variant="h6"
                          >
                            {inputProps.additionalLabel}
                          </Typography>
                        )}
                        <div className={inputProps?.inputClassName || ''}>
                          <Input {...inputProps} />
                        </div>
                      </div>
                    );
                  })}
                </div>
              </CollapsibleGroup>
            </div>
            {hasGroupDivider &&
              hasGroupTitle &&
              not(equals(lastGroup, groupName as string)) && (
                <Divider
                  className={isHorizontal ? 'my-0 mx-4' : 'my-4 mx-0'}
                  flexItem
                  orientation={isHorizontal ? 'vertical' : 'horizontal'}
                />
              )}
          </Fragment>
        );
      })}
    </div>
  );
};

export default Inputs;
