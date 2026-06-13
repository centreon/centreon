import { Alert } from '@mui/material';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { labelCurrencyChangeResetsUsage } from '../translatedLabels';

// shown only when editing an existing provider whose currency is being changed:
// changing the currency makes past usage in the previous currency stop counting
// toward the limit (it is kept for reporting, never deleted)
const CurrencyChangeWarning = (): JSX.Element | null => {
  const { t } = useTranslation();
  const { values, initialValues } = useFormikContext<{
    currency?: string | null;
  }>();

  const hadCurrency = Boolean(initialValues.currency);
  const currencyChanged = !equals(values.currency, initialValues.currency);

  if (!hadCurrency || !currencyChanged) {
    return null;
  }

  return (
    <Alert data-testid="currencyChangeWarning" severity="warning">
      {t(labelCurrencyChangeResetsUsage)}
    </Alert>
  );
};

export default CurrencyChangeWarning;
