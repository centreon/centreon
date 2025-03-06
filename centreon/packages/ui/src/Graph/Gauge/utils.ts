import { includes } from 'ramda';

export const getAngles = () =>
  includes('Firefox', window.navigator.userAgent)
    ? {
        endAngle: (2 * Math.PI) / 3,
        startAngle: -(2 * Math.PI) / 3
      }
    : {
        endAngle: -(2 * Math.PI) / 3,
        startAngle: (2 * Math.PI) / 3
      };
