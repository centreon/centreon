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
import { useRef } from 'react';
import { isNotNil } from 'ramda';

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
  const isRegexModeRef = useRef<boolean | undefined>(undefined);

  const resourceTypeToToggle = useAtomValue(resourceTypeToToggleRegexAtom);

  const confirm = ({ resourceType, index }: ResourceTypeToToggleRegexAtom) => {
    changeRegexFieldOnResourceType({
      resourceType,
      index,
      bypassResourcesCheck: true
    })();
  };

  if (
    isNotNil(resourceTypeToToggle?.isRegexMode) &&
    isRegexModeRef.current !== resourceTypeToToggle?.isRegexMode
  ) {
    isRegexModeRef.current = resourceTypeToToggle?.isRegexMode;
  }

  return (
    <ConfirmationModal
      atom={resourceTypeToToggleRegexAtom}
      labels={{
        cancel: t(labelStay),
        confirm: t(labelLeave),
        description: t(
          isRegexModeRef.current
            ? labelYourChangesWillNotBeSavedIfYouSwitchRegexMode
            : labelYourChangesWillNotBeSavedIfYouSwitchClassicMode
        ),
        title: t(
          isRegexModeRef.current
            ? labelDoYouWantToLeaveTheRegexMode
            : labelDoYouWantToLeaveTheClassicMode
        )
      }}
      onConfirm={confirm}
      size="medium"
    />
  );
};

export default ConfirmationResourceTypeToggleRegexModal;
