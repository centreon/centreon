import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import { ButtonBase, Typography } from '@mui/material';

import { useLocale } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import dayjs from 'dayjs';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { historyExpandedLogIdsAtom } from '../atoms';
import ChangesTable from '../Changes/ChangesTable';
import { Action, type ActivityLog } from '../models';
import {
  labelHideChanges,
  labelShowChanges,
  labelSystem,
  labelUnknown
} from '../translatedLabels';
import { useExpandedLogs } from '../useExpandedLogs';
import { actionLabels } from '../utils';

// Dot of the timeline, in the color of the action chip of the listing
const actionDotClassNames: Record<Action, string> = {
  [Action.Add]: 'bg-success-main',
  [Action.Delete]: 'bg-error-main',
  [Action.Disable]: 'bg-error-main',
  [Action.Enable]: 'bg-success-main',
  [Action.MassChange]: 'bg-warning-main',
  [Action.Update]: 'bg-warning-main'
};

interface Props {
  event: ActivityLog;
  isLast: boolean;
}

const TimelineEvent = ({ event, isLast }: Props): JSX.Element => {
  const { t } = useTranslation();
  const locale = useLocale().substring(0, 2);
  const { timezone } = useAtomValue(userAtom);
  const { isExpanded, toggle } = useExpandedLogs(historyExpandedLogIdsAtom);

  const { id, date, action, actor, hasChanges } = event;
  const expanded = isExpanded(id);

  const getAuthor = (): string => {
    if (!actor) {
      return t(labelSystem);
    }

    return actor.name ?? t(labelUnknown);
  };

  const summary = (
    <span className="flex min-w-0 flex-1 items-baseline gap-2">
      <Typography className="tabular-nums" component="span" variant="body2">
        {dayjs(date).tz(timezone).locale(locale).format('LT')}
      </Typography>
      <Typography className="font-bold" component="span" variant="body2">
        {t(actionLabels[action])}
      </Typography>
      <Typography
        className="truncate text-text-secondary"
        component="span"
        variant="body2"
      >
        · {getAuthor()}
      </Typography>
    </span>
  );

  return (
    <li className="relative flex gap-3 pl-1">
      {/* The line linking the dots, stopped at the last event */}
      {!isLast && (
        <span className="absolute top-4 bottom-0 left-[9px] w-px bg-divider" />
      )}
      <span
        className={`relative mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${actionDotClassNames[action]}`}
      />
      <div className="flex min-w-0 flex-1 flex-col gap-2 pb-4">
        {hasChanges ? (
          <ButtonBase
            aria-expanded={expanded}
            aria-label={t(expanded ? labelHideChanges : labelShowChanges)}
            className="flex w-full justify-between gap-2 rounded-sm text-left"
            onClick={() => toggle(id)}
          >
            {summary}
            <ExpandMoreIcon
              className={`text-text-secondary transition-transform ${expanded ? 'rotate-0' : '-rotate-90'}`}
              fontSize="small"
            />
          </ButtonBase>
        ) : (
          summary
        )}
        {expanded && <ChangesTable action={action} logId={id} />}
      </div>
    </li>
  );
};

export default TimelineEvent;
