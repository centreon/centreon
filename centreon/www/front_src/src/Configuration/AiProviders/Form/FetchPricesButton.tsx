import DownloadIcon from '@mui/icons-material/CloudDownload';
import { Button, CircularProgress, Tooltip, Typography } from '@mui/material';

import { useFormikContext } from 'formik';
import { isNil } from 'ramda';
import { JSX, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { modelPricesDecoder, modelPricesEndpoint } from '../api';
import useProviderCall from '../api/useProviderCall';
import { AiProviderType } from '../models';
import {
  labelFetchingPrices,
  labelFetchPrices,
  labelFetchPricesFailed,
  labelFetchPricesHint,
  labelPricesFilled,
  labelPricesNotFound
} from '../translatedLabels';
import { useLatestInputs } from './useLatestInputs';

interface FormValues {
  currency?: string | null;
  defaultModel?: string | null;
  fastModel?: string | null;
  type?: AiProviderType | null;
}

type Status = 'failed' | 'filled' | 'notFound';

const FetchPricesButton = (): JSX.Element => {
  const { t } = useTranslation();
  const { values, setFieldValue } = useFormikContext<FormValues>();

  const { call, isCalling: isFetching } = useProviderCall({
    decoder: modelPricesDecoder,
    endpoint: modelPricesEndpoint
  });
  const [status, setStatus] = useState<Status | null>(null);
  const [failureMessage, setFailureMessage] = useState<string | null>(null);

  // LiteLLM prices are in USD: only offer the prefill when the currency is
  // empty (it will be set to USD) or already USD, never mixing units
  const currencyAllowsUsd =
    !values.currency || values.currency.toUpperCase() === 'USD';
  const canFetch = Boolean(values.defaultModel) && currencyAllowsUsd;

  // prices found for other models, or a currency changed meanwhile, are not
  // applied
  const startRequest = useLatestInputs(
    JSON.stringify([
      values.type,
      values.defaultModel,
      values.fastModel,
      currencyAllowsUsd
    ])
  );

  const fetchPrices = async (): Promise<void> => {
    setStatus(null);
    setFailureMessage(null);

    const isLatest = startRequest();

    const { data, error } = await call({
      default_model: values.defaultModel,
      type: values.type,
      ...(values.fastModel ? { fast_model: values.fastModel } : {})
    });

    if (!isLatest()) {
      return;
    }

    if (!data) {
      setFailureMessage(error);
      setStatus('failed');

      return;
    }

    const filled = [
      ['priceDefaultInput', data.defaultModelInput],
      ['priceDefaultOutput', data.defaultModelOutput],
      ['priceFastInput', data.fastModelInput],
      ['priceFastOutput', data.fastModelOutput]
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
  };

  const message: Record<Status, { color: string; label: string }> = {
    failed: {
      color: 'error.main',
      label: failureMessage ?? t(labelFetchPricesFailed)
    },
    filled: { color: 'success.main', label: t(labelPricesFilled) },
    notFound: { color: 'warning.main', label: t(labelPricesNotFound) }
  };

  return (
    <div className="flex items-center gap-2">
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
    </div>
  );
};

export default FetchPricesButton;
