import { useTranslation } from 'react-i18next';

import { array, boolean, number, object } from 'yup';
import { PasswordSecurityPolicy } from './models';
import { oneHour, sevenDays, twelveMonths } from './timestamps';
import {
  labelBlockingDurationMustBeLessThanOrEqualTo7Days,
  labelChooseADurationBetween1HourAnd1Week,
  labelChooseADurationBetween7DaysAnd12Months,
  labelChooseAValueBetween1and10,
  labelMaximum128Characters,
  labelMinimum8Characters,
  labelRequired
} from './translatedLabels';

const useValidationSchema = (): Yup.SchemaOf<PasswordSecurityPolicy> => {
  const { t } = useTranslation();

  return object().shape({
    attempts: number()
      .min(1, t(labelChooseAValueBetween1and10))
      .max(10, t(labelChooseAValueBetween1and10))
      .nullable()
      .defined(),
    blockingDuration: number()
      .max(sevenDays, t(labelBlockingDurationMustBeLessThanOrEqualTo7Days))
      .nullable()
      .defined(),
    canReusePasswords: boolean().defined(),
    delayBeforeNewPassword: number()
      .min(oneHour, t(labelChooseADurationBetween1HourAnd1Week))
      .max(sevenDays, t(labelChooseADurationBetween1HourAnd1Week))
      .nullable()
      .defined(),
    hasLowerCase: boolean().defined(),
    hasNumber: boolean().defined(),
    hasSpecialCharacter: boolean().defined(),
    hasUpperCase: boolean().defined(),
    passwordExpiration: object().shape({
      excludedUsers: array().of(string().required()),
      expirationDelay: number()
        .min(sevenDays, t(labelChooseADurationBetween7DaysAnd12Months))
        .max(twelveMonths, t(labelChooseADurationBetween7DaysAnd12Months))
        .nullable()
        .defined()
    }),
    passwordMinLength: number()
      .min(8, t(labelMinimum8Characters))
      .max(128, t(labelMaximum128Characters))
      .defined(t(labelRequired))
  });
};

export default useValidationSchema;
