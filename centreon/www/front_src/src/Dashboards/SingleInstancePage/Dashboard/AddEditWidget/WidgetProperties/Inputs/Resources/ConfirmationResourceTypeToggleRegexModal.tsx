import { ConfirmationModal } from '@centreon/ui/components';
import {
  ResourceTypeToToggleRegexAtom,
  resourceTypeToToggleRegexAtom
} from './atoms';
import { WidgetResourceType } from '../../../models';

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
        cancel: 'Cancel',
        confirm: 'Confirm',
        description: 'Description',
        title: 'Title'
      }}
      onConfirm={confirm}
    />
  );
};

export default ConfirmationResourceTypeToggleRegexModal;
