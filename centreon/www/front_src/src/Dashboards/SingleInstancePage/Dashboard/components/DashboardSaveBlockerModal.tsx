import { ConfirmationModal } from '@centreon/ui/components';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { DashboardPanel } from '../../../api/models';
import { isEditingAtom, isRedirectionBlockedAtom } from '../atoms';
import useDashboardSaveBlocker from '../hooks/useDashboardSaveBlocker';
import useSaveDashboard from '../hooks/useSaveDashboard';
import {
  labelDiscard,
  labelDoYouWantToSaveChanges,
  labelIfYouClickOnDiscard,
  labelSave
} from '../translatedLabels';

interface Props {
  panels?: Array<DashboardPanel>;
}

const DashboardSaveBlockerModal = ({ panels }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { proceedNavigation, blockNavigation } =
    useDashboardSaveBlocker(panels);
  const { saveDashboard } = useSaveDashboard();

  const setIsEditing = useSetAtom(isEditingAtom);

  const close = (): void => {
    blockNavigation?.();
  };

  const cancel = (): void => {
    proceedNavigation?.();
    setIsEditing(false);
  };

  const confirm = (): void => {
    saveDashboard();
    proceedNavigation?.();
    setIsEditing(false);
  };

  return (
    <ConfirmationModal
      size="medium"
      hasCloseButton
      atom={isRedirectionBlockedAtom}
      labels={{
        cancel: t(labelDiscard),
        confirm: t(labelSave),
        description: t(labelIfYouClickOnDiscard),
        title: t(labelDoYouWantToSaveChanges)
      }}
      onCancel={cancel}
      onClose={close}
      onConfirm={confirm}
    />
  );
};

export default DashboardSaveBlockerModal;
