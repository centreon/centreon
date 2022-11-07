<<<<<<< HEAD
import { useTranslation } from 'react-i18next';
import { always, cond, lt, lte, map, not, pick, T } from 'ramda';
import { Responsive } from '@visx/visx';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';

import {
  Paper,
=======
import * as React from 'react';

import { useTranslation } from 'react-i18next';
import { always, cond, lt, lte, map, not, pick, T } from 'ramda';
import { Responsive } from '@visx/visx';

import {
  Paper,
  makeStyles,
>>>>>>> centreon/dev-21.10.x
  ButtonGroup,
  Button,
  useTheme,
  Tooltip,
  Theme,
<<<<<<< HEAD
} from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import { CreateCSSProperties } from '@mui/styles';
=======
} from '@material-ui/core';
import { CreateCSSProperties } from '@material-ui/styles';
>>>>>>> centreon/dev-21.10.x

import { useMemoComponent } from '@centreon/ui';

import { timePeriods } from '../../../Details/tabs/Graph/models';
import GraphOptions from '../ExportableGraphWithTimeline/GraphOptions';
<<<<<<< HEAD

import CustomTimePeriodPickers from './CustomTimePeriodPickers';
import {
  changeCustomTimePeriodDerivedAtom,
  changeSelectedTimePeriodDerivedAtom,
  customTimePeriodAtom,
  selectedTimePeriodAtom,
} from './timePeriodAtoms';
=======
import { useResourceContext } from '../../../Context';

import CustomTimePeriodPickers from './CustomTimePeriodPickers';
>>>>>>> centreon/dev-21.10.x

interface StylesProps {
  disablePaper: boolean;
}

const useStyles = makeStyles<Theme, StylesProps>((theme) => ({
  button: {
    fontSize: theme.typography.body2.fontSize,
<<<<<<< HEAD
    pointerEvents: 'all',
=======
>>>>>>> centreon/dev-21.10.x
  },
  buttonGroup: {
    alignSelf: 'center',
  },
  header: ({ disablePaper }): CreateCSSProperties<StylesProps> => ({
    alignItems: 'center',
    backgroundColor: disablePaper ? 'transparent' : 'undefined',
    border: disablePaper ? 'unset' : 'undefined',
    boxShadow: disablePaper ? 'unset' : 'undefined',
<<<<<<< HEAD
    columnGap: theme.spacing(2),
=======
    columnGap: `${theme.spacing(2)}px`,
>>>>>>> centreon/dev-21.10.x
    display: 'grid',
    gridTemplateColumns: `repeat(3, auto)`,
    justifyContent: 'center',
    padding: theme.spacing(1, 0.5),
  }),
}));

interface Props {
  disableGraphOptions?: boolean;
  disablePaper?: boolean;
  disabled?: boolean;
}

const timePeriodOptions = map(pick(['id', 'name', 'largeName']), timePeriods);

const TimePeriodButtonGroup = ({
  disabled = false,
  disableGraphOptions = false,
  disablePaper = false,
}: Props): JSX.Element => {
  const classes = useStyles({ disablePaper });
  const { t } = useTranslation();
  const theme = useTheme();

<<<<<<< HEAD
  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);
  const changeCustomTimePeriod = useUpdateAtom(
    changeCustomTimePeriodDerivedAtom,
  );
  const changeSelectedTimePeriod = useUpdateAtom(
    changeSelectedTimePeriodDerivedAtom,
  );
=======
  const {
    customTimePeriod,
    changeCustomTimePeriod,
    changeSelectedTimePeriod,
    selectedTimePeriod,
  } = useResourceContext();
>>>>>>> centreon/dev-21.10.x

  const translatedTimePeriodOptions = timePeriodOptions.map((timePeriod) => ({
    ...timePeriod,
    largeName: t(timePeriod.largeName),
    name: t(timePeriod.name),
  }));

  const changeDate = ({ property, date }): void =>
    changeCustomTimePeriod({ date, property });

  return useMemoComponent({
    Component: (
      <Responsive.ParentSize>
        {({ width }): JSX.Element => {
          const isCompact = lt(width, theme.breakpoints.values.sm);

          return (
            <Paper className={classes.header}>
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
                          selectedTimePeriod?.id === id
                            ? 'contained'
                            : 'outlined'
                        }
                        onClick={(): void => changeSelectedTimePeriod(id)}
                      >
                        {cond<number, string>([
                          [lte(theme.breakpoints.values.md), always(largeName)],
                          [T, always(name)],
                        ])(width)}
                      </Button>
                    </Tooltip>
                  ),
                  translatedTimePeriodOptions,
                )}
              </ButtonGroup>
              <CustomTimePeriodPickers
                acceptDate={changeDate}
                customTimePeriod={customTimePeriod}
                isCompact={isCompact}
              />
              {not(disableGraphOptions) && <GraphOptions />}
            </Paper>
          );
        }}
      </Responsive.ParentSize>
    ),
    memoProps: [
      disabled,
      disableGraphOptions,
      disablePaper,
      selectedTimePeriod?.id,
<<<<<<< HEAD
      customTimePeriod,
=======
>>>>>>> centreon/dev-21.10.x
    ],
  });
};

export default TimePeriodButtonGroup;
