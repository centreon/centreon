import dayjs from 'dayjs';
import { path, pipe, propEq, find } from 'ramda';

interface Props {
  module: string;
  response;
}
export const calculateDaysUntilLicenseExpiration = ({
  response,
  module
}: Props): number => {
  const getExpirationDate = pipe(
    path(['result', 'module', 'entities']),
    find(propEq('id', module)),
    path(['license', 'expiration_date'])
  ) as (data) => string;

  const currentDate = dayjs();
  const expirationDate = dayjs(getExpirationDate(response));
  const daysUntilExpiration = expirationDate.diff(currentDate, 'day');

  return daysUntilExpiration;
};
