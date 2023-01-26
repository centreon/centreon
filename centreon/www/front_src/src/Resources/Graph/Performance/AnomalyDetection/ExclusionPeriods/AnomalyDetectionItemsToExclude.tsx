import { isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { List, ListItem, ListItemText, Typography } from '@mui/material';

import { getData, useRequest, postData, deleteData } from '@centreon/ui';

import { labelExcludedPeriods } from '../../../../translatedLabels';
import { ExclusionPeriodsThreshold } from '../models';
import { getExclusionPeriodsByExclusionIdEndPoint } from '../anomalyDetectionEndPoints';

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
  const { sendRequest } = useRequest<any>({
    request: deleteData
  });

  const deleteExclusionPeriod = (): void => {
    const endPoint = getExclusionPeriodsByExclusionIdEndPoint({
      anomalyDetectionServiceId: 1,
      exclusionId: 1
    });
    sendRequest(endPoint);
  };

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
                    <AnomalyDetectionItemsExclusionPeriod
                      item={item?.id}
                      onDeleteExcludePeriod={deleteExclusionPeriod}
                    />
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
