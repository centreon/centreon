import { Box, LinearProgress, Tooltip, Typography } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { isNil } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { AiProviderListItem } from '../models';
import {
  labelNotMetered,
  labelThisMonth,
  labelToday
} from '../translatedLabels';

interface Window {
  label: string;
  limit: number;
  ratio: number;
  used: number;
}

const formatAmount = (
  amount: number,
  currency: string,
  language: string
): string => {
  try {
    return new Intl.NumberFormat(language, {
      currency,
      style: 'currency'
    }).format(amount);
  } catch {
    // unknown currency code: fall back to a plain number with the code
    return `${amount.toFixed(2)} ${currency}`;
  }
};

const barColor = (ratio: number): 'error' | 'primary' | 'warning' => {
  if (ratio >= 1) {
    return 'error';
  }

  return ratio >= 0.8 ? 'warning' : 'primary';
};

const Usage = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t, i18n } = useTranslation();

  const { currency, limitDaily, limitMonthly, usedDaily, usedMonthly } =
    row as unknown as AiProviderListItem;

  // no currency means no pricing: nothing is metered for this provider
  if (isNil(currency) || currency === '') {
    return (
      <Typography color="text.secondary" variant="body2">
        {t(labelNotMetered)}
      </Typography>
    );
  }

  const format = (amount: number): string =>
    formatAmount(amount, currency, i18n.language);

  const windows: Array<Window> = [
    { key: 'daily', label: t(labelToday), limit: limitDaily, used: usedDaily },
    {
      key: 'monthly',
      label: t(labelThisMonth),
      limit: limitMonthly,
      used: usedMonthly
    }
  ]
    .filter((window) => !isNil(window.limit) && window.limit > 0)
    .map((window) => ({
      label: window.label,
      limit: window.limit as number,
      ratio: (window.used ?? 0) / (window.limit as number),
      used: window.used ?? 0
    }));

  const tooltip = (
    <Box>
      <Typography variant="caption">
        {t(labelToday)}: {format(usedDaily ?? 0)}
        {!isNil(limitDaily) && ` / ${format(limitDaily)}`}
      </Typography>
      <br />
      <Typography variant="caption">
        {t(labelThisMonth)}: {format(usedMonthly ?? 0)}
        {!isNil(limitMonthly) && ` / ${format(limitMonthly)}`}
      </Typography>
    </Box>
  );

  // no limit configured: show the spend of the current month, without a bar
  if (windows.length === 0) {
    return (
      <Tooltip title={tooltip}>
        <Typography variant="body2">
          {format(usedMonthly ?? 0)} {t(labelThisMonth)}
        </Typography>
      </Tooltip>
    );
  }

  // surface the window closest to its cap (the one that will block first)
  const binding = windows.reduce((highest, window) =>
    window.ratio > highest.ratio ? window : highest
  );
  const percent = Math.round(binding.ratio * 100);

  return (
    <Tooltip title={tooltip}>
      <Box sx={{ minWidth: 140, width: '100%' }}>
        <Typography variant="body2">
          {format(binding.used)} / {format(binding.limit)} ({percent}%){' '}
          {binding.label}
        </Typography>
        <LinearProgress
          color={barColor(binding.ratio)}
          value={Math.min(binding.ratio * 100, 100)}
          variant="determinate"
        />
      </Box>
    </Tooltip>
  );
};

export default Usage;
