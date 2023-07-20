import { ReactElement, useCallback, useEffect } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';
import { useSearchParams } from 'react-router-dom';

import EditOutlinedIcon from '@mui/icons-material/EditOutlined';

import { Button } from '@centreon/ui/components';

import { DashboardPanel } from '../../../api/models';
import { formatPanel } from '../../useDashboardDetails';
import useDashboardDirty from '../../useDashboardDirty';
import useSaveDashboard from '../../useSaveDashboard';
import { isEditingAtom, switchPanelsEditionModeDerivedAtom } from '../../atoms';
import {
  labelEditDashboard,
  labelExit,
  labelSave
} from '../../translatedLabels';

interface DashboardEditActionsProps {
  panels?: Array<DashboardPanel>;
}

const DashboardEditActions = ({
  panels
}: DashboardEditActionsProps): ReactElement => {
  const { t } = useTranslation();

  const isEditing = useAtomValue(isEditingAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );

  const { saveDashboard } = useSaveDashboard();

  const dirty = useDashboardDirty(
    (panels || []).map((panel) => formatPanel({ panel, staticPanel: false }))
  );

  const [searchParams, setSearchParams] = useSearchParams();

  const startEditing = useCallback(() => {
    switchPanelsEditionMode(true);
    if (searchParams.get('edit') !== 'true') {
      searchParams.set('edit', 'true');
      setSearchParams(searchParams);
    }
  }, [searchParams, setSearchParams]);

  const stopEditing = useCallback(() => {
    switchPanelsEditionMode(false);
    if (searchParams.get('edit') !== null) {
      searchParams.delete('edit');
      setSearchParams(searchParams);
    }
  }, [searchParams, setSearchParams]);

  useEffect(() => {
    if (searchParams.get('edit') === 'true') startEditing();
    if (searchParams.get('edit') === null) stopEditing();
  }, [searchParams]);

  const saveAndProceed = (): void => {
    saveDashboard();
    switchPanelsEditionMode(false);
  };

  if (!isEditing) {
    return (
      <Button
        data-testid="edit_dashboard"
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
    <>
      <Button
        aria-label={t(labelExit) as string}
        data-testid="cancel_dashboard"
        size="small"
        variant="ghost"
        onClick={stopEditing}
      >
        {t(labelExit)}
      </Button>
      <Button
        aria-label={t(labelSave) as string}
        data-testid="save_dashboard"
        disabled={!dirty}
        size="small"
        variant="ghost"
        onClick={saveAndProceed}
      >
        {t(labelSave)}
      </Button>
    </>
  );
};

export { DashboardEditActions };
