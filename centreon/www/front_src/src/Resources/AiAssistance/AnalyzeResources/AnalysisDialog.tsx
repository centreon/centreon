import { useTranslation } from 'react-i18next';

import { Resource } from '../../models';
import AiResultDialog from '../AiResultDialog';
import { AiResult } from '../models';
import {
  labelAiAnalysis,
  labelAiAnalysisDisclaimer,
  labelAiExplanationDisclaimer,
  labelAiExplanationOf
} from '../translatedLabels';
import { unescaped } from '../utils';

interface Props {
  // the single resource being explained; null when several are analyzed
  explainedResource: Resource | null;
  onClose: () => void;
  open: boolean;
  result: AiResult | null;
}

// The answer of useAnalyzeOrExplain, titled after what was asked.
const AnalysisDialog = ({
  explainedResource,
  onClose,
  open,
  result
}: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <AiResultDialog
      disclaimer={t(
        explainedResource
          ? labelAiExplanationDisclaimer
          : labelAiAnalysisDisclaimer
      )}
      host={explainedResource?.parent?.name}
      onClose={onClose}
      open={open}
      result={result}
      title={
        explainedResource
          ? t(labelAiExplanationOf, {
              name: explainedResource.name,
              ...unescaped
            })
          : t(labelAiAnalysis)
      }
    />
  );
};

export default AnalysisDialog;
