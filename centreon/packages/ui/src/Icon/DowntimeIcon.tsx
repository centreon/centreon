import { SvgIconProps } from '@mui/material';

import BaseIcon from './BaseIcon';

const icon = (
  <g>
    <path d="M7.6215 5.243C7.9422 5.243 8.2557 5.1479 8.52236 4.96972C8.78901 4.79155 8.99685 4.53831 9.11958 4.24202C9.2423 3.94573 9.27441 3.6197 9.21185 3.30516C9.14928 2.99062 8.99485 2.70169 8.76808 2.47492C8.54131 2.24815 8.25238 2.09372 7.93784 2.03116C7.6233 1.96859 7.29727 2.0007 7.00098 2.12343C6.70469 2.24616 6.45145 2.45399 6.27327 2.72065C6.0951 2.9873 6 3.30079 6 3.6215C6 4.05155 6.17084 4.46398 6.47493 4.76807C6.77902 5.07216 7.19145 5.243 7.6215 5.243Z" />
    <path d="M17.8192 12.3739C17.743 12.1905 17.6143 12.0338 17.4492 11.9235C17.2841 11.8132 17.0901 11.7541 16.8915 11.7538C16.8915 11.7538 14.9107 11.4903 14.3232 11.8736C14.0659 12.083 13.8556 12.3442 13.706 12.6403L10.396 12.1161C10.142 11.2785 9.05429 8.10738 9.05429 8.10738L8.24354 6.81076C8.09994 6.57117 7.89855 6.37142 7.6578 6.22979C7.41705 6.08815 7.14463 6.00914 6.86545 6C6.64749 6.01064 6.43062 6.03754 6.21666 6.0805L2 7.86971V11.68H2.23096L4.36037 12.0174L2.81746 19.7866H4.52041L5.979 13.3006L7.68196 14.9221V19.7866H9.30345V13.706L8.16304 12.6183L13.3649 13.4415L10.4094 19.9725L20.928 19.8671L17.8192 12.3739ZM8.03845 9.87167C8.03845 9.87167 8.59142 11.1712 8.88179 11.8736L7.67525 11.682L8.03845 9.87167ZM3.62437 11.0408V8.921L5.08295 8.35368L4.52137 11.1827L3.62437 11.0408Z" />
  </g>
);

export const DowntimeIcon = (props: SvgIconProps): JSX.Element => (
  <BaseIcon
    {...props}
    Icon={icon}
    height="24"
    viewBox="0 0 24 24"
    width="24"
    dataTestId="DowntimeIcon"
  />
);
