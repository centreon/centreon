import { useAtomValue, useSetAtom } from 'jotai';
import { always, cond, lt, lte, map, not, pick, T } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Button, ButtonGroup, Paper, Tooltip, useTheme } from '@mui/material';

import { useDebounce, useMemoComponent, ParentSize } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { timePeriods } from '../../../Details/tabs/Graph/models';
import GraphOptions from '../ExportableGraphWithTimeline/GraphOptions';

import CustomTimePeriodPickers from './CustomTimePeriodPickers';
import {
  changeCustomTimePeriodDerivedAtom,
  changeSelectedTimePeriodDerivedAtom,
  customTimePeriodAtom,
  selectedTimePeriodAtom
} from './timePeriodAtoms';

interface StylesProps {
  disablePaper: boolean;
}

const useStyles = makeStyles<StylesProps>()((theme, { disablePaper }) => ({
  button: {
    fontSize: theme.typography.body2.fontSize,
    pointerEvents: 'all'
  },
  buttonGroup: {
    alignSelf: 'center',
    height: '100%'
  },
  header: {
    alignItems: 'center',
    backgroundColor: disablePaper ? 'transparent' : 'undefined',
    border: disablePaper ? 'unset' : 'undefined',
    boxShadow: disablePaper ? 'unset' : 'undefined',
    columnGap: theme.spacing(2),
    display: 'grid',
    gridTemplateColumns: `repeat(4, auto)`,
    gridTemplateRows: '1fr',
    justifyContent: 'center',
    padding: theme.spacing(1, 0.5)
  }
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
  disablePaper = false
}: Props): JSX.Element => {
  const { classes } = useStyles({ disablePaper });
  const { t } = useTranslation();
  const theme = useTheme();
  const debouncedChangeDate = useDebounce({
    functionToDebounce: ({ property, date }): void =>
      changeCustomTimePeriod({ date, property }),
    wait: 500
  });

  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);
  const { themeMode } = useAtomValue(userAtom);

  const changeCustomTimePeriod = useSetAtom(changeCustomTimePeriodDerivedAtom);
  const changeSelectedTimePeriod = useSetAtom(
    changeSelectedTimePeriodDerivedAtom
  );

  const translatedTimePeriodOptions = timePeriodOptions.map((timePeriod) => ({
    ...timePeriod,
    largeName: t(timePeriod.largeName),
    name: t(timePeriod.name)
  }));

  return useMemoComponent({
    Component: (
      <ParentSize>
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
                          [T, always(name)]
                        ])(width)}
                      </Button>
                    </Tooltip>
                  ),
                  translatedTimePeriodOptions
                )}
              </ButtonGroup>
              <CustomTimePeriodPickers
                acceptDate={debouncedChangeDate}
                customTimePeriod={customTimePeriod}
                isCompact={isCompact}
              />
              {not(disableGraphOptions) && <GraphOptions />}
            </Paper>
          );
        }}
      </ParentSize>
    ),
    memoProps: [
      customTimePeriod,
      disabled,
      disableGraphOptions,
      disablePaper,
      selectedTimePeriod?.id,
      themeMode
    ]
  });
};

export default TimePeriodButtonGroup;
