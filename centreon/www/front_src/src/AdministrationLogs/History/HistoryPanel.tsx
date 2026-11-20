import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import OpenInNewIcon from '@mui/icons-material/OpenInNew';
import { Box, Typography, useTheme } from '@mui/material';

import { IconButton, Panel, useInfiniteScrollListing } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { head } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { activityLogsDecoder } from '../api/decoders';
import { activityLogsEndpoint } from '../api/endpoints';
import {
  historyOriginLogAtom,
  historyPageAtom,
  type SelectedTarget
} from '../atoms';
import type { ActivityLog } from '../models';
import { minPanelWidth } from '../SidePanel/constants';
import { useSidePanelStyles } from '../SidePanel/SidePanel.styles';
import {
  labelBackToLogDetail,
  labelClose,
  labelOpenConfiguration,
  labelTargetTypes
} from '../translatedLabels';
import { useSelectedLog } from '../useSelectedLog';
import { useSelectedTarget } from '../useSelectedTarget';
import { useTargetParentLabel } from '../useTargetParentLabel';
import { getConfigurationPath } from '../utils';
import Timeline from './Timeline';

interface HeaderProps {
  latestEvent?: ActivityLog;
  target: SelectedTarget;
}

// Same layout as the header of the configuration panel: the name as title,
// the actions as icons on the right. A back arrow leads to the log detail the
// history was opened from
const Header = ({ target, latestEvent }: HeaderProps): JSX.Element => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const originLog = useAtomValue(historyOriginLogAtom);
  const { select: selectLog } = useSelectedLog();

  const configurationPath = getConfigurationPath(target);

  return (
    <Box className="flex min-w-0 items-center gap-2 py-1">
      {originLog && (
        <IconButton
          ariaLabel={t(labelBackToLogDetail)}
          onClick={() => selectLog(originLog)}
          title={t(labelBackToLogDetail)}
        >
          <ArrowBackIcon fontSize="small" />
        </IconButton>
      )}
      {/* The most recent event carries the current name of the object */}
      <Typography className="truncate" variant="h6">
        {latestEvent?.target.name ?? '…'}
      </Typography>
      {configurationPath && (
        <Box className="ml-auto flex items-center gap-1">
          <IconButton
            ariaLabel={t(labelOpenConfiguration)}
            onClick={() => navigate(configurationPath)}
            title={t(labelOpenConfiguration)}
          >
            <OpenInNewIcon fontSize="small" />
          </IconButton>
        </Box>
      )}
    </Box>
  );
};

const Subtitle = ({ target, latestEvent }: HeaderProps): JSX.Element => {
  const { t } = useTranslation();
  const getParentLabel = useTargetParentLabel();

  const parentLabel = latestEvent ? getParentLabel(latestEvent.target) : null;

  return (
    <Typography className="pt-3 text-text-secondary" variant="body2">
      {t(labelTargetTypes[target.type] ?? target.type)}
      {parentLabel && ` · ${parentLabel}`}
    </Typography>
  );
};

interface Props {
  onResize: (width: number) => void;
  target: SelectedTarget;
  width: number;
}

// History of the object selected in the listing, in a side panel so that the
// listing stays visible and another object can be picked right away
const HistoryPanel = ({ target, onResize, width }: Props): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();
  const { classes } = useSidePanelStyles();
  const { close } = useSelectedTarget();

  const { elements, elementRef, hasNextPage, isLoading } =
    useInfiniteScrollListing<ActivityLog>({
      apiFormat: 'JSON-LD',
      customQueryParameters: [
        { name: 'target_type[]', value: target.type },
        { name: 'target_id', value: target.id }
      ],
      decoder: activityLogsDecoder,
      endpoint: activityLogsEndpoint,
      limit: 30,
      pageAtom: historyPageAtom,
      parameters: { apiFormat: 'JSON-LD', sort: { date: 'desc' } },
      queryKeyName: `activity-log-history-${target.type}-${target.id}`,
      suspense: false
    });

  return (
    <Panel
      className={classes.panel}
      header={<Header latestEvent={head(elements)} target={target} />}
      headerBackgroundColor={theme.palette.background.default}
      labelClose={t(labelClose)}
      minWidth={Math.min(minPanelWidth, width)}
      onClose={close}
      onResize={onResize}
      selectedTab={
        <div className="px-5 pb-5">
          <Subtitle latestEvent={head(elements)} target={target} />
          <Timeline
            elementRef={elementRef}
            elements={elements}
            hasNextPage={hasNextPage}
            isLoading={isLoading}
          />
        </div>
      }
      width={width}
    />
  );
};

export default HistoryPanel;
