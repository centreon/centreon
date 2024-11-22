import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import WizardFormSetupStatus from '../../components/WizardFormSetupStatus';
import { PollerData, pollerAtom } from '../pollerAtoms';
import { labelFinalStep } from '../translatedLabels';

const PollerWizardStepThree = (): JSX.Element => {
  const { t } = useTranslation();

  const pollerData = useAtomValue<PollerData | null>(pollerAtom);

  return (
    <WizardFormSetupStatus
      error={null}
      formTitle={t(labelFinalStep)}
      statusCreating={pollerData?.submitStatus ? pollerData.submitStatus : null}
      statusGenerating={null}
    />
  );
};

export default PollerWizardStepThree;
