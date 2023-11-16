import { useTranslation } from 'react-i18next';
import { equals, isNil } from 'ramda';

import { Typography } from '@mui/material';

import { useFetchQuery, useLocaleDateTimeFormat } from '@centreon/ui';

import { labelAcknowledged, labelInDowntime } from '../translatedLabels';

interface Props {
  endpoint: string;
  type: 'acknowledgement' | 'downtime';
}

const State = ({ endpoint, type }: Props): JSX.Element | null => {
  const { t } = useTranslation();

  const { format } = useLocaleDateTimeFormat();

  const { data } = useFetchQuery({
    getEndpoint: () => endpoint,
    getQueryKey: () => ['statusgrid', type, endpoint],
    queryOptions: {
      suspense: false
    }
  });

  const state = data?.result[0];

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
