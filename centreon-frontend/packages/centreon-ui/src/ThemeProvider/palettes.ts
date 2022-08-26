import { equals } from 'ramda';

import { PaletteOptions } from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

declare module '@mui/material/styles/createPalette' {
  interface TypeAction {
    acknowledged: string;
    acknowledgedBackground: string;
    inDowntime: string;
    inDowntimeBackground: string;
  }
}

declare module '@mui/material/styles' {
  interface Palette {
    pending: {
      main: string;
    };
  }
  interface PaletteOptions {
    pending: {
      main: string;
    };
  }
}

declare module '@mui/material/Button' {
  interface ButtonPropsColorOverrides {
    pending: true;
  }
}

declare module '@mui/material/Badge' {
  interface BadgePropsColorOverrides {
    pending: true;
  }
}

export const lightPalette: PaletteOptions = {
  action: {
    acknowledged: '#67532C',
    acknowledgedBackground: '#F5F1E9',
    activatedOpacity: 0.12,
    active: '#666666',
    disabled: '#999999',
    disabledBackground: 'rgba(0, 0, 0, 0.12)',
    focus: 'rgba(0, 0, 0, 0.12)',
    focusOpacity: 0.12,
    hover: 'rgba(0, 0, 0, 0.06)',
    hoverOpacity: 0.06,
    inDowntime: '#4B2352',
    inDowntimeBackground: '#F0E9F8',
    selected: 'rgba(102, 102, 102, 0.3)',
    selectedOpacity: 0.3,
  },
  background: {
    default: '#F4F4F4',
    paper: '#FFFFFF',
  },
  divider: '#E3E3E3',
  error: {
    main: '#FF4A4A',
  },
  info: {
    main: '#1588D1',
  },
  mode: ThemeMode.light,
  pending: {
    main: '#1EBEB3',
  },
  primary: {
    dark: '#255891',
    main: '#2E68AA',
  },
  secondary: {
    main: '#C772D6',
  },
  success: {
    main: '#88B922',
  },
  text: {
    disabled: '#999999',
    primary: '#000000',
    secondary: '#666666',
  },
  warning: {
    main: '#FD9B27',
  },
};

export const darkPalette: PaletteOptions = {
  action: {
    acknowledged: '#67532C',
    acknowledgedBackground: '#F5F1E9',
    activatedOpacity: 0.3,
    active: '#B5B5B5',
    disabled: '#999999',
    disabledBackground: '#555555',
    focus: 'rgba(255, 255, 255, 0.30)',
    focusOpacity: 0.3,
    hover: 'rgba(255, 255, 255, 0.16)',
    hoverOpacity: 0.16,
    inDowntime: '#4B2352',
    inDowntimeBackground: '#F0E9F8',
    selected: 'rgba(255, 255, 255, 0.5)',
    selectedOpacity: 0.5,
  },
  background: {
    default: '#000000',
    paper: '#212121',
  },
  divider: '#666666',
  error: {
    main: '#D60101',
  },
  info: {
    main: '#1CA9F4',
  },
  mode: ThemeMode.dark,
  pending: {
    main: '#118077',
  },
  primary: {
    dark: '#4974A5',
    main: '#6EAFF8',
  },
  secondary: {
    main: '#7C1FA2',
  },
  success: {
    main: '#759F1D',
  },
  text: {
    disabled: '#666666',
    primary: '#FFFFFF',
    secondary: '#B5B5B5',
  },
  warning: {
    main: '#FC7E00',
  },
};

export const getPalette = (mode: ThemeMode): PaletteOptions =>
  equals(mode, ThemeMode.dark) ? darkPalette : lightPalette;
