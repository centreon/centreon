import { useCallback } from 'react';

import { useAtomValue } from 'jotai';
import pluralize from 'pluralize';
import { equals, includes } from 'ramda';
import { useTranslation } from 'react-i18next';

import { userAtom } from '@centreon/ui-context';

interface TProps {
  count: number;
  label: string;
}

export const usePluralizedTranslation = (): {
  pluralizedT: (props: TProps) => string;
} => {
  const translation = useTranslation();
  const { locale } = useAtomValue(userAtom);

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
