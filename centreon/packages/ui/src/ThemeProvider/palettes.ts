import { equals } from 'ramda';

import { alpha, PaletteOptions } from '@mui/material';

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
    background: {
      default: string;
      listingHeader: string;
      panelGroups: string;
      paper: string;
    };
    pending: {
      contrastText: string;
      main: string;
    };
    statusBackground: StatusBackground;
  }
  interface StatusBackground {
    error: string;
    none: string;
    pending: string;
    success: string;
    unknown: string;
    warning: string;
  }
  interface PaletteOptions {
    pending: {
      contrastText: string;
      main: string;
    };
    statusBackground: StatusBackground;
  }

  interface TypeBackground {
    listingHeader: string;
    panel: string;
    panelGroups: string;
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
    selectedOpacity: 0.3
  },
  background: {
    default: '#F4F4F4',
    listingHeader: '#666666',
    panel: '#EDEDED',
    panelGroups: '#F5F5F5',
    paper: '#FFFFFF'
  },
  divider: '#E3E3E3',
  error: {
    contrastText: '#000',
    main: '#FF4A4A'
  },
  info: {
    contrastText: '#000',
    main: '#1588D1'
  },
  mode: ThemeMode.light,
  pending: {
    contrastText: '#000',
    main: '#1EBEB3'
  },
  primary: {
    contrastText: '#fff',
    dark: '#255891',
    light: '#cde7fc',
    main: '#2E68AA'
  },
  secondary: {
    contrastText: '#fff',
    main: '#C772D6'
  },
  statusBackground: {
    error: '#FF6666',
    none: alpha('#2E68AA', 0.1),
    pending: '#1EBEB3',
    success: '#88B922',
    unknown: '#E3E3E3',
    warning: '#FD9B27'
  },
  success: {
    contrastText: '#000',
    main: '#88B922'
  },
  text: {
    disabled: '#999999',
    primary: '#000000',
    secondary: '#666666'
  },
  warning: {
    contrastText: '#000',
    main: '#FD9B27'
  }
};

export const darkPalette: PaletteOptions = {
  action: {
    acknowledged: '#67532C',
    acknowledgedBackground: '#67532C',
    activatedOpacity: 0.3,
    active: '#B5B5B5',
    disabled: '#999999',
    disabledBackground: '#555555',
    focus: 'rgba(255, 255, 255, 0.30)',
    focusOpacity: 0.3,
    hover: 'rgba(255, 255, 255, 0.16)',
    hoverOpacity: 0.16,
    inDowntime: '#4B2352',
    inDowntimeBackground: '#4B2352',
    selected: 'rgba(255, 255, 255, 0.5)',
    selectedOpacity: 0.5
  },
  background: {
    default: '#4a4a4a',
    listingHeader: '#666666',
    panel: '#4a4a4a',
    panelGroups: '#252525',
    paper: '#212121'
  },
  divider: '#666666',
  error: {
    contrastText: '#fff',
    main: '#D60101'
  },
  info: {
    contrastText: '#fff',
    main: '#1CA9F4'
  },
  mode: ThemeMode.dark,
  pending: {
    contrastText: '#fff',
    main: '#118077'
  },
  primary: {
    contrastText: '#000',
    dark: '#4974A5',
    light: '#8bbff9',
    main: '#6eaff8'
  },
  secondary: {
    contrastText: '#fff',
    main: '#7C1FA2'
  },
  statusBackground: {
    error: '#D60101',
    none: alpha('#2E68AA', 0.1),
    pending: '#118077',
    success: '#5F8118',
    unknown: '#666666',
    warning: '#C55400'
  },
  success: {
    contrastText: '#fff',
    main: '#5F8118'
  },
  text: {
    disabled: '#666666',
    primary: '#FFFFFF',
    secondary: '#CCCCCC'
  },
  warning: {
    contrastText: '#fff',
    main: '#C55400'
  }
};

export const getPalette = (mode: ThemeMode): PaletteOptions =>
  equals(mode, ThemeMode.dark) ? darkPalette : lightPalette;
