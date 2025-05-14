import { ConfirmationModal } from '@centreon/ui/components';
import { useTranslation } from 'react-i18next';
import {
  labelDoYouWantToLeaveThisInputMode,
  labelLeave,
  labelStay,
  labelYourChangesWillNotBeSavedIfYouSwitch
} from '../../../../translatedLabels';
import { WidgetResourceType } from '../../../models';
import {
  ResourceTypeToToggleRegexAtom,
  resourceTypeToToggleRegexAtom
} from './atoms';

interface Props {
  changeRegexFieldOnResourceType: ({
    resourceType,
    index,
    bypassResourcesCheck
  }: {
    resourceType: WidgetResourceType;
    index: number;
    bypassResourcesCheck?: boolean;
  }) => () => void;
}

const ConfirmationResourceTypeToggleRegexModal = ({
  changeRegexFieldOnResourceType
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const confirm = ({ resourceType, index }: ResourceTypeToToggleRegexAtom) => {
    changeRegexFieldOnResourceType({
      resourceType,
      index,
      bypassResourcesCheck: true
    })();
  };

  return (
    <ConfirmationModal
      atom={resourceTypeToToggleRegexAtom}
      labels={{
        cancel: t(labelStay),
        confirm: t(labelLeave),
        description: t(labelYourChangesWillNotBeSavedIfYouSwitch),
        title: t(labelDoYouWantToLeaveThisInputMode)
      }}
      onConfirm={confirm}
    />
  );
};

export default ConfirmationResourceTypeToggleRegexModal;
