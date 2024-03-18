import { useSetAtom } from 'jotai';

import { ConfirmationModal } from '@centreon/ui/components';

import useDashboardSaveBlocker from '../hooks/useDashboardSaveBlocker';
import { isEditingAtom, isRedirectionBlockedAtom } from '../atoms';
import useSaveDashboard from '../hooks/useSaveDashboard';
import {
  labelDiscard,
  labelDoYouWantToSaveChanges,
  labelIfYouClickOnDiscard,
  labelSave
} from '../translatedLabels';
import { DashboardPanel } from '../../../api/models';

interface Props {
  panels?: Array<DashboardPanel>;
}

const DashboardSaveBlockerModal = ({ panels }: Props): JSX.Element => {
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
      hasCloseButton
      atom={isRedirectionBlockedAtom}
      labels={{
        cancel: labelDiscard,
        confirm: labelSave,
        description: labelIfYouClickOnDiscard,
        title: labelDoYouWantToSaveChanges
      }}
      onCancel={cancel}
      onClose={close}
      onConfirm={confirm}
    />
  );
};

export default DashboardSaveBlockerModal;
