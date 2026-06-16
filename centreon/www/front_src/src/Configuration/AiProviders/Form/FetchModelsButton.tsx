import ListIcon from '@mui/icons-material/FormatListBulleted';
import {
  Box,
  Button,
  CircularProgress,
  Tooltip,
  Typography
} from '@mui/material';

import axios from 'axios';
import { useFormikContext } from 'formik';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { JSX, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { baseEndpoint } from '../../../api/endpoint';
import { modalStateAtom } from '../../ConfigurationBase/atoms';
import { discoverModelsEndpoint, getApiErrorMessage } from '../api';
import {
  labelFetchingModels,
  labelFetchModels,
  labelFetchModelsFailed,
  labelFetchModelsHint,
  labelModelsFetched,
  labelNoModelsFound
} from '../translatedLabels';
import { discoveredModelsAtom } from './discoveredModelsAtom';

interface FormValues {
  apiToken?: string | null;
  endpointUrl?: string | null;
  type?: string | null;
}

interface ModelsResponse {
  models: Array<string>;
}

type Status = 'failed' | 'fetched' | 'none';

const FetchModelsButton = (): JSX.Element => {
  const { t } = useTranslation();
  const { values } = useFormikContext<FormValues>();
  const { mode, id } = useAtomValue(modalStateAtom);
  const setDiscoveredModels = useSetAtom(discoveredModelsAtom);

  const [isFetching, setIsFetching] = useState(false);
  const [status, setStatus] = useState<Status | null>(null);
  const [failureMessage, setFailureMessage] = useState<string | null>(null);

  const isEditing = equals(mode, 'edit');
  const hasTypedToken = Boolean(values.apiToken);
  // a token is available when one is typed, or when editing (stored token)
  const canFetch = hasTypedToken || isEditing;

  const fetchModels = (): void => {
    setIsFetching(true);
    setStatus(null);
    setFailureMessage(null);

    axios
      .post<ModelsResponse>(`${baseEndpoint}${discoverModelsEndpoint}`, {
        type: values.type,
        ...(hasTypedToken ? { api_token: values.apiToken } : {}),
        ...(values.endpointUrl ? { endpoint_url: values.endpointUrl } : {}),
        ...(isEditing && id ? { provider_id: id } : {})
      })
      .then(({ data }) => {
        setDiscoveredModels({ models: data.models, type: values.type ?? null });
        setStatus(data.models.length > 0 ? 'fetched' : 'none');
      })
      .catch((error) => {
        setFailureMessage(getApiErrorMessage(error));
        setStatus('failed');
      })
      .finally(() => setIsFetching(false));
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
    <Box sx={{ alignItems: 'center', display: 'flex', gap: 1 }}>
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
    </Box>
  );
};

export default FetchModelsButton;
