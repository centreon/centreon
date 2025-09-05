import { useCallback } from 'react';

import pluralize from 'pluralize';
import { equals, includes } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useLocale } from './useLocale';

interface TProps {
  count: number;
  label: string;
}

export const usePluralizedTranslation = (): {
  pluralizedT: (props: TProps) => string;
} => {
  const translation = useTranslation();
  const locale = useLocale();

  const isNotPartitiveLocale = includes('fr', locale);

  const pluralizedT = useCallback(
    ({ label, count }: TProps): string => {
      const isZero = equals(count, 0);

      return pluralize(
        translation.t(label),
        isZero && isNotPartitiveLocale ? 1 : count
      );
    },
    [isNotPartitiveLocale]
  );

  return {
    pluralizedT
  };
};
