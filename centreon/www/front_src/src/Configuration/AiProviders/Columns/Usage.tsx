import { LinearProgress, Tooltip, Typography } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { isNil } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { AiProviderListItem } from '../models';
import {
  labelLimitUsedThisMonth,
  labelLimitUsedToday,
  labelNotMetered,
  labelSpentThisMonth,
  labelThisMonthUsage,
  labelThisMonthUsageOfLimit,
  labelTodayUsage,
  labelTodayUsageOfLimit
} from '../translatedLabels';

interface Window {
  // the gauge text, with the used amount, the limit and the percentage
  label: string;
  limit: number;
  ratio: number;
  used: number;
}

// the amounts are formatted already: no HTML escaping on top of React's
const interpolation = { escapeValue: false };

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

  // no currency means no pricing: the usage is recorded but has no cost
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
    { label: labelLimitUsedToday, limit: limitDaily, used: usedDaily },
    { label: labelLimitUsedThisMonth, limit: limitMonthly, used: usedMonthly }
  ]
    .filter((window) => !isNil(window.limit) && window.limit > 0)
    .map((window) => {
      const limit = window.limit as number;
      const used = window.used ?? 0;
      const ratio = used / limit;

      return {
        label: t(window.label, {
          interpolation,
          limit: format(limit),
          percent: Math.round(ratio * 100),
          used: format(used)
        }),
        limit,
        ratio,
        used
      };
    });

  const describeUsage = (
    used: number | null | undefined,
    limit: number | null | undefined,
    labels: { withLimit: string; withoutLimit: string }
  ): string =>
    isNil(limit)
      ? t(labels.withoutLimit, { interpolation, used: format(used ?? 0) })
      : t(labels.withLimit, {
          interpolation,
          limit: format(limit),
          used: format(used ?? 0)
        });

  const tooltip = (
    <div>
      <Typography variant="caption">
        {describeUsage(usedDaily, limitDaily, {
          withLimit: labelTodayUsageOfLimit,
          withoutLimit: labelTodayUsage
        })}
      </Typography>
      <br />
      <Typography variant="caption">
        {describeUsage(usedMonthly, limitMonthly, {
          withLimit: labelThisMonthUsageOfLimit,
          withoutLimit: labelThisMonthUsage
        })}
      </Typography>
    </div>
  );

  // no limit configured: show the spend of the current month, without a bar
  if (windows.length === 0) {
    return (
      <Tooltip title={tooltip}>
        <Typography variant="body2">
          {t(labelSpentThisMonth, {
            amount: format(usedMonthly ?? 0),
            interpolation
          })}
        </Typography>
      </Tooltip>
    );
  }

  // surface the window closest to its cap (the one that will block first)
  const binding = windows.reduce((highest, window) =>
    window.ratio > highest.ratio ? window : highest
  );
  return (
    <Tooltip title={tooltip}>
      <div className="w-full min-w-[140px]">
        <Typography variant="body2">{binding.label}</Typography>
        <LinearProgress
          color={barColor(binding.ratio)}
          value={Math.min(binding.ratio * 100, 100)}
          variant="determinate"
        />
      </div>
    </Tooltip>
  );
};

export default Usage;
