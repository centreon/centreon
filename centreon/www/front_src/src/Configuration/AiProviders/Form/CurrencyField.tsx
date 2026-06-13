import { Autocomplete, TextField, Typography } from '@mui/material';

import type { InputPropsWithoutGroup } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { path } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { labelCurrency, labelCurrencyPlaceholder } from '../translatedLabels';

// a short list of common currencies; any ISO 4217 code can still be typed in
const currencyNames: Record<string, string> = {
  AUD: 'Australian Dollar',
  BRL: 'Brazilian Real',
  CAD: 'Canadian Dollar',
  CHF: 'Swiss Franc',
  CNY: 'Chinese Yuan',
  EUR: 'Euro',
  GBP: 'British Pound',
  INR: 'Indian Rupee',
  JPY: 'Japanese Yen',
  USD: 'US Dollar'
};

const commonCurrencies = Object.keys(currencyNames);

// the default symbol display disambiguates regional variants (US$ vs CA$ vs A$),
// so the symbol is only ever a decoration next to the always-visible code
const currencySymbol = (code: string): string => {
  try {
    return (
      new Intl.NumberFormat('en', { currency: code, style: 'currency' })
        .formatToParts(0)
        .find((part) => part.type === 'currency')?.value ?? ''
    );
  } catch {
    // not a (valid) ISO code yet: nothing to show
    return '';
  }
};

const CurrencyField = ({ fieldName }: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const { values, errors, touched, setFieldValue, setFieldTouched } =
    useFormikContext();

  const value = (path([fieldName], values) as string | null) ?? '';
  const error = path([fieldName], touched)
    ? (path([fieldName], errors) as string | undefined)
    : undefined;

  return (
    <Autocomplete
      autoHighlight
      freeSolo
      // both value and inputValue are controlled from the form, so closing the
      // dropdown (blur or Escape) restores the typed code instead of clearing it
      inputValue={value}
      onBlur={() => setFieldTouched(fieldName, true, true)}
      onInputChange={(_event, newInput) =>
        setFieldValue(fieldName, newInput.toUpperCase())
      }
      options={commonCurrencies}
      renderInput={(params) => (
        <TextField
          {...params}
          error={Boolean(error)}
          helperText={error}
          inputProps={{ ...params.inputProps, 'data-testid': t(labelCurrency) }}
          label={t(labelCurrency)}
          placeholder={t(labelCurrencyPlaceholder)}
          size="small"
        />
      )}
      renderOption={(props, code) => {
        const { key, ...rest } = props as { key: string };

        return (
          <li key={key} {...rest}>
            <Typography
              color="textSecondary"
              component="span"
              sx={{ minWidth: 36 }}
            >
              {currencySymbol(code)}
            </Typography>
            <Typography component="span" sx={{ minWidth: 44 }}>
              {code}
            </Typography>
            <Typography color="textSecondary" component="span">
              {currencyNames[code]}
            </Typography>
          </li>
        );
      }}
      sx={{ maxWidth: 280 }}
      value={value || null}
    />
  );
};

export default CurrencyField;
