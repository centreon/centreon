import { ConfirmationModal } from '@centreon/ui/components';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import {
  labelDoYouWantToLeaveTheClassicMode,
  labelDoYouWantToLeaveTheRegexMode,
  labelLeave,
  labelStay,
  labelYourChangesWillNotBeSavedIfYouSwitchClassicMode,
  labelYourChangesWillNotBeSavedIfYouSwitchRegexMode
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

  const resourceTypeToToggle = useAtomValue(resourceTypeToToggleRegexAtom);

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
        description: t(
          resourceTypeToToggle?.isRegexMode
            ? labelYourChangesWillNotBeSavedIfYouSwitchRegexMode
            : labelYourChangesWillNotBeSavedIfYouSwitchClassicMode
        ),
        title: t(
          resourceTypeToToggle?.isRegexMode
            ? labelDoYouWantToLeaveTheRegexMode
            : labelDoYouWantToLeaveTheClassicMode
        )
      }}
      onConfirm={confirm}
    />
  );
};

export default ConfirmationResourceTypeToggleRegexModal;
