import { useEffect } from 'react';

import { Responsive } from '@visx/visx';
import { useAtomValue } from 'jotai/utils';
import { lt } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Paper, useTheme } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import CustomTimePeriodPickers from './CustomTimePeriod/CustomTimePeriodPickers';
import TimePeriodButtonGroup from './TimePeriodButton';
import { LabelDay, LabelTimePeriodPicker } from './models';
import {
  customTimePeriodAtom,
  getGraphQueryParametersDerivedAtom,
  selectedTimePeriodAtom
} from './timePeriodAtoms';

interface StylesProps {
  disablePaper: boolean;
}

const useStyles = makeStyles<StylesProps>()((theme, { disablePaper }) => ({
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
  getGraphParameters?: (data) => void;
  height?: number;
  labelButtonGroups?: Array<LabelDay>;
  labelTimePeriodPicker?: LabelTimePeriodPicker;
}

const TimePeriod = ({
  disableGraphOptions = false,
  disablePaper = false,
  height = 100,
  labelTimePeriodPicker = { labelEnd: 'To', labelFrom: 'From' },
  getGraphParameters
}: Props): JSX.Element => {
  const { classes } = useStyles({ disablePaper });
  const theme = useTheme();

  const { themeMode } = useAtomValue(userAtom);
  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);
  const customTimePeriod = useAtomValue(customTimePeriodAtom);

  const getGraphQueryParameters = useAtomValue(
    getGraphQueryParametersDerivedAtom
  );

  const graphQueryParameters = getGraphQueryParameters({
    endDate: customTimePeriod.end,
    startDate: customTimePeriod.start,
    timePeriod: selectedTimePeriod
  });

  useEffect(() => {
    getGraphParameters?.(graphQueryParameters);
  }, [selectedTimePeriod, customTimePeriod]);

  return useMemoComponent({
    Component: (
      <div style={{ height }}>
        <Responsive.ParentSize>
          {({ width }): JSX.Element => {
            const isCompact = lt(width, theme.breakpoints.values.sm);

            return (
              <Paper className={classes.header}>
                <TimePeriodButtonGroup width={width} />
                <CustomTimePeriodPickers
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
      disableGraphOptions,
      disablePaper,
      selectedTimePeriod,
      customTimePeriod,
      themeMode
    ]
  });
};

export default TimePeriod;
