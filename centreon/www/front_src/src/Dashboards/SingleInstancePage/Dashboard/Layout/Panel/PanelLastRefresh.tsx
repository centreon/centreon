// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Typography } from '@mui/material';

import { useDeepCompare } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { useIsFetching, useQueryClient } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { equals, isEmpty } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { dashboardAtom } from '../../atoms';
import { useLastRefresh } from '../../hooks/useLastRefresh';
import { labelRefresh } from '../../translatedLabels';
import { usePanelHeaderStyles } from './usePanelStyles';

interface Props {
  id: string;
  setRefreshCount?: (id: string) => void;
}

/**
 * Small "last updated" indicator shared by every widget, titled or not, via
 * the floating overlay (see Item.tsx's overlayInfo / Layout.tsx) so its
 * behavior and position stay identical regardless of whether the widget
 * has a title bar.
 */
const PanelLastRefresh = ({
  id,
  setRefreshCount
}: Props): JSX.Element | null => {
  const { classes } = usePanelHeaderStyles();
  const { t } = useTranslation();

  const dashboard = useAtomValue(dashboardAtom);

  const panel = useMemo(
    () => dashboard.layout.find((dashbordPanel) => equals(dashbordPanel.i, id)),
    useDeepCompare([dashboard.layout])
  );

  const widgetPrefixQuery = useMemo(
    () => `${panel?.panelConfiguration.path}_${id}`,
    [panel?.panelConfiguration.path, id]
  );

  const queryClient = useQueryClient();
  const isFetching = useIsFetching({ queryKey: [widgetPrefixQuery] });

  const { labelLastRefresh } = useLastRefresh(isFetching);

  const hasQueryData = !isEmpty(
    queryClient.getQueriesData({
      queryKey: [widgetPrefixQuery]
    })
  );

  if (!hasQueryData) {
    return null;
  }

  const refresh = (): void => {
    setRefreshCount?.(id);
  };

  return (
    <Tooltip followCursor={false} label={t(labelRefresh)} placement="top">
      <Typography
        className={classes.panelLastRefresh}
        onClick={!isFetching ? refresh : undefined}
      >
        {labelLastRefresh}
      </Typography>
    </Tooltip>
  );
};

export default PanelLastRefresh;
