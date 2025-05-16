import { browserLocaleAtom, userAtom } from '@centreon/ui-context';
import { useAtom, useAtomValue } from 'jotai';
import { ReactElement, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation } from 'react-router';
import { getBrowserLocale } from './utils';

interface Props {
  children: ReactElement;
}

const LanguageDetector = ({ children }: Props): ReactElement => {
  const { locale } = useAtomValue(userAtom);
  const [browserLocale, setBrowserLocale] = useAtom(browserLocaleAtom);
  const { i18n } = useTranslation();
  const location = useLocation();

  const changeLanguage = (): void => {
    if (locale) {
      return;
    }

    i18n.changeLanguage(getBrowserLocale());
    setBrowserLocale(getBrowserLocale());

    if (location.pathname === '/main.php') {
      window.location.reload();
    }
  };

  useEffect(() => {
    if (!locale) {
      setBrowserLocale(getBrowserLocale());
    }

    window.addEventListener('languagechange', changeLanguage);

    return () => window.removeEventListener('languagechange', changeLanguage);
  }, [locale, browserLocale, location.pathname]);

  return children;
};

export default LanguageDetector;
