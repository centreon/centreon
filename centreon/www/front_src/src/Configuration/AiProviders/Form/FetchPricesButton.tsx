import DownloadIcon from '@mui/icons-material/CloudDownload';
import {
  Box,
  Button,
  CircularProgress,
  Tooltip,
  Typography
} from '@mui/material';

import axios from 'axios';
import { useFormikContext } from 'formik';
import { isNil } from 'ramda';
import { JSX, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { baseEndpoint } from '../../../api/endpoint';
import { modelPricesEndpoint } from '../api';
import {
  labelFetchingPrices,
  labelFetchPrices,
  labelFetchPricesFailed,
  labelFetchPricesHint,
  labelPricesFilled,
  labelPricesNotFound
} from '../translatedLabels';

interface FormValues {
  currency?: string | null;
  defaultModel?: string | null;
  fastModel?: string | null;
}

interface PricesResponse {
  default_model_input: number | null;
  default_model_output: number | null;
  fast_model_input: number | null;
  fast_model_output: number | null;
}

type Status = 'failed' | 'filled' | 'notFound';

const FetchPricesButton = (): JSX.Element => {
  const { t } = useTranslation();
  const { values, setFieldValue } = useFormikContext<FormValues>();

  const [isFetching, setIsFetching] = useState(false);
  const [status, setStatus] = useState<Status | null>(null);

  // LiteLLM prices are in USD: only offer the prefill when the currency is
  // empty (it will be set to USD) or already USD, never mixing units
  const currencyAllowsUsd =
    !values.currency || values.currency.toUpperCase() === 'USD';
  const canFetch = Boolean(values.defaultModel) && currencyAllowsUsd;

  const fetchPrices = (): void => {
    setIsFetching(true);
    setStatus(null);

    axios
      .post<PricesResponse>(`${baseEndpoint}${modelPricesEndpoint}`, {
        default_model: values.defaultModel,
        ...(values.fastModel ? { fast_model: values.fastModel } : {})
      })
      .then(({ data }) => {
        const filled = [
          ['priceDefaultInput', data.default_model_input],
          ['priceDefaultOutput', data.default_model_output],
          ['priceFastInput', data.fast_model_input],
          ['priceFastOutput', data.fast_model_output]
        ].reduce((count, [field, value]) => {
          if (isNil(value)) {
            return count;
          }
          setFieldValue(field as string, value);

          return count + 1;
        }, 0);

        if (filled === 0) {
          setStatus('notFound');

          return;
        }

        if (!values.currency) {
          setFieldValue('currency', 'USD');
        }
        setStatus('filled');
      })
      .catch(() => setStatus('failed'))
      .finally(() => setIsFetching(false));
  };

  const message: Record<Status, { color: string; label: string }> = {
    failed: { color: 'error.main', label: t(labelFetchPricesFailed) },
    filled: { color: 'success.main', label: t(labelPricesFilled) },
    notFound: { color: 'warning.main', label: t(labelPricesNotFound) }
  };

  return (
    <Box sx={{ alignItems: 'center', display: 'flex', gap: 1 }}>
      <Tooltip title={t(labelFetchPricesHint)}>
        <span>
          <Button
            data-testid={labelFetchPrices}
            disabled={!canFetch || isFetching}
            onClick={fetchPrices}
            size="small"
            startIcon={<DownloadIcon />}
            variant="outlined"
          >
            {isFetching ? t(labelFetchingPrices) : t(labelFetchPrices)}
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

export default FetchPricesButton;
