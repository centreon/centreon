import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';

import 'dayjs/locale/de';
import 'dayjs/locale/fr';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/pt';
import { Props, useLocaleTimezoneDate } from './useLocaleTimezoneDate';

dayjs.extend(localizedFormat);
dayjs.extend(utc);
dayjs.extend(timezonePlugin);

const date = dayjs(1602252661000);

const Test = ({
  locale,
  timezone,
  format
}: Omit<Props, 'date'>): JSX.Element => {
  const { formatDate } = useLocaleTimezoneDate();

  return <p>{formatDate({ date, format, locale, timezone })}</p>;
};

const initialize = ({ timezone, locale, format }): void => {
  cy.mount({
    Component: <Test format={format} locale={locale} timezone={timezone} />
  });
};

const locales = [undefined, 'en-GB', 'fr-FR', 'de-DE'];
const timezones = [
  'Europe/Paris',
  'Europe/London',
  'UTC',
  'Africa/Casablanca',
  'America/New_York'
];
const formats = ['L LT', 'LLLL'];

const resultsEN = [
  '10/09/2020 4:11 PM',
  'Friday, October 9, 2020 4:11 PM',
  '10/09/2020 3:11 PM',
  'Friday, October 9, 2020 3:11 PM',
  '10/09/2020 2:11 PM',
  'Friday, October 9, 2020 2:11 PM',
  '10/09/2020 3:11 PM',
  'Friday, October 9, 2020 3:11 PM',
  '10/09/2020 10:11 AM',
  'Friday, October 9, 2020 10:11 AM'
];

const resultsFR = [
  '09/10/2020 16:11',
  'vendredi 9 octobre 2020 16:11',
  '09/10/2020 15:11',
  'vendredi 9 octobre 2020 15:11',
  '09/10/2020 14:11',
  'vendredi 9 octobre 2020 14:11',
  '09/10/2020 15:11',
  'vendredi 9 octobre 2020 15:11',
  '09/10/2020 10:11',
  'vendredi 9 octobre 2020 10:11'
];

const resultsDE = [
  '09.10.2020 16:11',
  'Freitag, 9. Oktober 2020 16:11',
  '09.10.2020 15:11',
  'Freitag, 9. Oktober 2020 15:11',
  '09.10.2020 14:11',
  'Freitag, 9. Oktober 2020 14:11',
  '09.10.2020 15:11',
  'Freitag, 9. Oktober 2020 15:11',
  '09.10.2020 10:11',
  'Freitag, 9. Oktober 2020 10:11'
];

const results = [...resultsEN, ...resultsEN, ...resultsFR, ...resultsDE];
let index = 0;

locales.forEach((locale) => {
  timezones.forEach((timezone) => {
    formats.forEach((format) => {
      it(`displays the date with the locale ${locale}, the timezone ${timezone} and the format ${format}`, () => {
        initialize({ format, locale, timezone });

        cy.contains(results[index]).should('be.visible');
        index += 1;
      });
    });
  });
});
