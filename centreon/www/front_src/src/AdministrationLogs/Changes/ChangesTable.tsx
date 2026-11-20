import { TextField, Typography } from '@mui/material';

import { LoadingSkeleton, useFetchQuery } from '@centreon/ui';

import { equals, isEmpty, isNil, toLower } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { changesDecoder } from '../api/decoders';
import { getChangesEndpoint } from '../api/endpoints';
import { Action, type Change } from '../models';
import {
  labelAfter,
  labelBefore,
  labelCouldNotReadChanges,
  labelFieldCount,
  labelFilterFields,
  labelNoFieldMatches,
  labelNoModification
} from '../translatedLabels';

// Above this many fields, a filter helps to find the one being looked for
const fieldFilterThreshold = 8;

type Variant = 'before' | 'after';

const variantClassNames: Record<Variant, string> = {
  after: 'bg-success-main/15 text-success-dark dark:text-success-light',
  before: 'bg-error-main/15 text-error-dark dark:text-error-light'
};

const variantSigns: Record<Variant, string> = {
  after: '+',
  before: '−'
};

interface ValueProps {
  label: string;
  value?: string | null;
  variant: Variant;
}

// One line of a unified diff: a sign, then the value. Values are kept whole
// (break-all), a command line or a macro is only useful when fully readable
const Value = ({ label, value, variant }: ValueProps): JSX.Element => (
  <div
    className={`flex gap-2 rounded-sm px-2 py-1 font-mono text-xs ${variantClassNames[variant]}`}
    title={label}
  >
    <span aria-hidden className="select-none">
      {variantSigns[variant]}
    </span>
    {isNil(value) || isEmpty(value) ? (
      <span className="italic opacity-70">—</span>
    ) : (
      <span className="min-w-0 break-all whitespace-pre-wrap">{value}</span>
    )}
  </div>
);

// An added object has no previous values and a deleted one no next values:
// only the side that exists is shown
const getVariants = (action: Action): Array<Variant> => {
  if (equals(action, Action.Add)) {
    return ['after'];
  }

  if (equals(action, Action.Delete)) {
    return ['before'];
  }

  return ['before', 'after'];
};

interface ChangeRowProps {
  change: Change;
  variants: Array<Variant>;
}

const ChangeRow = ({ change, variants }: ChangeRowProps): JSX.Element => {
  const { t } = useTranslation();

  const labels: Record<Variant, string> = {
    after: t(labelAfter),
    before: t(labelBefore)
  };

  return (
    <div className="flex flex-col gap-1 border-0 border-b border-solid border-b-divider py-2 last:border-b-0">
      <dt className="font-mono text-xs font-bold text-text-secondary">
        {change.field}
      </dt>
      {variants.map((variant) => (
        <dd className="m-0" key={variant}>
          <Value
            label={labels[variant]}
            value={change[variant]}
            variant={variant}
          />
        </dd>
      ))}
    </div>
  );
};

interface Props {
  action: Action;
  logId: number;
}

// The diff is only computed on demand: it replays the history of the object,
// which is too costly to do for every row of the listing
const ChangesTable = ({ action, logId }: Props): JSX.Element => {
  const { t } = useTranslation();
  const [fieldFilter, setFieldFilter] = useState('');

  const { data, isLoading, isError } = useFetchQuery({
    decoder: changesDecoder,
    getEndpoint: () => getChangesEndpoint(logId),
    getQueryKey: () => ['activity-log-changes', logId],
    queryOptions: { suspense: false }
  });

  if (isLoading) {
    return (
      <div className="flex flex-col gap-2">
        <LoadingSkeleton height={16} variant="rectangular" width="30%" />
        <LoadingSkeleton height={24} variant="rectangular" width="100%" />
        <LoadingSkeleton height={24} variant="rectangular" width="100%" />
      </div>
    );
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

  const variants = getVariants(action);
  const hasFieldFilter = changes.length > fieldFilterThreshold;
  const visibleChanges = changes.filter(({ field }) =>
    toLower(field).includes(toLower(fieldFilter.trim()))
  );

  return (
    <div className="flex flex-col gap-2">
      {hasFieldFilter && (
        <div className="flex items-center gap-3">
          <TextField
            className="flex-1"
            onChange={(event) => setFieldFilter(event.target.value)}
            placeholder={t(labelFilterFields)}
            size="small"
            slotProps={{ htmlInput: { 'aria-label': t(labelFilterFields) } }}
            value={fieldFilter}
          />
          <Typography
            className="shrink-0 text-text-secondary"
            variant="caption"
          >
            {t(labelFieldCount, {
              count: changes.length,
              visible: visibleChanges.length
            })}
          </Typography>
        </div>
      )}
      {isEmpty(visibleChanges) ? (
        <Typography className="text-text-secondary" variant="body2">
          {t(labelNoFieldMatches)}
        </Typography>
      ) : (
        <dl className="m-0">
          {visibleChanges.map((change) => (
            <ChangeRow change={change} key={change.field} variants={variants} />
          ))}
        </dl>
      )}
    </div>
  );
};

export default ChangesTable;
