import { useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { dashboardAtom, isEditingAtom } from '../atoms';
import { labelAddAWidget } from '../translatedLabels';

import useWidgetForm from './useWidgetModal';

const AddWidgetButton = (): JSX.Element | null => {
  const { t } = useTranslation();

  const isEditing = useAtomValue(isEditingAtom);
  const dashboard = useAtomValue(dashboardAtom);

  const { openModal } = useWidgetForm();

  const hasPanels = useMemo(
    () => !isEmpty(dashboard.layout),
    [dashboard.layout]
  );

  if (!isEditing || !hasPanels) {
    return null;
  }

  return (
    <Button
      aria-label={t(labelAddAWidget) as string}
      data-testid="add-widget"
      icon={<AddIcon />}
      iconVariant="start"
      size="small"
      variant="secondary"
      onClick={() => openModal(null)}
    >
      {t(labelAddAWidget)}
    </Button>
  );
};

export default AddWidgetButton;
