import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ErrorIcon from '@mui/icons-material/Error';
import {
  Box,
  Button,
  CircularProgress,
  Tooltip,
  Typography
} from '@mui/material';

import axios from 'axios';
import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { JSX, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { baseEndpoint } from '../../../api/endpoint';
import { formStateAtom } from '../../ConfigurationBase/atoms';
import { getApiErrorMessage, testConnectionEndpoint } from '../api';
import {
  labelConnectionFailed,
  labelConnectionSucceeded,
  labelTestConnection,
  labelTestConnectionHint,
  labelTestingConnection
} from '../translatedLabels';
import { isSameEndpoint } from '../utils';

interface FormValues {
  apiToken?: string | null;
  defaultModel?: string | null;
  endpointUrl?: string | null;
  type?: string | null;
}

interface TestResult {
  message?: string | null;
  model?: string | null;
  success: boolean;
}

const TestConnectionButton = (): JSX.Element => {
  const { t } = useTranslation();
  const { values, initialValues } = useFormikContext<FormValues>();
  const { mode, id } = useAtomValue(formStateAtom);

  const [isTesting, setIsTesting] = useState(false);
  const [result, setResult] = useState<TestResult | null>(null);

  const isEditing = equals(mode, 'edit');
  const hasTypedToken = Boolean(values.apiToken);
  // the stored token is only reused against the endpoint it was configured for
  const canUseStoredToken =
    isEditing && isSameEndpoint(values.endpointUrl, initialValues.endpointUrl);
  // a token is available to test with when one is typed, or when editing
  // with an unchanged endpoint; a model is also required
  const canTest =
    Boolean(values.defaultModel) && (hasTypedToken || canUseStoredToken);

  const test = (): void => {
    setIsTesting(true);
    setResult(null);

    axios
      .post<TestResult>(`${baseEndpoint}${testConnectionEndpoint}`, {
        default_model: values.defaultModel,
        type: values.type,
        ...(hasTypedToken ? { api_token: values.apiToken } : {}),
        ...(values.endpointUrl ? { endpoint_url: values.endpointUrl } : {}),
        ...(isEditing && id ? { provider_id: id } : {})
      })
      .then(({ data }) => setResult(data))
      .catch((error) =>
        setResult({ message: getApiErrorMessage(error), success: false })
      )
      .finally(() => setIsTesting(false));
  };

  return (
    <Box sx={{ alignItems: 'center', display: 'flex', gap: 1 }}>
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
          color="success.main"
          sx={{ alignItems: 'center', display: 'flex', gap: 0.5 }}
          variant="body2"
        >
          <CheckCircleIcon fontSize="small" />
          {t(labelConnectionSucceeded)}
          {result.model ? ` (${result.model})` : ''}
        </Typography>
      )}
      {result && !result.success && (
        <Typography
          color="error.main"
          sx={{ alignItems: 'center', display: 'flex', gap: 0.5 }}
          variant="body2"
        >
          <ErrorIcon fontSize="small" />
          {result.message || t(labelConnectionFailed)}
        </Typography>
      )}
    </Box>
  );
};

export default TestConnectionButton;
