import { Fragment, useMemo } from 'react';

import * as R from 'ramda';
import { makeStyles } from 'tss-react/mui';
import { FormikValues, useFormikContext } from 'formik';

import { Divider, Typography } from '@mui/material';

import CollapsibleGroup from '../CollapsibleGroup';
import { GroupDirection } from '..';

import { Group, InputProps, InputPropsWithoutGroup, InputType } from './models';
import Autocomplete from './Autocomplete';
import SwitchInput from './Switch';
import RadioInput from './Radio';
import TextInput from './Text';
import ConnectedAutocomplete from './ConnectedAutocomplete';
import FieldsTable from './FieldsTable/FieldsTable';
import Grid from './Grid';
import CheckboxGroup from './CheckboxGroup';
import Checkbox from './Checkbox';
import Custom from './Custom';
import LoadingSkeleton from './LoadingSkeleton';

export const getInput = R.cond<
  Array<InputType>,
  (props: InputPropsWithoutGroup) => JSX.Element | null
>([
  [
    R.equals(InputType.Switch) as (b: InputType) => boolean,
    R.always(SwitchInput)
  ],
  [
    R.equals(InputType.Radio) as (b: InputType) => boolean,
    R.always(RadioInput)
  ],
  [
    R.equals(InputType.SingleAutocomplete) as (b: InputType) => boolean,
    R.always(Autocomplete)
  ],
  [
    R.equals(InputType.MultiAutocomplete) as (b: InputType) => boolean,
    R.always(Autocomplete)
  ],
  [
    R.equals(InputType.MultiConnectedAutocomplete) as (b: InputType) => boolean,
    R.always(ConnectedAutocomplete)
  ],
  [
    R.equals(InputType.SingleConnectedAutocomplete) as (
      b: InputType
    ) => boolean,
    R.always(ConnectedAutocomplete)
  ],
  [
    R.equals(InputType.FieldsTable) as (b: InputType) => boolean,
    R.always(FieldsTable)
  ],
  [R.equals(InputType.Grid) as (b: InputType) => boolean, R.always(Grid)],
  [R.equals(InputType.Custom) as (b: InputType) => boolean, R.always(Custom)],
  [
    R.equals(InputType.Checkbox) as (b: InputType) => boolean,
    R.always(Checkbox)
  ],
  [
    R.equals(InputType.CheckboxGroup) as (b: InputType) => boolean,
    R.always(CheckboxGroup)
  ],
  [R.T, R.always(TextInput)]
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
    margin: R.equals(groupDirection, GroupDirection.Horizontal)
      ? theme.spacing(0, 2)
      : theme.spacing(2, 0)
  },
  groups: {
    display: 'flex',
    flexDirection: R.equals(groupDirection, GroupDirection.Horizontal)
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
    rowGap: theme.spacing(2)
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

  const groupsName = R.pluck('name', groups);

  const visibleInputs = R.filter(
    ({ hideInput }) =>
      formikContext ? !hideInput?.(formikContext?.values) || false : true,
    inputs
  );

  const inputsByGroup = useMemo(
    () =>
      R.groupBy(
        ({ group }) => R.find(R.equals(group), groupsName) as string,
        visibleInputs
      ),
    [visibleInputs]
  ) as Record<string, Array<InputProps>>;

  const sortedGroupNames = useMemo(() => {
    const sortedGroups = R.sort(R.ascend(R.prop('order')), groups);

    const usedGroups = R.filter(
      ({ name }) => R.any(R.equals(name), R.keys(inputsByGroup)),
      sortedGroups
    );

    return R.pluck('name', usedGroups);
  }, []);

  const sortedInputsByGroup = useMemo(
    () =>
      R.reduce<string, Record<string, Array<InputProps>>>(
        (acc, value) => ({
          ...acc,
          [value]: R.sort(
            (a, b) => (b?.required ? 1 : 0) - (a?.required ? 1 : 0),
            inputsByGroup[value]
          )
        }),
        {},
        sortedGroupNames
      ),
    [visibleInputs]
  );

  const lastGroup = useMemo(() => R.last(sortedGroupNames), []);

  const normalizedInputsByGroup = (
    R.isEmpty(sortedInputsByGroup)
      ? [[null, visibleInputs]]
      : R.toPairs(sortedInputsByGroup)
  ) as Array<[string | null, Array<InputProps>]>;

  return (
    <div className={classes.groups}>
      {normalizedInputsByGroup.map(([groupName, groupedInputs], index) => {
        const hasGroupTitle = R.not(R.isNil(groupName));

        const groupProps = hasGroupTitle
          ? R.find(R.propEq('name', groupName), groups)
          : ({} as Group);

        const isFirstElement = areGroupsOpen || R.equals(index, 0);

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
            {hasGroupTitle &&
              R.not(R.equals(lastGroup, groupName as string)) && (
                <Divider
                  flexItem
                  className={classes.divider}
                  orientation={
                    R.equals(groupDirection, GroupDirection.Horizontal)
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
