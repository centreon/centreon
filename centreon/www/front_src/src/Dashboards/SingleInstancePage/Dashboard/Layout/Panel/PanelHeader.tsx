// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import UpdateIcon from '@mui/icons-material/Update';
import { CardHeader, Typography } from '@mui/material';

import { IconButton, useDeepCompare } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { dashboardAtom } from '../../atoms';
import { labelRefreshThePage } from '../../translatedLabels';
import { usePanelHeaderStyles } from './usePanelStyles';
import useRefreshWebPageWidget from './useRefreshWebPageWidget';

interface PanelHeaderProps {
  displayMoreActions: boolean;
  id: string;
  name: string;
}

/**
 * Title strip for widgets that have a title. The "last updated" indicator,
 * the "more actions" (…) trigger and the drag handle all live in a single
 * floating overlay shared with titleless widgets (see Item.tsx's
 * overlayInfo / overlayActions, Layout.tsx, PanelLastRefresh,
 * PanelMoreActionsButton) so the interaction is identical either way,
 * rather than duplicated here.
 */
const PanelHeader = ({
  id,
  displayMoreActions,
  name
}: PanelHeaderProps): JSX.Element | null => {
  const { classes } = usePanelHeaderStyles();
  const { t } = useTranslation();

  const dashboard = useAtomValue(dashboardAtom);

  const panel = useMemo(
    () => dashboard.layout.find((dashbordPanel) => equals(dashbordPanel.i, id)),
    useDeepCompare([dashboard.layout])
  );

  const isWebPageWidget = equals(name, 'centreon-widget-webpage');

  const refresWebpageWidget = useRefreshWebPageWidget(id);

  return (
    <CardHeader
      action={
        displayMoreActions && isWebPageWidget ? (
          <div className={classes.panelActionsIcons}>
            <IconButton
              onClick={refresWebpageWidget}
              size="small"
              title={t(labelRefreshThePage)}
              tooltipPlacement="top"
            >
              <UpdateIcon sx={{ height: 22, width: 22 }} />
            </IconButton>
          </div>
        ) : null
      }
      classes={{
        content: classes.panelHeaderContent
      }}
      className={classes.panelHeader}
      title={
        <Tooltip
          followCursor={false}
          label={panel?.options?.name}
          placement="top"
        >
          <Typography className={classes.panelTitle}>
            {panel?.options?.name || ''}
          </Typography>
        </Tooltip>
      }
    />
  );
};

export default PanelHeader;
