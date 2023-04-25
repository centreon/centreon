import { Responsive } from '@visx/visx';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { T, always, cond, equals, lt, lte, map, pick } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Button, ButtonGroup, Paper, Tooltip, useTheme } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import CustomTimePeriodPickers from './CustomTimePeriodPickers';
import { LabelTimePeriodPicker, LabelDay, timePeriods } from './models';
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
  height?: number;
  labelButtonGroups?: Array<LabelDay>;
  labelTimePeriodPicker: LabelTimePeriodPicker;
}

const timePeriodOptions = map(pick(['id', 'name', 'largeName']), timePeriods);

const TimePeriodButtonGroup = ({
  disabled = false,
  disableGraphOptions = false,
  disablePaper = false,
  height = 100,
  labelButtonGroups,
  labelTimePeriodPicker = { labelEnd: 'To', labelFrom: 'From' }
}: Props): JSX.Element => {
  const { classes } = useStyles({ disablePaper });
  const theme = useTheme();

  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);
  const { themeMode } = useAtomValue(userAtom);

  const changeCustomTimePeriod = useUpdateAtom(
    changeCustomTimePeriodDerivedAtom
  );
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

  const changeDate = ({ property, date }): void =>
    changeCustomTimePeriod({ date, property });

  return useMemoComponent({
    Component: (
      <div style={{ height }}>
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
                            [
                              lte(theme.breakpoints.values.md),
                              always(largeName)
                            ],
                            [T, always(name)]
                          ])(width)}
                        </Button>
                      </Tooltip>
                    ),
                    translatedTimePeriodOptions
                  )}
                </ButtonGroup>
                <CustomTimePeriodPickers
                  acceptDate={changeDate}
                  customTimePeriod={customTimePeriod}
                  isCompact={isCompact}
                  labelTimePeriodPicker={labelTimePeriodPicker}
                />
                {/* {not(disableGraphOptions) && <GraphOptions />} */}
              </Paper>
            );
          }}
        </Responsive.ParentSize>
      </div>
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
