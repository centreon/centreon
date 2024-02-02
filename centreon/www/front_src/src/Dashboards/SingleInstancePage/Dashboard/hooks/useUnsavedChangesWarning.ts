import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { isEditingAtom } from '../atoms';
import { labelUnsavedChanges } from '../translatedLabels';
import { DashboardPanel } from '../../../api/models';

import { formatPanel } from './useDashboardDetails';
import useDashboardDirty from './useDashboardDirty';

import { federatedWidgetsAtom } from 'www/front_src/src/federatedModules/atoms';

interface Props {
  panels?: Array<DashboardPanel>;
}
const useUnsavedChangesWarning = ({ panels }: Props): string => {
  const { t } = useTranslation();

  const isEditing = useAtomValue(isEditingAtom);
  const federatedWidgets = useAtomValue(federatedWidgetsAtom);

  const dirty = useDashboardDirty(
    (panels || []).map((panel) =>
      formatPanel({ federatedWidgets, panel, staticPanel: false })
    )
  );

  return dirty && isEditing ? t(labelUnsavedChanges) : '';
};

export default useUnsavedChangesWarning;
