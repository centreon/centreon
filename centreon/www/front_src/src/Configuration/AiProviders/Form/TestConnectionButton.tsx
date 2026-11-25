import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ErrorIcon from '@mui/icons-material/Error';
import InfoIcon from '@mui/icons-material/InfoOutlined';
import WarningIcon from '@mui/icons-material/Warning';
import { Button, CircularProgress, Tooltip, Typography } from '@mui/material';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { JSX, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { formStateAtom } from '../../ConfigurationBase/atoms';
import {
  type ConnectionTestResult,
  connectionTestResultDecoder,
  testConnectionEndpoint
} from '../api';
import useProviderCall from '../api/useProviderCall';
import {
  AiProviderType,
  ConnectionTestWarning,
  ReasoningMode
} from '../models';
import {
  labelConnectionFailed,
  labelConnectionSucceeded,
  labelConnectionSucceededWithModel,
  labelConnectionSucceededWithModelAndReasoning,
  labelConnectionSucceededWithReasoning,
  labelConnectionWarningBudgetUsed,
  labelConnectionWarningEmptyAnswer,
  labelConnectionWarningNoReasoningSetting,
  labelConnectionWarningRefused,
  labelConnectionWarningUnknown,
  labelTestConnection,
  labelTestConnectionHint,
  labelTestingConnection
} from '../translatedLabels';
import { isSameEndpoint } from '../utils';

interface FormValues {
  apiToken?: string | null;
  defaultModel?: string | null;
  defaultModelReasoning?: ReasoningMode | null;
  endpointUrl?: string | null;
  type?: AiProviderType | null;
}

interface TestedResult extends ConnectionTestResult {
  // the inputs the result was obtained with
  inputs: string;
}

const getInputs = ({
  apiToken,
  defaultModel,
  defaultModelReasoning,
  endpointUrl,
  type
}: FormValues): string =>
  JSON.stringify([
    type,
    endpointUrl,
    apiToken,
    defaultModel,
    defaultModelReasoning
  ]);

const getSuccessLabel = ({
  model,
  reasoning
}: ConnectionTestResult): string => {
  if (model && reasoning) {
    return labelConnectionSucceededWithModelAndReasoning;
  }

  if (model) {
    return labelConnectionSucceededWithModel;
  }

  return reasoning
    ? labelConnectionSucceededWithReasoning
    : labelConnectionSucceeded;
};

// an answer the AI features would reject is a warning; a model that accepts no
// setting to reason less, most often one that does not reason, is a note
const notes: Record<
  ConnectionTestWarning,
  { isWarning: boolean; label: string }
> = {
  [ConnectionTestWarning.BudgetUsedWithoutAnswer]: {
    isWarning: true,
    label: labelConnectionWarningBudgetUsed
  },
  [ConnectionTestWarning.EmptyAnswer]: {
    isWarning: true,
    label: labelConnectionWarningEmptyAnswer
  },
  [ConnectionTestWarning.NoReasoningSetting]: {
    isWarning: false,
    label: labelConnectionWarningNoReasoningSetting
  },
  [ConnectionTestWarning.Refused]: {
    isWarning: true,
    label: labelConnectionWarningRefused
  }
};

// a code from a newer server than this page
const unknownNote = { isWarning: false, label: labelConnectionWarningUnknown };

const getNote = (
  warning?: string | null
): { isWarning: boolean; label: string } | undefined => {
  if (!warning) {
    return undefined;
  }

  return Object.values<string>(ConnectionTestWarning).includes(warning)
    ? notes[warning as ConnectionTestWarning]
    : unknownNote;
};

const TestConnectionButton = (): JSX.Element => {
  const { t } = useTranslation();
  const { values, initialValues } = useFormikContext<FormValues>();
  const { mode, id } = useAtomValue(formStateAtom);

  const { call, isCalling: isTesting } = useProviderCall({
    decoder: connectionTestResultDecoder,
    endpoint: testConnectionEndpoint
  });
  const [testedResult, setTestedResult] = useState<TestedResult | null>(null);

  const isEditing = equals(mode, 'edit');
  const hasTypedToken = Boolean(values.apiToken);
  // the stored token is only reused against the endpoint it was configured for
  const canUseStoredToken =
    isEditing && isSameEndpoint(values.endpointUrl, initialValues.endpointUrl);
  const canTest =
    Boolean(values.defaultModel) && (hasTypedToken || canUseStoredToken);

  // a result only holds for the inputs it was obtained with: it is hidden as
  // soon as one of them changes, including while the test is running
  const inputs = getInputs(values);
  const result = equals(testedResult?.inputs, inputs) ? testedResult : null;
  const note = result?.success ? getNote(result.warning) : undefined;

  const test = async (): Promise<void> => {
    setTestedResult(null);

    const { data, error } = await call({
      default_model: values.defaultModel,
      type: values.type,
      ...(values.defaultModelReasoning
        ? { default_model_reasoning: values.defaultModelReasoning }
        : {}),
      ...(hasTypedToken ? { api_token: values.apiToken } : {}),
      ...(values.endpointUrl ? { endpoint_url: values.endpointUrl } : {}),
      ...(isEditing && id ? { provider_id: id } : {})
    });

    setTestedResult({
      ...(data ?? { message: error, success: false }),
      inputs
    });
  };

  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-center gap-2">
        <Tooltip title={t(labelTestConnectionHint)}>
          <span>
            <Button
              data-testid={labelTestConnection}
              disabled={!canTest || isTesting}
              onClick={test}
              size="small"
              variant="outlined"
            >
              {isTesting ? t(labelTestingConnection) : t(labelTestConnection)}
            </Button>
          </span>
        </Tooltip>
        {isTesting && <CircularProgress size={16} />}
        {result?.success && (
          <Typography
            className="flex items-center gap-1"
            color="success.main"
            variant="body2"
          >
            <CheckCircleIcon fontSize="small" />
            {t(getSuccessLabel(result), {
              interpolation: { escapeValue: false },
              model: result.model,
              reasoning: result.reasoning
            })}
          </Typography>
        )}
        {result && !result.success && (
          <Typography
            className="flex items-center gap-1"
            color="error.main"
            variant="body2"
          >
            <ErrorIcon fontSize="small" />
            {result.message || t(labelConnectionFailed)}
          </Typography>
        )}
      </div>
      {note && (
        <Typography
          className="flex items-center gap-1"
          color={note.isWarning ? 'warning.main' : 'text.secondary'}
          data-testid="connectionTestWarning"
          variant="body2"
        >
          {note.isWarning ? (
            <WarningIcon fontSize="small" />
          ) : (
            <InfoIcon fontSize="small" />
          )}
          {t(note.label, {
            interpolation: { escapeValue: false },
            reason: result?.message
          })}
        </Typography>
      )}
    </div>
  );
};

export default TestConnectionButton;
