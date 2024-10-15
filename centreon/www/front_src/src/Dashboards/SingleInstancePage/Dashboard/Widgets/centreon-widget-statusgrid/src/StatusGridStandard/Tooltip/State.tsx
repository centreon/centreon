import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import {
  LoadingSkeleton,
  useFetchQuery,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import { labelAcknowledged, labelInDowntime } from '../translatedLabels';

interface Props {
  endpoint: string;
  type: 'acknowledgement' | 'downtime';
}

const State = ({ endpoint, type }: Props): JSX.Element | null => {
  const { t } = useTranslation();

  const { format } = useLocaleDateTimeFormat();

  const { data, isLoading } = useFetchQuery({
    getEndpoint: () => endpoint,
    getQueryKey: () => ['statusgrid', type, endpoint],
    queryOptions: {
      suspense: false
    }
  });

  const state = data?.result[0];

  if (isLoading && isNil(data)) {
    return <LoadingSkeleton variant="text" width="100%" />;
  }

  if (isNil(data)) {
    return null;
  }

  const isDowntime = equals(type, 'downtime');

  const text = !isDowntime
    ? state?.comment
    : `from ${format({
        date: state?.start_time,
        formatString: 'LLL'
      })} to ${format({ date: state?.end_time, formatString: 'LLL' })}`;

  return (
    <Typography>
      {t(isDowntime ? labelInDowntime : labelAcknowledged)} ({text})
    </Typography>
  );
};

export default State;
