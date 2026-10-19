import 'dayjs/locale/de';
import 'dayjs/locale/en';
import 'dayjs/locale/fr';

import dayjs from 'dayjs';

import { type Props, useLocaleTimezoneDate } from './useLocaleTimezoneDate';

declare global {
  interface Window {
    formatDate?: (props: Omit<Props, 'date'> & { date: number }) => string;
  }
}

// Publishes the formatting of the hook on window, for page.evaluate().
export const Api = (): JSX.Element => {
  const { formatDate } = useLocaleTimezoneDate();
  window.formatDate = ({ date, ...props }) =>
    formatDate({ ...props, date: dayjs(date) });

  return <p>formatDate</p>;
};
