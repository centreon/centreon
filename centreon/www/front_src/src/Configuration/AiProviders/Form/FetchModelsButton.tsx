import ListIcon from '@mui/icons-material/FormatListBulleted';
import { Button, CircularProgress, Tooltip, Typography } from '@mui/material';

import { useFormikContext } from 'formik';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { JSX, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { formStateAtom } from '../../ConfigurationBase/atoms';
import { discoveredModelsDecoder, discoverModelsEndpoint } from '../api';
import useProviderCall from '../api/useProviderCall';
import { discoveredModelsAtom } from '../atoms';
import { AiProviderType } from '../models';
import {
  labelFetchingModels,
  labelFetchModels,
  labelFetchModelsFailed,
  labelFetchModelsHint,
  labelModelsFetched,
  labelNoModelsFound
} from '../translatedLabels';
import { isApiTokenOptional, isSameEndpoint } from '../utils';
import { useLatestInputs } from './useLatestInputs';

interface FormValues {
  apiToken?: string | null;
  endpointUrl?: string | null;
  type?: AiProviderType | null;
}

type Status = 'failed' | 'fetched' | 'none';

const FetchModelsButton = (): JSX.Element => {
  const { t } = useTranslation();
  const { values, initialValues } = useFormikContext<FormValues>();
  const { mode, id } = useAtomValue(formStateAtom);
  const setDiscoveredModels = useSetAtom(discoveredModelsAtom);

  const { call, isCalling: isFetching } = useProviderCall({
    decoder: discoveredModelsDecoder,
    endpoint: discoverModelsEndpoint
  });
  const [status, setStatus] = useState<Status | null>(null);
  const [failureMessage, setFailureMessage] = useState<string | null>(null);

  const isEditing = equals(mode, 'edit');
  const hasTypedToken = Boolean(values.apiToken);
  // the stored token is only reused against the endpoint it was configured for
  const canUseStoredToken =
    isEditing && isSameEndpoint(values.endpointUrl, initialValues.endpointUrl);
  // an OpenAI-compatible server may need no token
  const canFetch =
    hasTypedToken || canUseStoredToken || isApiTokenOptional(values.type);

  // the models of another provider, endpoint or token are not offered
  const startRequest = useLatestInputs(
    JSON.stringify([mode, id, values.type, values.endpointUrl, values.apiToken])
  );

  const fetchModels = async (): Promise<void> => {
    setStatus(null);
    setFailureMessage(null);

    const isLatest = startRequest();

    const { data, error } = await call({
      type: values.type,
      ...(hasTypedToken ? { api_token: values.apiToken } : {}),
      ...(values.endpointUrl ? { endpoint_url: values.endpointUrl } : {}),
      ...(isEditing && id ? { provider_id: id } : {})
    });

    if (!isLatest()) {
      return;
    }

    if (!data) {
      setFailureMessage(error);
      setStatus('failed');

      return;
    }

    setDiscoveredModels({ models: data.models, type: values.type ?? null });
    setStatus(data.models.length > 0 ? 'fetched' : 'none');
  };

  const message: Record<Status, { color: string; label: string }> = {
    failed: {
      color: 'error.main',
      label: failureMessage ?? t(labelFetchModelsFailed)
    },
    fetched: { color: 'success.main', label: t(labelModelsFetched) },
    none: { color: 'warning.main', label: t(labelNoModelsFound) }
  };

  return (
    <div className="flex items-center gap-2">
      <Tooltip title={t(labelFetchModelsHint)}>
        <span>
          <Button
            data-testid={labelFetchModels}
            disabled={!canFetch || isFetching}
            onClick={fetchModels}
            size="small"
            startIcon={<ListIcon />}
            variant="outlined"
          >
            {isFetching ? t(labelFetchingModels) : t(labelFetchModels)}
          </Button>
        </span>
      </Tooltip>
      {isFetching && <CircularProgress size={16} />}
      {status && (
        <Typography color={message[status].color} variant="body2">
          {message[status].label}
        </Typography>
      )}
    </div>
  );
};

export default FetchModelsButton;
