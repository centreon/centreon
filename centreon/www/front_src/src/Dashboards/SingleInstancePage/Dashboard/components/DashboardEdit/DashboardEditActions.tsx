import EditOutlinedIcon from '@mui/icons-material/EditOutlined';

import { Button } from '@centreon/ui/components';
import { federatedWidgetsAtom } from '@centreon/ui-context';

import { useIsFetching } from '@tanstack/react-query';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { ReactElement, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import { DashboardPanel } from '../../../../api/models';
import { isEditingAtom, switchPanelsEditionModeDerivedAtom } from '../../atoms';
import { formatPanel, routerParams } from '../../hooks/useDashboardDetails';
import useDashboardDirty from '../../hooks/useDashboardDirty';
import useResetDashboardFromSavedState from '../../hooks/useResetDashboardFromSavedState';
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

  const isFetchingDashboard = useIsFetching({
    queryKey: ['dashboard', dashboardId]
  });

  const federatedWidgets = useAtomValue(federatedWidgetsAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );

  const { saveDashboard } = useSaveDashboard();

  const dirty = useDashboardDirty(
    (panels || []).map((panel) =>
      formatPanel({ federatedWidgets, panel, staticPanel: false })
    )
  );

  const [searchParams, setSearchParams] = useSearchParams(
    window.location.search
  );

  const resetDashboardFromSavedState = useResetDashboardFromSavedState();

  const startEditing = useCallback(() => {
    resetDashboardFromSavedState();
    switchPanelsEditionMode(true);
    if (searchParams.get('edit') !== 'true') {
      searchParams.set('edit', 'true');
      setSearchParams(searchParams);
    }
  }, [searchParams, setSearchParams, resetDashboardFromSavedState]);

  const stopEditing = useCallback(() => {
    switchPanelsEditionMode(false);
    searchParams.delete('edit');
    setSearchParams(searchParams);
  }, [searchParams, setSearchParams]);

  const cancel = useCallback(() => {
    stopEditing();
    resetDashboardFromSavedState();
  }, [stopEditing, resetDashboardFromSavedState]);

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
        onClick={startEditing}
        size="small"
        variant="primary"
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
        onClick={cancel}
        size="small"
        variant="secondary"
      >
        {t(labelCancel)}
      </Button>
      <Button
        aria-label={t(labelSave) as string}
        data-testid="save_dashboard"
        disabled={!dirty}
        onClick={saveAndProceed}
        size="small"
        variant="primary"
      >
        {t(labelSave)}
      </Button>
    </div>
  );
};

export { DashboardEditActions };
