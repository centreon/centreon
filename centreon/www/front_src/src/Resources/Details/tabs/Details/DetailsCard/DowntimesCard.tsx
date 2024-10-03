import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import DowntimeChip from '../../../../Chip/Downtime';
import {
  labelDowntimeDuration,
  labelFrom,
  labelTo
} from '../../../../translatedLabels';
import { ResourceDetails } from '../../../models';
import StateCard from '../StateCard';

interface Props {
  details: ResourceDetails;
}

const useStyles = makeStyles()((theme) => ({
  downtimes: {
    display: 'grid',
    rowGap: theme.spacing(1)
  }
}));

const DowntimesCard = ({ details }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { toDateTime } = useLocaleDateTimeFormat();

  return (
    <div className={classes.downtimes}>
      {details.downtimes?.map(({ start_time, end_time, comment }) => (
        <StateCard
          chip={<DowntimeChip />}
          commentLine={comment}
          contentLines={[
            ...[
              { prefix: t(labelFrom), testId: 'From_date', time: start_time },
              { prefix: t(labelTo), testId: 'To_date', time: end_time }
            ].map(({ prefix, testId, time }) => {
              return {
                line: `${prefix} ${toDateTime(time)}`,
                testId
              };
            })
          ]}
          key={`downtime-${start_time}-${end_time}`}
          title={t(labelDowntimeDuration)}
        />
      ))}
    </div>
  );
};

export default DowntimesCard;
