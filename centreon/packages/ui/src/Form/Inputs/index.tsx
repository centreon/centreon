import { Fragment, useMemo } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { makeStyles } from 'tss-react/mui';

import { Divider, Typography } from '@mui/material';

import { GroupDirection } from '..';
import CollapsibleGroup from '../CollapsibleGroup';

import {
  T,
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
  keys,
  last,
  not,
  pluck,
  prop,
  propEq,
  reduce,
  sort,
  toPairs
} from 'ramda';
import Autocomplete from './Autocomplete';
import Checkbox from './Checkbox';
import CheckboxGroup from './CheckboxGroup';
import ConnectedAutocomplete from './ConnectedAutocomplete';
import Custom from './Custom';
import FieldsTable from './FieldsTable/FieldsTable';
import File from './File';
import Grid from './Grid';
import List from './List/List';
import LoadingSkeleton from './LoadingSkeleton';
import RadioInput from './Radio';
import SwitchInput from './Switch';
import TextInput from './Text';
import { Group, InputProps, InputPropsWithoutGroup, InputType } from './models';

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
  [equals(InputType.List) as (b: InputType) => boolean, always(List)],
  [equals(InputType.File) as (b: InputType) => boolean, always(File)],
  [T, always(TextInput)]
]);

interface StylesProps {
  groupDirection?: GroupDirection;
}

const useStyles = makeStyles<StylesProps>()((theme, { groupDirection }) => ({
  additionalLabel: {
    marginBottom: theme.spacing(0.5)
  },
  buttons: {
    columnGap: theme.spacing(2),
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'flex-end'
  },
  divider: {
    margin: equals(groupDirection, GroupDirection.Horizontal)
      ? theme.spacing(0, 2)
      : theme.spacing(2, 0)
  },
  groups: {
    display: 'flex',
    flexDirection: equals(groupDirection, GroupDirection.Horizontal)
      ? 'row'
      : 'column'
  },
  inputWrapper: {
    width: '100%'
  },
  inputs: {
    display: 'flex',
    flexDirection: 'column',
    marginTop: theme.spacing(1),
    rowGap: theme.spacing(2),
    marginBottom: theme.spacing(1)
  }
}));

interface Props {
  areGroupsOpen?: boolean;
  groupDirection?: GroupDirection;
  groups?: Array<Group>;
  groupsClassName?: string;
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
  areGroupsOpen
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ groupDirection });
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
    [visibleInputs]
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
    [visibleInputs]
  );

  const lastGroup = useMemo(() => last(sortedGroupNames), []);

  const normalizedInputsByGroup = (
    isEmpty(sortedInputsByGroup)
      ? [[null, visibleInputs]]
      : toPairs(sortedInputsByGroup)
  ) as Array<[string | null, Array<InputProps>]>;

  return (
    <div className={classes.groups}>
      {normalizedInputsByGroup.map(([groupName, groupedInputs], index) => {
        const hasGroupTitle = not(isNil(groupName));

        const groupProps = hasGroupTitle
          ? find(propEq(groupName, 'name'), groups)
          : ({} as Group);

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
                <div className={classes.inputs}>
                  {groupedInputs.map((inputProps) => {
                    if (isLoading) {
                      return (
                        <LoadingSkeleton
                          input={inputProps}
                          key={inputProps.label}
                        />
                      );
                    }

                    const Input = getInput(inputProps.type);

                    return (
                      <div
                        className={classes.inputWrapper}
                        key={inputProps.label}
                      >
                        {inputProps.additionalLabel && (
                          <Typography
                            className={cx(
                              classes.additionalLabel,
                              inputProps?.additionalLabelClassName
                            )}
                            variant="body1"
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
            {hasGroupTitle && not(equals(lastGroup, groupName as string)) && (
              <Divider
                flexItem
                className={classes.divider}
                orientation={
                  equals(groupDirection, GroupDirection.Horizontal)
                    ? 'vertical'
                    : 'horizontal'
                }
              />
            )}
          </Fragment>
        );
      })}
    </div>
  );
};

export default Inputs;
