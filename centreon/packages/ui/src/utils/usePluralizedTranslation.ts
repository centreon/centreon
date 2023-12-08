import { useTranslation } from 'react-i18next';
import pluralize from 'pluralize';

interface TProps {
  count: number;
  label: string;
}

export const usePluralizedTranslation = (): {
  pluralizedT: (props: TProps) => string;
} => {
  const translation = useTranslation();

  const pluralizedT = ({ label, count }: TProps): string => {
    return pluralize(translation.t(label), count);
  };

  return {
    pluralizedT
  };
};
