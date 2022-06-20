import { useMemo } from 'react';

import * as R from 'ramda';
import { useTranslation } from 'react-i18next';

import { makeStyles } from '@mui/styles';
import { Divider, Typography } from '@mui/material';

import CollapsibleGroup from '../CollapsibleGroup';

import { Group, InputProps, InputPropsWithoutGroup, InputType } from './models';
import Autocomplete from './Autocomplete';
import SwitchInput from './Switch';
import RadioInput from './Radio';
import TextInput from './Text';
import ConnectedAutocomplete from './ConnectedAutocomplete';
import FieldsTable from './FieldsTable';
import Grid from './Grid';
import Custom from './Custom';
import LoadingSkeleton from './LoadingSkeleton';

export const getInput = R.cond<
  InputType,
  (props: InputPropsWithoutGroup) => JSX.Element | null
>([
  [
    R.equals(InputType.Switch) as (b: InputType) => boolean,
    R.always(SwitchInput),
  ],
  [
    R.equals(InputType.Radio) as (b: InputType) => boolean,
    R.always(RadioInput),
  ],
  [R.equals(InputType.Text) as (b: InputType) => boolean, R.always(TextInput)],
  [
    R.equals(InputType.SingleAutocomplete) as (b: InputType) => boolean,
    R.always(Autocomplete),
  ],
  [
    R.equals(InputType.MultiAutocomplete) as (b: InputType) => boolean,
    R.always(Autocomplete),
  ],
  [
    R.equals(InputType.Password) as (b: InputType) => boolean,
    R.always(TextInput),
  ],
  [
    R.equals(InputType.MultiConnectedAutocomplete) as (b: InputType) => boolean,
    R.always(ConnectedAutocomplete),
  ],
  [
    R.equals(InputType.SingleConnectedAutocomplete) as (
      b: InputType,
    ) => boolean,
    R.always(ConnectedAutocomplete),
  ],
  [
    R.equals(InputType.FieldsTable) as (b: InputType) => boolean,
    R.always(FieldsTable),
  ],
  [R.equals(InputType.Grid) as (b: InputType) => boolean, R.always(Grid)],
  [R.equals(InputType.Custom) as (b: InputType) => boolean, R.always(Custom)],
]);

const useStyles = makeStyles((theme) => ({
  additionalLabel: {
    marginBottom: theme.spacing(0.5),
  },
  buttons: {
    columnGap: theme.spacing(2),
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'flex-end',
  },
  group: {
    marginBottom: theme.spacing(2),
    marginTop: theme.spacing(2),
  },
  inputWrapper: { width: '100%' },
  inputs: {
    display: 'flex',
    flexDirection: 'column',
    marginTop: theme.spacing(1),
    rowGap: theme.spacing(2),
  },
}));

interface Props {
  groups?: Array<Group>;
  inputs: Array<InputProps>;
  isCollapsible: boolean;
  isLoading?: boolean;
}

const Inputs = ({
  inputs,
  groups = [],
  isLoading = false,
  isCollapsible,
}: Props): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

  const groupsName = R.pluck('name', groups);

  const inputsByGroup = useMemo(
    () =>
      R.groupBy(
        ({ group }) => R.find(R.equals(group), groupsName) as string,
        inputs,
      ),
    [inputs],
  );

  const sortedGroupNames = useMemo(() => {
    const sortedGroups = R.sort(R.ascend(R.prop('order')), groups);

    const usedGroups = R.filter(
      ({ name }) => R.any(R.equals(name), R.keys(inputsByGroup)),
      sortedGroups,
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
            inputsByGroup[value],
          ),
        }),
        {},
        sortedGroupNames,
      ),
    [inputs],
  );

  const lastGroup = useMemo(() => R.last(sortedGroupNames), []);

  const normalizedInputsByGroup = (
    R.isEmpty(sortedInputsByGroup)
      ? [[null, inputs]]
      : R.toPairs(sortedInputsByGroup)
  ) as Array<[string | null, Array<InputProps>]>;

  return (
    <div>
      {normalizedInputsByGroup.map(([groupName, groupedInputs], index) => {
        const hasGroupTitle = R.not(R.isNil(groupName));

        const groupProps = hasGroupTitle
          ? R.find(R.propEq('name', groupName), groups)
          : ({} as Group);

        const isFirstElement = R.equals(index, 0);

        return (
          <div key={groupName}>
            <div className={classes.group}>
              <CollapsibleGroup
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
                            className={classes.additionalLabel}
                            variant="body1"
                          >
                            {t(inputProps.additionalLabel)}
                          </Typography>
                        )}
                        <Input {...inputProps} />
                      </div>
                    );
                  })}
                </div>
              </CollapsibleGroup>
            </div>
            {hasGroupTitle &&
              R.not(R.equals(lastGroup, groupName as string)) && <Divider />}
          </div>
        );
      })}
    </div>
  );
};

export default Inputs;
