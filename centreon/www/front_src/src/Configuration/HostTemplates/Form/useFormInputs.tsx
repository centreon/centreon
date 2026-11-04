import { useTranslation } from 'react-i18next';

import { type FormInputs, getFormInputs } from '../../ResourceForm/formInputs';
import { isUnlocked } from '../utils';
import { placeBlocks } from './layout';
import { getBlocks } from './sections';

interface Props {
  canEdit: boolean;
  isAdditiveInheritanceEnabled: boolean;
  isCloudPlatform: boolean;
}

const useFormInputs = ({
  canEdit,
  isAdditiveInheritanceEnabled,
  isCloudPlatform
}: Props): FormInputs => {
  const { t } = useTranslation();

  const context = { isAdditiveInheritanceEnabled, isCloudPlatform, t };

  return getFormInputs({
    canEdit,
    editable: isUnlocked,
    placedSections: placeBlocks({ blocks: getBlocks(context), t }),
    t
  });
};

export default useFormInputs;
