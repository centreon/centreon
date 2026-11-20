import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';
import OpenInNewIcon from '@mui/icons-material/OpenInNew';
import TimelineIcon from '@mui/icons-material/Timeline';
import { Box, Typography, useTheme } from '@mui/material';

import { IconButton, Panel, StatusChip } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import ChangesTable from '../Changes/ChangesTable';
import type { ActivityLog } from '../models';
import { minPanelWidth } from '../SidePanel/constants';
import { useSidePanelStyles } from '../SidePanel/SidePanel.styles';
import {
  labelChanges,
  labelClose,
  labelNextLog,
  labelNoModification,
  labelOpenConfiguration,
  labelPreviousLog,
  labelViewFullHistory
} from '../translatedLabels';
import { useSelectedLog } from '../useSelectedLog';
import { useSelectedTarget } from '../useSelectedTarget';
import {
  actionLabels,
  actionSeverityCodes,
  getConfigurationPath
} from '../utils';
import Summary from './Summary';
import { useKeyboardNavigation } from './useKeyboardNavigation';

interface HeaderProps {
  log: ActivityLog;
  next?: ActivityLog;
  previous?: ActivityLog;
}

// What happened to which object as title, then the navigation between the
// logs of the listing and the link to the configuration of the object
const Header = ({ log, previous, next }: HeaderProps): JSX.Element => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { select } = useSelectedLog();

  const { action, target } = log;
  const configurationPath = getConfigurationPath(target);

  return (
    <Box className="flex min-w-0 items-center gap-2 py-1">
      <StatusChip
        className="h-5 min-w-20 shrink-0 font-bold"
        label={t(actionLabels[action])}
        severityCode={actionSeverityCodes[action]}
      />
      <Typography className="truncate" title={target.name} variant="h6">
        {target.name}
      </Typography>
      <Box className="ml-auto flex shrink-0 items-center gap-1">
        <IconButton
          ariaLabel={t(labelPreviousLog)}
          disabled={!previous}
          onClick={() => previous && select(previous)}
          title={t(labelPreviousLog)}
        >
          <KeyboardArrowUpIcon fontSize="small" />
        </IconButton>
        <IconButton
          ariaLabel={t(labelNextLog)}
          disabled={!next}
          onClick={() => next && select(next)}
          title={t(labelNextLog)}
        >
          <KeyboardArrowDownIcon fontSize="small" />
        </IconButton>
        {configurationPath && (
          <IconButton
            ariaLabel={t(labelOpenConfiguration)}
            onClick={() => navigate(configurationPath)}
            title={t(labelOpenConfiguration)}
          >
            <OpenInNewIcon fontSize="small" />
          </IconButton>
        )}
      </Box>
    </Box>
  );
};

interface Props {
  log: ActivityLog;
  onResize: (width: number) => void;
  // The logs of the listing, in their order, to go to the previous or next one
  rows: Array<ActivityLog>;
  width: number;
}

// Detail of the log selected in the listing: the summary of the event, then
// the changed fields. The listing stays visible so another log can be picked
const LogDetailPanel = ({ log, onResize, rows, width }: Props): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();
  const { classes } = useSidePanelStyles();
  const { close, select } = useSelectedLog();
  const { select: selectTarget } = useSelectedTarget();

  // The log can be gone from the listing once the filters change: the panel
  // still shows it, without the navigation
  const index = rows.findIndex(({ id }) => equals(id, log.id));
  const previous = index > 0 ? rows[index - 1] : undefined;
  const next = index >= 0 ? rows[index + 1] : undefined;

  useKeyboardNavigation({
    onClose: close,
    onNext: next ? () => select(next) : undefined,
    onPrevious: previous ? () => select(previous) : undefined
  });

  return (
    <Panel
      className={classes.panel}
      header={<Header log={log} next={next} previous={previous} />}
      headerBackgroundColor={theme.palette.background.default}
      labelClose={t(labelClose)}
      minWidth={Math.min(minPanelWidth, width)}
      onClose={close}
      onResize={onResize}
      selectedTab={
        <div className="flex flex-col gap-6 px-5 py-4">
          <Summary log={log} />
          <section className="flex flex-col gap-2">
            <Typography className="font-bold" component="h2" variant="body1">
              {t(labelChanges)}
            </Typography>
            {log.hasChanges ? (
              // Keyed by log: the field filter is reset on navigation
              <ChangesTable action={log.action} key={log.id} logId={log.id} />
            ) : (
              <Typography className="text-text-secondary" variant="body2">
                {t(labelNoModification)}
              </Typography>
            )}
          </section>
          <Button
            className="self-start"
            icon={<TimelineIcon />}
            iconVariant="start"
            onClick={() => selectTarget(log.target, log)}
            size="small"
            variant="secondary"
          >
            {t(labelViewFullHistory)}
          </Button>
        </div>
      }
      width={width}
    />
  );
};

export default LogDetailPanel;
