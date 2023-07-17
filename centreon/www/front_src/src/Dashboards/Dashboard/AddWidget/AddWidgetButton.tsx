import { useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '@centreon/ui/components';

import { labelAddAWidget } from '../translatedLabels';
import { dashboardAtom, isEditingAtom } from '../atoms';

import useAddWidget from './useAddWidget';

const AddWidgetButton = (): JSX.Element | null => {
  const { t } = useTranslation();

  const isEditing = useAtomValue(isEditingAtom);
  const dashboard = useAtomValue(dashboardAtom);

  const { openModal } = useAddWidget();

  const hasPanels = useMemo(
    () => !isEmpty(dashboard.layout),
    [dashboard.layout]
  );

  if (!isEditing || !hasPanels) {
    return null;
  }

  return (
    <Button
      aria-label="add widget"
      data-testid="add-widget"
      icon={<AddIcon />}
      iconVariant="start"
      size="small"
      onClick={openModal}
    >
      {t(labelAddAWidget)}
    </Button>
  );
};

export default AddWidgetButton;
