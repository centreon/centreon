import { useEffect } from 'react';

import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { T, always, cond, equals, lte, map, pick } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Button, ButtonGroup, Tooltip, useTheme } from '@mui/material';

import { LabelDay, timePeriods } from './models';
import {
  changeSelectedTimePeriodDerivedAtom,
  selectedTimePeriodAtom
} from './timePeriodAtoms';

const useStyles = makeStyles()((theme) => ({
  button: {
    fontSize: theme.typography.body2.fontSize,
    pointerEvents: 'all'
  },
  buttonGroup: {
    alignSelf: 'center',
    height: '100%'
  }
}));

interface Props {
  disabled?: boolean;
  getSelectedTimePeriod?: (data) => void;
  labelButtonGroups?: Array<LabelDay>;
  width: number;
}

const timePeriodOptions = map(pick(['id', 'name', 'largeName']), timePeriods);

const TimePeriodButtonGroup = ({
  disabled = false,
  labelButtonGroups,
  width,
  getSelectedTimePeriod
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const theme = useTheme();

  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);

  const changeSelectedTimePeriod = useUpdateAtom(
    changeSelectedTimePeriodDerivedAtom
  );

  const getLabel = ({ timePeriod, label, key }): string => {
    return labelButtonGroups && equals(timePeriod.id, Object.keys(label)[0])
      ? label[timePeriod.id][key]
      : timePeriod[key];
  };

  const translatedTimePeriodOptions = timePeriodOptions.map(
    (timePeriod, index) => ({
      ...timePeriod,
      largeName: getLabel({
        key: 'largeName',
        label: labelButtonGroups?.[index],
        timePeriod
      }),
      name: getLabel({
        key: 'name',
        label: labelButtonGroups?.[index],
        timePeriod
      })
    })
  );

  useEffect(() => {
    getSelectedTimePeriod?.(selectedTimePeriod);
  }, [selectedTimePeriod]);

  return (
    <ButtonGroup
      className={classes.buttonGroup}
      color="primary"
      component="span"
      disabled={disabled}
      size="small"
    >
      {map(
        ({ id, name, largeName }) => (
          <Tooltip key={name} placement="top" title={largeName}>
            <Button
              className={classes.button}
              component="span"
              data-testid={id}
              variant={
                equals(selectedTimePeriod?.id, id) ? 'contained' : 'outlined'
              }
              onClick={(): void => changeSelectedTimePeriod(id)}
            >
              {cond<number, string>([
                [lte(theme.breakpoints.values.md), always(largeName)],
                [T, always(name)]
              ])(width)}
            </Button>
          </Tooltip>
        ),
        translatedTimePeriodOptions
      )}
    </ButtonGroup>
  );
};

export default TimePeriodButtonGroup;
