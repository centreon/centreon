import { useAtomValue, useSetAtom } from 'jotai';
import { T, always, cond, equals, lte, map, pick } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Button, ButtonGroup, Tooltip, useTheme } from '@mui/material';

import { TimePeriod, timePeriods } from './models';
import {
  changeSelectedTimePeriodDerivedAtom,
  selectedTimePeriodAtom
} from './timePeriodsAtoms';
import useSortTimePeriods from './useSortTimePeriods';

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
  extraTimePeriods?: Array<TimePeriod>;
  width: number;
}

const SelectedTimePeriod = ({
  width,
  disabled = false,
  extraTimePeriods = []
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const theme = useTheme();

  const selectedTimePeriodData = useAtomValue(selectedTimePeriodAtom);

  const changeSelectedTimePeriod = useSetAtom(
    changeSelectedTimePeriodDerivedAtom
  );

  const currentTimePeriods = useSortTimePeriods([
    ...timePeriods,
    ...extraTimePeriods
  ]);

  const timePeriodOptions = map(
    pick(['id', 'name', 'largeName']),
    currentTimePeriods
  );

  return (
    <div>
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
                  equals(selectedTimePeriodData?.id, id)
                    ? 'contained'
                    : 'outlined'
                }
                onClick={(): void =>
                  changeSelectedTimePeriod({
                    id,
                    timePeriods: currentTimePeriods
                  })
                }
              >
                {
                  cond<number, string>([
                    [lte(theme.breakpoints.values.md), always(largeName)],
                    [T, always(name)]
                  ])(width) as string
                }
              </Button>
            </Tooltip>
          ),
          timePeriodOptions
        )}
      </ButtonGroup>
    </div>
  );
};

export default SelectedTimePeriod;
