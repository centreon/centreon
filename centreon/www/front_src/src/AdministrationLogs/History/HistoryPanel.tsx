import OpenInNewIcon from '@mui/icons-material/OpenInNew';
import { Box, Typography, useTheme } from '@mui/material';

import { IconButton, Panel, useInfiniteScrollListing } from '@centreon/ui';

import { equals, head, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { activityLogsDecoder } from '../api/decoders';
import { activityLogsEndpoint } from '../api/endpoints';
import { historyPageAtom, type SelectedTarget } from '../atoms';
import type { ActivityLog } from '../models';
import {
  labelClose,
  labelLinkedResourceChanged,
  labelOnHost,
  labelOnHostGroup,
  labelOpenConfiguration,
  labelTargetTypes
} from '../translatedLabels';
import { useSelectedTarget } from '../useSelectedTarget';
import { getConfigurationPath } from '../utils';
import { useHistoryPanelStyles } from './HistoryPanel.styles';
import Timeline from './Timeline';

interface HeaderProps {
  latestEvent?: ActivityLog;
  target: SelectedTarget;
}

// Same layout as the header of the configuration panel: the name as title,
// the actions as icons on the right
const Header = ({ target, latestEvent }: HeaderProps): JSX.Element => {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const configurationPath = getConfigurationPath(target);

  return (
    <Box className="flex min-w-0 items-center gap-2 py-1">
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

  const parent = latestEvent?.target.parent;

  const getParentLabel = (): string | null => {
    if (!equals(target.type, 'Service') || !latestEvent) {
      return null;
    }

    if (!parent || isEmpty(parent.names)) {
      return t(labelLinkedResourceChanged);
    }

    return t(
      equals(parent.type, 'HostGroup') ? labelOnHostGroup : labelOnHost,
      { names: parent.names.join(', ') }
    );
  };

  const parentLabel = getParentLabel();

  return (
    <Typography className="pt-3 text-text-secondary" variant="body2">
      {t(labelTargetTypes[target.type] ?? target.type)}
      {parentLabel && ` · ${parentLabel}`}
    </Typography>
  );
};

export const minPanelWidth = 480;
export const maxPanelWidth = 900;

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
  const { classes } = useHistoryPanelStyles();
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
