import { isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { List, ListItem, ListItemText, Typography } from '@mui/material';

import { labelExcludedPeriods } from '../../../../translatedLabels';
import { ExclusionPeriodsThreshold } from '../models';

import AnomalyDetectionItemsExclusionPeriod from './AnomalyDetectionItemsExclusionPeriod';

const useStyles = makeStyles()((theme) => ({
  excludedPeriods: {
    display: 'flex',
    flexDirection: 'column',
    width: '55%'
  },
  list: {
    backgroundColor: theme.palette.action.disabledBackground,
    maxHeight: theme.spacing(150 / 8),
    minHeight: theme.spacing(150 / 8),
    overflow: 'auto',
    padding: theme.spacing(1)
  },
  title: {
    color: theme.palette.text.disabled
  }
}));

interface Props {
  data: ExclusionPeriodsThreshold['data'];
}

const AnomalyDetectionItemsToExclude = ({ data }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.excludedPeriods}>
      <Typography
        className={classes.title}
        data-testid={labelExcludedPeriods}
        variant="h6"
      >
        {labelExcludedPeriods}
      </Typography>
      <List className={classes.list} data-testid="listExcludedPeriods">
        {data.map((item) => {
          const dateExist =
            !isNil(item?.id?.start) &&
            !isNil(item?.id?.end) &&
            item.isConfirmed;

          return (
            dateExist && (
              <ListItem
                disablePadding
                key={`${item?.id?.start}-${item?.id?.end}`}
              >
                <ListItemText
                  primary={
                    <AnomalyDetectionItemsExclusionPeriod item={item?.id} />
                  }
                />
              </ListItem>
            )
          );
        })}
      </List>
    </div>
  );
};
export default AnomalyDetectionItemsToExclude;
