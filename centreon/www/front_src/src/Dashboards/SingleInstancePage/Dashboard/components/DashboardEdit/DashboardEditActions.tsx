import { ReactElement, useCallback, useEffect } from 'react';

import { useIsFetching, useQueryClient } from '@tanstack/react-query';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router-dom';

import EditOutlinedIcon from '@mui/icons-material/EditOutlined';

import { federatedWidgetsAtom } from '@centreon/ui-context';
import { Button } from '@centreon/ui/components';

import { Dashboard, DashboardPanel } from '../../../../api/models';
import {
  dashboardAtom,
  isEditingAtom,
  switchPanelsEditionModeDerivedAtom
} from '../../atoms';
import {
  formatPanel,
  getPanels,
  routerParams
} from '../../hooks/useDashboardDetails';
import useDashboardDirty from '../../hooks/useDashboardDirty';
import useSaveDashboard from '../../hooks/useSaveDashboard';
import {
  labelCancel,
  labelEditDashboard,
  labelSave
} from '../../translatedLabels';

import { useDashboardEditActionsStyles } from './DashboardEditActions.styles';

interface DashboardEditActionsProps {
  panels?: Array<DashboardPanel>;
}

const DashboardEditActions = ({
  panels
}: DashboardEditActionsProps): ReactElement => {
  const { classes } = useDashboardEditActionsStyles();
  const { t } = useTranslation();
  const { dashboardId } = routerParams.useParams();

  const queryClient = useQueryClient();
  const isFetchingDashboard = useIsFetching({
    queryKey: ['dashboard', dashboardId]
  });

  const federatedWidgets = useAtomValue(federatedWidgetsAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );
  const setDashboard = useSetAtom(dashboardAtom);

  const { saveDashboard } = useSaveDashboard();

  const dirty = useDashboardDirty(
    (panels || []).map((panel) =>
      formatPanel({ federatedWidgets, panel, staticPanel: false })
    )
  );

  const [searchParams, setSearchParams] = useSearchParams(
    window.location.search
  );

  const startEditing = useCallback(() => {
    switchPanelsEditionMode(true);
    if (searchParams.get('edit') !== 'true') {
      searchParams.set('edit', 'true');
      setSearchParams(searchParams);
    }
  }, [searchParams, setSearchParams]);

  const stopEditing = useCallback(() => {
    switchPanelsEditionMode(false);
    searchParams.delete('edit');
    setSearchParams(searchParams);
  }, [searchParams, setSearchParams]);

  const cancel = useCallback(() => {
    stopEditing();

    const dashboard = queryClient.getQueryData<Dashboard>([
      'dashboard',
      dashboardId
    ]);
    const basePanels = getPanels(dashboard);

    setDashboard({
      layout:
        basePanels.map((panel) => formatPanel({ federatedWidgets, panel })) ||
        []
    });
    queryClient.getQueryData(['dashboard', dashboardId]);
  }, []);

  useEffect(() => {
    if (equals(searchParams.get('edit'), 'true')) {
      startEditing();

      return;
    }
    stopEditing();
  }, []);

  const saveAndProceed = (): void => {
    saveDashboard();
    stopEditing();
  };

  if (!isEditing) {
    return (
      <Button
        aria-label={t(labelEditDashboard) as string}
        data-testid="edit_dashboard"
        disabled={!!isFetchingDashboard}
        icon={<EditOutlinedIcon />}
        iconVariant="start"
        size="small"
        variant="ghost"
        onClick={startEditing}
      >
        {t(labelEditDashboard)}
      </Button>
    );
  }

  return (
    <div className={classes.root}>
      <Button
        aria-label={t(labelCancel) as string}
        data-testid="cancel_dashboard"
        size="small"
        variant="ghost"
        onClick={cancel}
      >
        {t(labelCancel)}
      </Button>
      <Button
        aria-label={t(labelSave) as string}
        data-testid="save_dashboard"
        disabled={!dirty}
        size="small"
        variant="primary"
        onClick={saveAndProceed}
      >
        {t(labelSave)}
      </Button>
    </div>
  );
};

export { DashboardEditActions };
