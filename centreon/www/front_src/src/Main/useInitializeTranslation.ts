import { useLayoutEffect } from 'react';

import i18next, { i18n, Resource, ResourceLanguage } from 'i18next';
import { mergeAll, pipe, reduce, toPairs } from 'ramda';
import { initReactI18next } from 'react-i18next';

import { getData, useLocale, useRequest } from '@centreon/ui';

import {
  externalTranslationEndpoint,
  internalTranslationEndpoint
} from '../App/endpoint';

import { getBrowserLocale } from './utils';

interface UseInitializeTranslationState {
  getBrowserLocale: () => string;
  getExternalTranslation: () => Promise<void>;
  getInternalTranslation: () => Promise<void>;
  i18next: i18n;
  initializeI18n: (retrievedTranslations?: ResourceLanguage) => void;
}

const useInitializeTranslation = (): UseInitializeTranslationState => {
  const { sendRequest: getTranslations } = useRequest<ResourceLanguage>({
    httpCodesBypassErrorSnackbar: [500],
    request: getData
  });

  const locale = useLocale();

  const initializeI18n = (retrievedTranslations?: ResourceLanguage): void => {
    i18next.use(initReactI18next).init({
      fallbackLng: 'en',
      keySeparator: false,
      lng: locale?.substring(0, 2) || getBrowserLocale(),
      nsSeparator: false,
      resources: pipe(
        toPairs as (t) => Array<[string, ResourceLanguage]>,
        reduce(
          (acc, [language, values]) =>
            mergeAll([acc, { [language]: { translation: values } }]),
          {}
        )
      )(retrievedTranslations) as Resource
    });
  };

  const getTranslation = (endpoint: string): Promise<void> => {
    return getTranslations({
      endpoint
    })
      .then((retrievedTranslations) => {
        initializeI18n(retrievedTranslations);
      })
      .catch(() => {
        initializeI18n();
      });
  };

  const getExternalTranslation = (): Promise<void> =>
    getTranslation(externalTranslationEndpoint);

  const getInternalTranslation = (): Promise<void> =>
    getTranslation(internalTranslationEndpoint);

  useLayoutEffect(() => {
    initializeI18n();
  }, []);

  return {
    getBrowserLocale,
    getExternalTranslation,
    getInternalTranslation,
    i18next,
    initializeI18n
  };
};

export default useInitializeTranslation;
