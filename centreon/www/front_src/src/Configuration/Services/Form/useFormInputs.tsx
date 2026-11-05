import { useTranslation } from 'react-i18next';

import { type FormInputs, getFormInputs } from '../../ResourceForm/formInputs';
import { placeBlocks } from './layout';
import { getBlocks } from './sections';

interface Props {
  canEdit: boolean;
  isAdditiveInheritanceEnabled: boolean;
  isCloudPlatform: boolean;
  isMassChange?: boolean;
}

const useFormInputs = ({
  canEdit,
  isAdditiveInheritanceEnabled,
  isCloudPlatform,
  isMassChange = false
}: Props): FormInputs => {
  const { t } = useTranslation();

  const context = {
    isAdditiveInheritanceEnabled,
    isCloudPlatform,
    isMassChange,
    t
  };

  return getFormInputs({
    canEdit,
    placedSections: placeBlocks({ blocks: getBlocks(context), t }),
    t
  });
};

export default useFormInputs;
