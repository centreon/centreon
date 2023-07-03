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

  const currentDate = new Date();
  const expirationDate = new Date(getExpirationDate(response));

  const daysUntilExpiration = Math.floor(
    (expirationDate.getTime() - currentDate.getTime()) / (1000 * 60 * 60 * 24)
  );

  return daysUntilExpiration;
};
