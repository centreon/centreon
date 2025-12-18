import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

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
      onClick={() => openModal(null)}
      size="small"
      variant="secondary"
    >
      {t(labelAddAWidget)}
    </Button>
  );
};

export default AddWidgetButton;
