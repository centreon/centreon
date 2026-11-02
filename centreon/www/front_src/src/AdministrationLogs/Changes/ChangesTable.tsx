import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import { Typography } from '@mui/material';

import { LoadingSkeleton, useFetchQuery } from '@centreon/ui';

import { isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { changesDecoder } from '../api/decoders';
import { getChangesEndpoint } from '../api/endpoints';
import {
  labelAfter,
  labelBefore,
  labelCouldNotReadChanges,
  labelNoModification
} from '../translatedLabels';

interface Props {
  logId: number;
}

interface ValueProps {
  label: string;
  value?: string | null;
  variant: 'before' | 'after';
}

const variantClassNames = {
  after: 'bg-success-main/15 text-success-dark dark:text-success-light',
  before:
    'bg-error-main/15 text-error-dark line-through decoration-1 dark:text-error-light'
};

const Value = ({ label, value, variant }: ValueProps): JSX.Element =>
  isNil(value) || isEmpty(value) ? (
    <span className="font-mono text-xs text-text-disabled" title={label}>
      —
    </span>
  ) : (
    <span
      className={`break-all rounded-sm px-1.5 py-0.5 font-mono text-xs ${variantClassNames[variant]}`}
      title={label}
    >
      {value}
    </span>
  );

// The diff is only computed when a row is expanded: it replays the history
// of the object, which is too costly to do for every row of the listing
const ChangesTable = ({ logId }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { data, isLoading, isError } = useFetchQuery({
    decoder: changesDecoder,
    getEndpoint: () => getChangesEndpoint(logId),
    getQueryKey: () => ['activity-log-changes', logId],
    queryOptions: { suspense: false }
  });

  if (isLoading) {
    return <LoadingSkeleton height={24} variant="rectangular" width="40%" />;
  }

  if (isError) {
    return (
      <Typography className="text-error-main" variant="body2">
        {t(labelCouldNotReadChanges)}
      </Typography>
    );
  }

  const changes = data?.result ?? [];

  if (isEmpty(changes)) {
    return (
      <Typography className="text-text-secondary" variant="body2">
        {t(labelNoModification)}
      </Typography>
    );
  }

  return (
    // Four tracks (field, before, arrow, after) shared by every change, so
    // the arrows and the values line up without a table
    <dl className="m-0 grid grid-cols-[minmax(140px,max-content)_fit-content(40%)_auto_minmax(0,1fr)] items-baseline gap-x-4 gap-y-2">
      {changes.map(({ field, before, after }) => (
        <div className="contents" key={field}>
          <dt className="font-mono text-xs text-text-secondary">{field}</dt>
          <dd className="m-0 justify-self-start">
            <Value label={t(labelBefore)} value={before} variant="before" />
          </dd>
          <dd className="m-0 self-center">
            <ArrowForwardIcon
              className="block text-text-secondary"
              fontSize="inherit"
            />
          </dd>
          <dd className="m-0 justify-self-start">
            <Value label={t(labelAfter)} value={after} variant="after" />
          </dd>
        </div>
      ))}
    </dl>
  );
};

export default ChangesTable;
