import { Typography } from '@mui/material';

import { useLocale } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import relativeTime from 'dayjs/plugin/relativeTime';
import timezonePlugin from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { useAtomValue } from 'jotai';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import Author from '../Author';
import type { ActivityLog } from '../models';
import {
  labelAuthor,
  labelDate,
  labelLogId,
  labelObject,
  labelObjectType,
  labelTargetTypes
} from '../translatedLabels';
import { useTargetParentLabel } from '../useTargetParentLabel';

dayjs.extend(utc);
dayjs.extend(timezonePlugin);
dayjs.extend(relativeTime);
dayjs.extend(localizedFormat);

interface ItemProps {
  children: ReactNode;
  label: string;
}

const Item = ({ label, children }: ItemProps): JSX.Element => (
  <div className="contents">
    <dt>
      <Typography className="text-text-secondary" variant="body2">
        {label}
      </Typography>
    </dt>
    <dd className="m-0 min-w-0">{children}</dd>
  </div>
);

interface Props {
  log: ActivityLog;
}

// Who did what, on what and when: the answers an audit entry is read for,
// as key-value pairs, before the changes themselves
const Summary = ({ log }: Props): JSX.Element => {
  const { t } = useTranslation();
  const locale = useLocale().substring(0, 2);
  const { timezone } = useAtomValue(userAtom);
  const getParentLabel = useTargetParentLabel();

  const { actor, date, id, target } = log;
  const localDate = dayjs(date).tz(timezone).locale(locale);
  const parentLabel = getParentLabel(target);

  return (
    <dl className="m-0 grid grid-cols-[max-content_minmax(0,1fr)] items-center gap-x-6 gap-y-2">
      <Item label={t(labelDate)}>
        <Typography className="tabular-nums" variant="body2">
          {/* To the second: several changes often land in the same minute */}
          {localDate.format('dddd LL LTS')}
        </Typography>
        <Typography className="text-text-secondary" variant="caption">
          {`${localDate.fromNow()} · ${timezone}`}
        </Typography>
      </Item>
      <Item label={t(labelAuthor)}>
        <Author actor={actor} />
      </Item>
      <Item label={t(labelObjectType)}>
        <Typography variant="body2">
          {t(labelTargetTypes[target.type] ?? target.type)}
        </Typography>
      </Item>
      <Item label={t(labelObject)}>
        <Typography className="break-words" variant="body2">
          {target.name}
        </Typography>
        {parentLabel && (
          <Typography className="text-text-secondary" variant="caption">
            {parentLabel}
          </Typography>
        )}
      </Item>
      <Item label={t(labelLogId)}>
        <Typography className="font-mono text-text-secondary" variant="body2">
          {`#${id}`}
        </Typography>
      </Item>
    </dl>
  );
};

export default Summary;
