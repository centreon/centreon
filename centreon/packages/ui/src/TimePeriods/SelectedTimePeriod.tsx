import { useAtomValue, useSetAtom } from 'jotai';
import { T, always, cond, equals, lte, map, pick } from 'ramda';
import { useTranslation } from 'react-i18next';
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
  extraTimePeriods?: Array<Omit<TimePeriod, 'timelineEventsLimit'>>;
  width: number;
}

const SelectedTimePeriod = ({
  width,
  disabled = false,
  extraTimePeriods = []
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const theme = useTheme();
  const { t } = useTranslation();
  const selectedTimePeriodData = useAtomValue(selectedTimePeriodAtom);

  const changeSelectedTimePeriod = useSetAtom(
    changeSelectedTimePeriodDerivedAtom
  );

  const currentTimePeriods = useSortTimePeriods([
    ...timePeriods,
    ...extraTimePeriods
  ] as Array<TimePeriod>);

  const timePeriodOptions = map(
    pick(['id', 'name', 'largeName']),
    currentTimePeriods
  );

  const translatedTimePeriodOptions = timePeriodOptions.map((timePeriod) => ({
    ...timePeriod,
    largeName: t(timePeriod.largeName),
    name: t(timePeriod.name)
  }));

  return (
    <ButtonGroup
      className={classes.buttonGroup}
      color="primary"
      disabled={disabled}
      size="small"
    >
      {map(
        ({ id, name, largeName }) => (
          <Tooltip key={name} placement="top" title={largeName}>
            <Button
              className={classes.button}
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
        translatedTimePeriodOptions
      )}
    </ButtonGroup>
  );
};

export default SelectedTimePeriod;
