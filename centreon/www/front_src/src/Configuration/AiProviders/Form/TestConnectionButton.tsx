import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ErrorIcon from '@mui/icons-material/Error';
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

const TestConnectionButton = (): JSX.Element => {
  const { t } = useTranslation();
  const { values, initialValues } = useFormikContext<FormValues>();
  const { mode, id } = useAtomValue(formStateAtom);

  const { call, isCalling: isTesting } = useProviderCall({
    decoder: connectionTestResultDecoder,
    endpoint: testConnectionEndpoint
  });
  const [result, setResult] = useState<ConnectionTestResult | null>(null);

  const isEditing = equals(mode, 'edit');
  const hasTypedToken = Boolean(values.apiToken);
  // the stored token is only reused against the endpoint it was configured for
  const canUseStoredToken =
    isEditing && isSameEndpoint(values.endpointUrl, initialValues.endpointUrl);
  // a token is available to test with when one is typed, or when editing
  // with an unchanged endpoint; a model is also required
  const canTest =
    Boolean(values.defaultModel) && (hasTypedToken || canUseStoredToken);

  const test = async (): Promise<void> => {
    setResult(null);

    const { data, error } = await call({
      default_model: values.defaultModel,
      type: values.type,
      ...(hasTypedToken ? { api_token: values.apiToken } : {}),
      ...(values.endpointUrl ? { endpoint_url: values.endpointUrl } : {}),
      ...(isEditing && id ? { provider_id: id } : {})
    });

    setResult(data ?? { message: error, success: false });
  };

  return (
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
          {t(labelConnectionSucceeded)}
          {result.model ? ` (${result.model})` : ''}
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
  );
};

export default TestConnectionButton;
