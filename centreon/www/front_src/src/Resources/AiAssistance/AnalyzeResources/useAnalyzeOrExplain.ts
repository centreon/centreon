import { ResourceDetails } from '../../Details/models';
import { Resource } from '../../models';
import useExplainResource from '../ExplainResource/useExplainResource';
import { AiResult } from '../models';
import useAnalyzeResources from './useAnalyzeResources';

// A single resource is explained on its own; two or more are correlated to
// surface a likely common cause. Resolves to null when the call failed (the
// error snackbar already says why).
const useAnalyzeOrExplain = (): ((
  resources: Array<Resource>
) => Promise<AiResult | null>) => {
  const { analyze } = useAnalyzeResources();
  const { explain } = useExplainResource();

  return (resources) =>
    resources.length === 1
      ? explain(resources[0] as unknown as ResourceDetails).then(
          (explanation) =>
            explanation && {
              content: explanation.explanation,
              model: explanation.model,
              truncated: explanation.truncated ?? false
            }
        )
      : analyze(resources).then(
          (analysis) =>
            analysis && {
              content: analysis.analysis,
              model: analysis.model,
              truncated: analysis.truncated ?? false
            }
        );
};

export default useAnalyzeOrExplain;
